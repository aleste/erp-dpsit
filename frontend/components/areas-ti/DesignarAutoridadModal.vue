<template>
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div v-dialogo="() => $emit('close')" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto">
      <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold">Designar Máxima Autoridad</h2>
          <p class="text-sm text-slate-400">{{ area.nombre }}</p>
        </div>
        <button type="button" aria-label="Cerrar" @click="$emit('close')" class="text-slate-400 hover:text-white">✕</button>
      </div>

      <form @submit.prevent="confirmar" class="p-6 space-y-4 text-sm">
        <!-- Qué pasa con la autoridad actual -->
        <div
          class="p-3 rounded-xl border"
          :class="vigente ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-700'"
        >
          <template v-if="vigente">
            <p>
              Reemplaza a <strong>{{ nombreVigente }}</strong>
              ({{ vigente.tipo_rol?.nombre || 'máxima autoridad' }}, desde el {{ formatFecha(vigente.fecha_inicio) }}).
            </p>
            <p v-if="!fechaInvalida" class="mt-1">
              Su designación finalizará el <strong>{{ formatFecha(fechaCese) }}</strong>, el día anterior a la asunción.
            </p>
          </template>
          <p v-else>El área no tiene una máxima autoridad vigente.</p>
        </div>

        <div>
          <label for="designar-persona-id" class="block font-bold text-slate-700 mb-1">Persona a designar *</label>
          <PersonaSearchSelect
            v-model="form.persona_id"
            input-id="designar-persona-id"
            placeholder="Buscar por apellido, nombre o CUIL..."
            @change="persona = $event"
          />
          <p v-if="esLaMismaPersona" class="mt-1 text-xs font-semibold text-rose-700">
            Esta persona ya es la máxima autoridad vigente del área.
          </p>
        </div>

        <div>
          <label for="designar-tipo-rol-id" class="block font-bold text-slate-700 mb-1">Cargo / denominación formal *</label>
          <select
            id="designar-tipo-rol-id"
            v-model="form.tipo_rol_id"
            required
            class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            <option value="" disabled>Seleccione el cargo...</option>
            <option v-for="r in tiposRol" :key="r.id" :value="r.id">{{ r.nombre }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="designar-fecha-inicio" class="block font-bold text-slate-700 mb-1">Fecha de asunción *</label>
            <input
              id="designar-fecha-inicio"
              v-model="form.fecha_inicio"
              type="date"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
          <div>
            <label for="designar-acto" class="block font-bold text-slate-700 mb-1">Acto administrativo</label>
            <input
              id="designar-acto"
              v-model="form.acto_administrativo"
              type="text"
              maxlength="150"
              placeholder="Ej: Decreto 1250/24"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
        </div>
        <p v-if="fechaInvalida" class="-mt-2 text-xs font-semibold text-rose-700">
          La asunción debe ser posterior al {{ formatFecha(vigente.fecha_inicio) }}, inicio de la autoridad vigente.
        </p>
        <p v-if="!form.acto_administrativo.trim()" class="-mt-2 text-xs text-amber-800">
          Sin acto administrativo, la designación queda sin respaldo formal. Se puede completar después editando la asignación.
        </p>

        <div>
          <label for="designar-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones</label>
          <textarea
            id="designar-observaciones"
            v-model="form.observaciones"
            rows="2"
            class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
          ></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" @click="$emit('close')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando || !puedeGuardar"
            class="px-5 py-2 bg-amber-700 hover:bg-amber-600 text-white rounded-xl font-bold shadow-md shadow-amber-700/20 disabled:opacity-50"
          >
            {{ guardando ? 'Designando...' : 'Designar autoridad' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import { hoyISO, formatFecha, parseISO, toISO } from '~/composables/useFechas'

// Designación de la máxima autoridad de un área TI. El backend cierra la autoridad vigente
// con cese el día anterior a la asunción (AutoridadAreaService, D-32).
const props = defineProps({
  area: { type: Object, required: true },
  tiposRol: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved'])

const { post } = useApi()
const { toast, confirmDialog } = useToast()

const vigente = computed(() => props.area.maxima_autoridad_vigente || null)
const nombreVigente = computed(() => {
  const p = vigente.value?.persona
  return p ? `${p.apellido}, ${p.nombre}` : 'la autoridad vigente'
})

const rolPorDefecto = () =>
  vigente.value?.tipo_rol_id || props.tiposRol.find((r) => r.es_maxima_autoridad_default)?.id || ''

const form = reactive({
  persona_id: '',
  tipo_rol_id: rolPorDefecto(),
  fecha_inicio: hoyISO(),
  acto_administrativo: '',
  observaciones: '',
})
const persona = ref(null)
const guardando = ref(false)

const fechaCese = computed(() => {
  if (!form.fecha_inicio) return null
  const d = parseISO(form.fecha_inicio)
  d.setUTCDate(d.getUTCDate() - 1)
  return toISO(d)
})

const esLaMismaPersona = computed(() => !!vigente.value && Number(form.persona_id) === vigente.value.persona_id)
const fechaInvalida = computed(
  () => !!vigente.value && !!form.fecha_inicio && form.fecha_inicio <= vigente.value.fecha_inicio.substring(0, 10),
)
const puedeGuardar = computed(
  () => !!form.persona_id && !!form.tipo_rol_id && !!form.fecha_inicio && !esLaMismaPersona.value && !fechaInvalida.value,
)

const nombrePersona = computed(() => (persona.value ? `${persona.value.apellido}, ${persona.value.nombre}` : 'la persona elegida'))

const confirmar = async () => {
  if (!form.persona_id) {
    toast.warning('Seleccione a la persona que asume como máxima autoridad.')
    return
  }
  if (!puedeGuardar.value) return

  const ok = await confirmDialog({
    title: 'Confirmar designación',
    message: vigente.value
      ? `${nombrePersona.value} pasa a ser la máxima autoridad de ${props.area.nombre} desde el ${formatFecha(form.fecha_inicio)}. La designación de ${nombreVigente.value} finalizará el ${formatFecha(fechaCese.value)}.`
      : `${nombrePersona.value} pasa a ser la máxima autoridad de ${props.area.nombre} desde el ${formatFecha(form.fecha_inicio)}.`,
    confirmText: 'Sí, designar',
    cancelText: 'Revisar',
    type: 'warning',
  })
  if (!ok) return

  guardando.value = true
  try {
    const res = await post(`/areas-ti/${props.area.id}/maxima-autoridad`, form)
    if (res.success) {
      toast.success(
        vigente.value
          ? `${nombrePersona.value} es la nueva máxima autoridad desde el ${formatFecha(form.fecha_inicio)}. La designación anterior finalizó el ${formatFecha(fechaCese.value)}.`
          : `${nombrePersona.value} es la máxima autoridad del área desde el ${formatFecha(form.fecha_inicio)}.`,
        { title: 'Designación registrada' },
      )
      emit('saved')
    }
  } catch (err) {
    toast.error(err)
  } finally {
    guardando.value = false
  }
}
</script>
