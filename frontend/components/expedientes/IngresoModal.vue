<template>
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div v-dialogo="() => $emit('close')" class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[92vh] flex flex-col">
      <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold">Registrar Ingreso de Expediente</h2>
          <p class="text-xs text-slate-400">Expediente nuevo o reingreso de uno ya tramitado</p>
        </div>
        <button type="button" aria-label="Cerrar" @click="$emit('close')" class="text-slate-400 hover:text-white">✕</button>
      </div>

      <form @submit.prevent="guardar" class="p-6 space-y-4 text-xs overflow-y-auto">
        <div>
          <label for="ingreso-form-numero" class="block font-bold text-slate-700 mb-1">Nº de expediente *</label>
          <input id="ingreso-form-numero"
            v-model="form.numero"
            @input="verificarConDemora"
            type="text"
            required
            placeholder="EX-2026-12345678- -GDEBA-REPARTICION"
            class="campo font-mono"
          />
          <!-- Resultado de la verificación (reemplaza el resaltado de duplicados de la planilla) -->
          <div v-if="verificacion" class="mt-2 p-3 rounded-xl border text-[11px]" :class="claseVerificacion">
            <template v-if="!verificacion.existe">Expediente nuevo: se registrará la 1ª intervención.</template>
            <template v-else-if="verificacion.en_area">
              <strong>El expediente ya está en el área</strong> ({{ verificacion.expediente.intervencion_actual?.estado?.nombre }}).
              <NuxtLink :to="`/expedientes/${verificacion.expediente.id}`" class="font-bold underline" @click="$emit('close')">Abrir su ficha</NuxtLink>
              para registrar movimientos.
            </template>
            <template v-else>
              <strong>Reingreso:</strong> el expediente ya fue tramitado. Se registrará la
              <strong>{{ verificacion.proxima_intervencion }}ª intervención</strong>; el historial anterior se conserva.
              <span v-if="verificacion.expediente.caratula" class="block text-slate-500 mt-1">Carátula: {{ verificacion.expediente.caratula }}</span>
            </template>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="ingreso-form-fecha-ingreso" class="block font-bold text-slate-700 mb-1">Fecha de ingreso *</label>
            <input id="ingreso-form-fecha-ingreso" v-model="form.fecha_ingreso" type="date" required :max="hoyISO()" class="campo" />
          </div>
          <div>
            <label for="ingreso-form-ministerio-id" class="block font-bold text-slate-700 mb-1">Organismo</label>
            <select id="ingreso-form-ministerio-id" v-model="form.ministerio_id" :disabled="esReingreso && !!verificacion.expediente.ministerio_id" class="campo">
              <option value="">Sin especificar</option>
              <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.nombre }}</option>
            </select>
          </div>
        </div>

        <div v-if="!esReingreso || !verificacion.expediente.caratula">
          <label for="ingreso-form-caratula" class="block font-bold text-slate-700 mb-1">Carátula / asunto</label>
          <textarea id="ingreso-form-caratula" v-model="form.caratula" rows="2" class="campo" placeholder="Ej: Solicitud de bonificación ..."></textarea>
        </div>

        <div>
          <label for="ingreso-form-descripcion" class="block font-bold text-slate-700 mb-1">{{ esReingreso ? 'Motivo del reingreso *' : 'Motivo de la intervención' }}</label>
          <textarea id="ingreso-form-descripcion" v-model="form.descripcion" rows="2" :required="esReingreso" class="campo" placeholder="Qué se solicita al área en este pase"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="ingreso-form-tipo-requerimiento-id" class="block font-bold text-slate-700 mb-1">Requerimiento</label>
            <select id="ingreso-form-tipo-requerimiento-id" v-model="form.tipo_requerimiento_id" class="campo">
              <option value="">Sin especificar</option>
              <option v-for="t in requerimientosActivos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
            </select>
          </div>
          <div>
            <label for="ingreso-form-responsable-id" class="block font-bold text-slate-700 mb-1">Responsable</label>
            <select id="ingreso-form-responsable-id" v-model="form.responsable_id" class="campo">
              <option value="">Sin asignar</option>
              <option v-for="u in opciones.integrantes" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label for="ingreso-form-estado-id" class="block font-bold text-slate-700 mb-1">Estado inicial</label>
            <select id="ingreso-form-estado-id" v-model="form.estado_id" class="campo">
              <option v-for="e in estadosTrabajo" :key="e.id" :value="e.id">{{ e.nombre }}</option>
            </select>
          </div>
          <div>
            <label for="ingreso-form-prioridad" class="block font-bold text-slate-700 mb-1">Prioridad</label>
            <select id="ingreso-form-prioridad" v-model="form.prioridad" class="campo">
              <option value="NORMAL">Normal</option>
              <option value="ALTA">Alta</option>
            </select>
          </div>
        </div>

        <details class="rounded-xl border border-slate-200 p-3">
          <summary class="font-bold text-slate-600 cursor-pointer">Seguimiento opcional (próxima acción, plazo)</summary>
          <div class="grid grid-cols-2 gap-3 mt-3">
            <div class="col-span-2">
              <input v-model="form.proxima_accion" type="text" maxlength="255" placeholder="Próxima acción prevista" class="campo" />
            </div>
            <div>
              <label for="ingreso-form-fecha-proxima-accion" class="block font-bold text-slate-700 mb-1">Fecha de la próxima acción</label>
              <input id="ingreso-form-fecha-proxima-accion" v-model="form.fecha_proxima_accion" type="date" class="campo" />
            </div>
            <div>
              <label for="ingreso-form-fecha-vencimiento" class="block font-bold text-slate-700 mb-1">Vencimiento (solo si hay un plazo real)</label>
              <input id="ingreso-form-fecha-vencimiento" v-model="form.fecha_vencimiento" type="date" class="campo" />
            </div>
          </div>
        </details>

        <div>
          <label for="ingreso-form-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones</label>
          <textarea id="ingreso-form-observaciones" v-model="form.observaciones" rows="2" class="campo"></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" @click="$emit('close')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">Cancelar</button>
          <button
            type="submit"
            :disabled="guardando || verificacion?.en_area"
            class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
          >
            {{ guardando ? 'Registrando...' : (esReingreso ? 'Registrar reingreso' : 'Registrar ingreso') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import { useExpedientes } from '~/composables/useExpedientes'
import { hoyISO } from '~/composables/useFechas'

const props = defineProps({
  ministerios: { type: Array, default: () => [] },
  // Número precargado (reingreso desde la ficha del expediente)
  numeroInicial: { type: String, default: '' },
})
const emit = defineEmits(['close', 'saved'])

const { get, post } = useApi()
const { toast } = useToast()
const { opciones, cargarOpciones } = useExpedientes()

const guardando = ref(false)
const verificacion = ref(null)

const form = reactive({
  numero: '', fecha_ingreso: hoyISO(), ministerio_id: '', caratula: '', descripcion: '', tipo_requerimiento_id: '',
  responsable_id: '', estado_id: '', prioridad: 'NORMAL', proxima_accion: '', fecha_proxima_accion: '', fecha_vencimiento: '', observaciones: '',
})

const estadosTrabajo = computed(() => opciones.value.estados.filter(e => !e.es_final && e.activo))
const requerimientosActivos = computed(() => opciones.value.tipos_requerimiento.filter(t => t.activo))
const esReingreso = computed(() => !!verificacion.value?.existe && !verificacion.value?.en_area)
const claseVerificacion = computed(() => {
  if (!verificacion.value?.existe) return 'bg-emerald-50 border-emerald-200 text-emerald-800'
  return verificacion.value.en_area ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800'
})

let temporizador = null
const verificarConDemora = () => {
  clearTimeout(temporizador)
  verificacion.value = null
  if (form.numero.trim().length < 6) return
  temporizador = setTimeout(async () => {
    try {
      const res = await get('/expedientes/buscar-numero', { numero: form.numero })
      if (res.success) verificacion.value = res.data
    } catch (err) {
      console.error('Error verificando el número:', err)
    }
  }, 400)
}

const guardar = async () => {
  guardando.value = true
  try {
    const res = await post('/expedientes', { ...form, colaboradores: [] })
    if (res.success) {
      toast.success(res.message)
      emit('saved', res.data)
    }
  } catch (err) {
    toast.error(err)
  } finally {
    guardando.value = false
  }
}

onMounted(async () => {
  await cargarOpciones()
  form.estado_id = estadosTrabajo.value[0]?.id || ''
  if (props.numeroInicial) {
    form.numero = props.numeroInicial
    verificarConDemora()
  }
})
</script>

<style scoped>
.campo {
  @apply w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 disabled:opacity-60;
}
</style>
