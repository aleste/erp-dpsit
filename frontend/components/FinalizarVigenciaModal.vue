<template>
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div v-dialogo="() => $emit('close')" class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto">
      <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold">Finalizar vigencia</h2>
          <p class="text-sm text-slate-400">Cese o baja del cargo</p>
        </div>
        <button type="button" aria-label="Cerrar" @click="$emit('close')" class="text-slate-400 hover:text-white">✕</button>
      </div>

      <form @submit.prevent="finalizar" class="p-6 space-y-4 text-sm">
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800">
          <p class="font-bold">{{ nombrePersona }}</p>
          <p class="text-slate-600">
            {{ asignacion.tipo_rol?.nombre }}<template v-if="nombreArea"> · {{ nombreArea }}</template>
          </p>
          <p class="text-xs text-slate-600 mt-0.5">Desde el {{ formatFecha(asignacion.fecha_inicio) }}</p>
        </div>

        <div v-if="asignacion.es_maxima_autoridad" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900">
          Es la <strong>máxima autoridad</strong> del área: al finalizar, el área queda sin máxima autoridad vigente hasta que se designe otra.
        </div>

        <div>
          <label for="finalizar-fecha-fin" class="block font-bold text-slate-700 mb-1">Fecha de finalización *</label>
          <input
            id="finalizar-fecha-fin"
            v-model="form.fecha_fin"
            type="date"
            required
            :min="inicio"
            class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
          />
          <p v-if="fechaInvalida" class="mt-1 text-xs font-semibold text-rose-700">
            No puede ser anterior al inicio del cargo ({{ formatFecha(inicio) }}).
          </p>
        </div>

        <div>
          <label for="finalizar-motivo" class="block font-bold text-slate-700 mb-1">Motivo</label>
          <textarea
            id="finalizar-motivo"
            v-model="form.motivo"
            rows="2"
            maxlength="500"
            placeholder="Ej: Renuncia, pase a otro organismo, conclusión del mandato..."
            class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
          ></textarea>
        </div>

        <div>
          <label for="finalizar-acto" class="block font-bold text-slate-700 mb-1">Acto administrativo de la baja</label>
          <input
            id="finalizar-acto"
            v-model="form.acto_administrativo"
            type="text"
            maxlength="150"
            placeholder="Ej: RESOL-2026-15-GDEBA"
            class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
          />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" @click="$emit('close')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando || !form.fecha_fin || fechaInvalida"
            class="px-5 py-2 bg-amber-700 hover:bg-amber-600 text-white rounded-xl font-bold shadow-md shadow-amber-700/20 disabled:opacity-50"
          >
            {{ guardando ? 'Finalizando...' : 'Finalizar vigencia' }}
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
import { hoyISO, formatFecha } from '~/composables/useFechas'

// Finalización de la vigencia de una asignación de rol, igual en el área, Personas y Roles.
// persona y areaNombre completan los datos cuando la asignación llega sin esas relaciones.
const props = defineProps({
  asignacion: { type: Object, required: true },
  persona: { type: Object, default: null },
  areaNombre: { type: String, default: '' },
})
const emit = defineEmits(['close', 'saved'])

const { post } = useApi()
const { toast } = useToast()

const persona = computed(() => props.asignacion.persona || props.persona)
const nombrePersona = computed(() => (persona.value ? `${persona.value.apellido}, ${persona.value.nombre}` : 'Agente'))
const nombreArea = computed(() => props.asignacion.area_ti?.nombre || props.areaNombre)
const inicio = computed(() => (props.asignacion.fecha_inicio || '').substring(0, 10))

const hoy = hoyISO()
const form = reactive({
  fecha_fin: hoy < inicio.value ? inicio.value : hoy,
  motivo: '',
  acto_administrativo: '',
})
const guardando = ref(false)

const fechaInvalida = computed(() => !!form.fecha_fin && form.fecha_fin < inicio.value)

const finalizar = async () => {
  if (fechaInvalida.value) return
  guardando.value = true
  try {
    const res = await post(`/asignaciones-rol/${props.asignacion.id}/finalizar-vigencia`, form)
    if (res.success) {
      toast.success(
        `La vigencia de ${nombrePersona.value} como ${props.asignacion.tipo_rol?.nombre || 'agente'} finaliza el ${formatFecha(form.fecha_fin)}.`,
        { title: 'Vigencia finalizada' },
      )
      if (props.asignacion.es_maxima_autoridad) {
        toast.warning(`${nombreArea.value || 'El área'} queda sin máxima autoridad vigente. Designe a la nueva autoridad desde la ficha del área.`)
      }
      emit('saved')
    }
  } catch (err) {
    toast.error(err)
  } finally {
    guardando.value = false
  }
}
</script>
