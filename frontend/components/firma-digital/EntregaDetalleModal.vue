<template>
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div v-dialogo="() => $emit('close')" class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[92vh] flex flex-col">
      <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
        <div>
          <span class="text-[10px] uppercase font-bold text-sky-400 tracking-wider">Entrega de token #{{ entregaId }}</span>
          <h2 class="text-base font-bold">{{ entrega ? `${entrega.persona?.apellido}, ${entrega.persona?.nombre}` : 'Cargando...' }}</h2>
        </div>
        <button type="button" aria-label="Cerrar" @click="$emit('close')" class="text-slate-400 hover:text-white">✕</button>
      </div>

      <div v-if="cargando" class="p-12 text-center text-xs text-slate-400">Cargando detalle...</div>

      <div v-else-if="entrega" class="p-6 space-y-5 text-xs overflow-y-auto">
        <!-- Datos generales -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <p class="text-[10px] font-bold uppercase text-slate-400">Contacto</p>
            <p class="font-semibold text-slate-800 mt-0.5 break-all">{{ entrega.persona?.email_principal || entrega.persona?.email_alternativo || 'Sin email' }}</p>
            <p v-if="!entrega.persona?.email_principal && !entrega.persona?.email_alternativo" class="text-[10px] text-orange-600 mt-1">
              ⚠ Sin email no se pueden enviar recordatorios. Complételo en el padrón de Personas.
            </p>
            <p class="text-slate-500 mt-1">{{ identificacion(entrega.persona) }}</p>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <p class="text-[10px] font-bold uppercase text-slate-400">Organismo</p>
            <p class="font-semibold text-slate-800 mt-0.5">{{ entrega.ministerio?.nombre || entrega.organismo_externo || 'Sin organismo' }}</p>
            <p v-if="entrega.organismo_externo" class="text-[10px] text-slate-500">Externo al ERP</p>
            <p v-if="entrega.cargo_funcion" class="text-slate-500 mt-1">{{ entrega.cargo_funcion }}</p>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <p class="text-[10px] font-bold uppercase text-slate-400">Dispositivo</p>
            <p class="font-semibold text-slate-800 mt-0.5">
              {{ TIPOS_DISPOSITIVO[entrega.dispositivo?.tipo_dispositivo] || entrega.dispositivo?.tipo_dispositivo }} · {{ entrega.dispositivo?.marca || 's/marca' }} {{ entrega.dispositivo?.modelo || '' }}
            </p>
            <p class="font-mono text-slate-500 mt-1">Serie: {{ entrega.dispositivo?.numero_serie || 's/n' }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-slate-600">
          <span>Entregado el <strong>{{ formatFecha(entrega.fecha_entrega) }}</strong></span>
          <span class="px-2 py-0.5 rounded-lg bg-slate-100 font-bold uppercase text-[10px]">{{ ESTADOS_ENTREGA[entrega.estado] || entrega.estado }}</span>
          <span v-if="entrega.fecha_fin">Finalizada el <strong>{{ formatFecha(entrega.fecha_fin) }}</strong></span>
          <span v-if="entrega.motivo_fin" class="text-slate-500">— {{ entrega.motivo_fin }}</span>
        </div>
        <p v-if="entrega.observaciones" class="text-slate-500 italic">{{ entrega.observaciones }}</p>

        <!-- Acciones -->
        <div v-if="authStore.isEditor && esAsignada" class="flex flex-wrap gap-2 pt-1">
          <button @click="$emit('edit', entrega)" class="btn-sec">✏️ Editar entrega</button>
          <button @click="abrirPanel('renovar')" class="btn-sec">🔄 Renovar certificado</button>
          <button @click="abrirPanel('fechas')" class="btn-sec">📅 Corregir fechas</button>
          <button @click="$emit('replace', entrega)" class="btn-sec">🔁 Reemplazar dispositivo</button>
          <button @click="abrirPanel('finalizar')" class="btn-sec text-rose-700">⏹ Finalizar entrega</button>
        </div>

        <!-- Panel: renovar o corregir fechas -->
        <form v-if="panel === 'renovar' || panel === 'fechas'" @submit.prevent="guardarFechas" class="p-4 rounded-xl border border-sky-200 bg-sky-50/40 space-y-3">
          <p class="font-bold text-slate-700">{{ panel === 'renovar' ? 'Renovar certificado en el mismo dispositivo' : 'Corregir fechas del certificado actual' }}</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label for="entrega-detalle-fechas-fecha-inicio" class="block font-bold text-slate-600 mb-1">Inicio del certificado *</label>
              <input id="entrega-detalle-fechas-fecha-inicio" v-model="fechas.fecha_inicio" type="date" required class="campo" />
            </div>
            <div>
              <label for="entrega-detalle-fechas-fecha-vencimiento" class="block font-bold text-slate-600 mb-1">Vencimiento</label>
              <input id="entrega-detalle-fechas-fecha-vencimiento" v-if="fechas.vencimiento_manual" v-model="fechas.fecha_vencimiento" type="date" required class="campo" />
              <p v-else class="p-2.5 font-extrabold text-slate-800">{{ formatFecha(vencimientoCalculado) }}</p>
              <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-600 cursor-pointer">
                <input v-model="fechas.vencimiento_manual" type="checkbox" class="w-3.5 h-3.5 rounded text-sky-600" @change="precargarVencimiento" /> Manual
              </label>
            </div>
            <div>
              <label for="entrega-detalle-fechas-fecha-recordatorio" class="block font-bold text-slate-600 mb-1">Recordatorio</label>
              <input id="entrega-detalle-fechas-fecha-recordatorio" v-if="fechas.recordatorio_manual" v-model="fechas.fecha_recordatorio" type="date" required class="campo" />
              <p v-else class="p-2.5 font-extrabold text-slate-800">{{ formatFecha(recordatorioCalculado) }}</p>
              <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-600 cursor-pointer">
                <input v-model="fechas.recordatorio_manual" type="checkbox" class="w-3.5 h-3.5 rounded text-sky-600" @change="precargarRecordatorio" /> Manual
              </label>
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="panel = null" class="px-3 py-1.5 bg-slate-100 rounded-lg font-semibold">Cancelar</button>
            <button type="submit" :disabled="procesando" class="px-4 py-1.5 bg-sky-600 text-white rounded-lg font-bold disabled:opacity-50">
              {{ panel === 'renovar' ? 'Registrar renovación' : 'Guardar fechas' }}
            </button>
          </div>
        </form>

        <!-- Panel: finalizar -->
        <form v-if="panel === 'finalizar'" @submit.prevent="finalizar" class="p-4 rounded-xl border border-rose-200 bg-rose-50/40 space-y-3">
          <p class="font-bold text-slate-700">Finalizar entrega</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select v-model="fin.estado" required class="campo">
              <option value="DEVUELTA">Devuelto por la persona</option>
              <option value="EXTRAVIADA">Extraviado</option>
              <option value="REVOCADA">Revocado</option>
            </select>
            <input v-model="fin.fecha_fin" type="date" required class="campo" />
            <select v-model="fin.estado_dispositivo" class="campo">
              <option value="">Estado del dispositivo: automático</option>
              <option value="DISPONIBLE">Disponible para reasignar</option>
              <option value="DEVUELTO">Devuelto (en revisión)</option>
              <option value="EXTRAVIADO">Extraviado</option>
              <option value="DADO_DE_BAJA">Dado de baja</option>
            </select>
          </div>
          <input v-model="fin.motivo_fin" type="text" placeholder="Motivo (opcional)" class="campo" />
          <div class="flex justify-end gap-2">
            <button type="button" @click="panel = null" class="px-3 py-1.5 bg-slate-100 rounded-lg font-semibold">Cancelar</button>
            <button type="submit" :disabled="procesando" class="px-4 py-1.5 bg-rose-600 text-white rounded-lg font-bold disabled:opacity-50">Finalizar</button>
          </div>
        </form>

        <!-- Historial de certificados -->
        <div>
          <h3 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 mb-2">Certificados de esta entrega</h3>
          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-left">
              <thead>
                <tr class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  <th class="py-2 px-3">Inicio</th>
                  <th class="py-2 px-3">Vencimiento</th>
                  <th class="py-2 px-3">Estado</th>
                  <th class="py-2 px-3">Recordatorio</th>
                  <th class="py-2 px-3 text-right"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(c, i) in entrega.certificados" :key="c.id" :class="i > 0 ? 'text-slate-400' : 'text-slate-700'">
                  <td class="py-2 px-3">{{ formatFecha(c.fecha_inicio) }}</td>
                  <td class="py-2 px-3 font-semibold">
                    {{ formatFecha(c.fecha_vencimiento) }}
                    <span v-if="c.vencimiento_manual" class="text-[9px] uppercase text-slate-400">(manual)</span>
                  </td>
                  <td class="py-2 px-3">
                    <span v-if="i > 0" class="text-[10px] font-bold uppercase">Reemplazado</span>
                    <FirmaDigitalEstadoBadge v-else :estado="esAsignada ? c.estado_certificado : 'NO_VIGENTE'" />
                  </td>
                  <td class="py-2 px-3 space-y-0.5">
                    <div class="flex items-center gap-2">
                      <span>{{ formatFecha(c.fecha_recordatorio) }}</span>
                      <FirmaDigitalEstadoBadge tipo="recordatorio" :estado="c.recordatorio_estado" />
                    </div>
                    <p v-if="c.recordatorio_enviado_at" class="text-[10px] text-slate-500">
                      Enviado {{ formatFecha(c.recordatorio_enviado_at) }} a {{ c.recordatorio_email }}
                    </p>
                    <p v-if="c.recordatorio_ultimo_error && c.recordatorio_estado !== 'ENVIADO'" class="text-[10px] text-rose-600 break-all">
                      {{ c.recordatorio_ultimo_error }}
                    </p>
                  </td>
                  <td class="py-2 px-3 text-right">
                    <button
                      v-if="i === 0 && authStore.isEditor && esAsignada && c.recordatorio_estado !== 'PENDIENTE'"
                      @click="reprogramar(c)"
                      class="text-[11px] font-bold text-sky-600 hover:underline"
                    >
                      Reprogramar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="p-4 border-t border-slate-100 flex justify-between">
        <button
          v-if="authStore.isAdmin && entrega"
          @click="eliminar"
          class="px-4 py-2 text-rose-600 hover:bg-rose-50 rounded-xl text-xs font-bold"
        >
          🗑️ Eliminar registro
        </button>
        <span v-else></span>
        <button @click="$emit('close')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import { useFirmaDigital, ESTADOS_ENTREGA, TIPOS_DISPOSITIVO } from '~/composables/useFirmaDigital'
import { formatFecha, hoyISO } from '~/composables/useFechas'

const props = defineProps({
  entregaId: { type: Number, required: true },
})
const emit = defineEmits(['close', 'changed', 'edit', 'replace'])

const { get, post, put, delete: del } = useApi()
const { toast, confirmDialog } = useToast()
const authStore = useAuthStore()
const { cargarParametros, calcularVencimiento, calcularRecordatorio } = useFirmaDigital()

const entrega = ref(null)
const cargando = ref(false)
const procesando = ref(false)
const panel = ref(null)

const esAsignada = computed(() => entrega.value?.estado === 'ASIGNADA')
const certificadoActual = computed(() => entrega.value?.certificados?.[0] || null)

const fechas = reactive({ fecha_inicio: '', vencimiento_manual: false, fecha_vencimiento: '', recordatorio_manual: false, fecha_recordatorio: '' })
const fin = reactive({ estado: 'DEVUELTA', fecha_fin: hoyISO(), motivo_fin: '', estado_dispositivo: '' })

const vencimientoCalculado = computed(() => calcularVencimiento(fechas.fecha_inicio))
const vencimientoEfectivo = computed(() => fechas.vencimiento_manual ? fechas.fecha_vencimiento : vencimientoCalculado.value)
const recordatorioCalculado = computed(() => calcularRecordatorio(vencimientoEfectivo.value))

const precargarVencimiento = () => {
  if (fechas.vencimiento_manual && !fechas.fecha_vencimiento) fechas.fecha_vencimiento = vencimientoCalculado.value
}
const precargarRecordatorio = () => {
  if (fechas.recordatorio_manual && !fechas.fecha_recordatorio) fechas.fecha_recordatorio = recordatorioCalculado.value
}

const identificacion = (p) => {
  if (p?.numero_documento) return `${p.tipo_documento}: ${p.numero_documento}`
  if (p?.cuil_cuit) return `CUIL: ${p.cuil_cuit}`
  return 'Sin documento registrado'
}

const cargar = async () => {
  cargando.value = true
  try {
    const res = await get(`/firma-digital/entregas/${props.entregaId}`)
    if (res.success) entrega.value = res.data
  } catch (err) {
    toast.error(err, { title: 'Error al cargar la entrega' })
  } finally {
    cargando.value = false
  }
}

const abrirPanel = (tipo) => {
  panel.value = tipo
  const actual = certificadoActual.value
  if (tipo === 'renovar') {
    Object.assign(fechas, {
      fecha_inicio: actual?.fecha_vencimiento || hoyISO(),
      vencimiento_manual: false, fecha_vencimiento: '',
      recordatorio_manual: false, fecha_recordatorio: '',
    })
  } else if (tipo === 'fechas' && actual) {
    Object.assign(fechas, {
      fecha_inicio: actual.fecha_inicio,
      vencimiento_manual: actual.vencimiento_manual,
      fecha_vencimiento: actual.vencimiento_manual ? actual.fecha_vencimiento : '',
      recordatorio_manual: actual.recordatorio_manual,
      fecha_recordatorio: actual.recordatorio_manual ? actual.fecha_recordatorio : '',
    })
  }
}

const trasCambio = async (mensaje) => {
  toast.success(mensaje)
  panel.value = null
  await cargar()
  emit('changed')
}

const guardarFechas = async () => {
  procesando.value = true
  try {
    const payload = {
      fecha_inicio: fechas.fecha_inicio,
      vencimiento_manual: fechas.vencimiento_manual,
      fecha_vencimiento: fechas.vencimiento_manual ? fechas.fecha_vencimiento : null,
      recordatorio_manual: fechas.recordatorio_manual,
      fecha_recordatorio: fechas.recordatorio_manual ? fechas.fecha_recordatorio : null,
    }
    const res = panel.value === 'renovar'
      ? await post(`/firma-digital/entregas/${entrega.value.id}/certificados`, payload)
      : await put(`/firma-digital/certificados/${certificadoActual.value.id}`, payload)
    if (res.success) await trasCambio(panel.value === 'renovar' ? 'Certificado renovado correctamente.' : 'Fechas actualizadas correctamente.')
  } catch (err) {
    toast.error(err)
  } finally {
    procesando.value = false
  }
}

const finalizar = async () => {
  const confirmed = await confirmDialog({
    title: 'Finalizar entrega',
    message: 'La entrega dejará de estar vigente y se cancelarán los recordatorios pendientes. ¿Desea continuar?',
    confirmText: 'Sí, finalizar',
    type: 'warning',
  })
  if (!confirmed) return

  procesando.value = true
  try {
    const res = await post(`/firma-digital/entregas/${entrega.value.id}/finalizar`, { ...fin, estado_dispositivo: fin.estado_dispositivo || null })
    if (res.success) await trasCambio('Entrega finalizada correctamente.')
  } catch (err) {
    toast.error(err)
  } finally {
    procesando.value = false
  }
}

const reprogramar = async (c) => {
  const confirmed = await confirmDialog({
    title: 'Reprogramar recordatorio',
    message: `El recordatorio volverá a quedar pendiente para el ${formatFecha(c.fecha_recordatorio)} (o la próxima ejecución diaria si esa fecha ya pasó). ¿Continuar?`,
    confirmText: 'Reprogramar',
    type: 'info',
  })
  if (!confirmed) return

  try {
    const res = await post(`/firma-digital/certificados/${c.id}/reprogramar-recordatorio`, {})
    if (res.success) await trasCambio('Recordatorio reprogramado.')
  } catch (err) {
    toast.error(err)
  }
}

const eliminar = async () => {
  const confirmed = await confirmDialog({
    title: 'Eliminar registro de entrega',
    message: 'Esta acción solo debe usarse para registros cargados por error. Para devoluciones o reemplazos use "Finalizar" o "Reemplazar". ¿Eliminar?',
    confirmText: 'Sí, eliminar',
    type: 'danger',
  })
  if (!confirmed) return

  try {
    const res = await del(`/firma-digital/entregas/${entrega.value.id}`)
    if (res.success) {
      toast.success('Registro de entrega eliminado.')
      emit('changed')
      emit('close')
    }
  } catch (err) {
    toast.error(err)
  }
}

onMounted(() => {
  cargarParametros()
  cargar()
})
</script>

<style scoped>
.campo {
  @apply w-full p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500;
}
.btn-sec {
  @apply px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg text-[11px] font-bold text-slate-700 transition;
}
</style>
