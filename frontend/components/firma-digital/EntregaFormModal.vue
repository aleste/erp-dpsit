<template>
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div v-dialogo="() => $emit('close')" class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[92vh] flex flex-col">
      <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold">{{ titulo }}</h2>
          <p class="text-xs text-slate-400">Dispositivos de firma digital</p>
        </div>
        <button type="button" aria-label="Cerrar" @click="$emit('close')" class="text-slate-400 hover:text-white">✕</button>
      </div>

      <form @submit.prevent="guardar" class="p-6 space-y-5 text-xs overflow-y-auto">
        <!-- Aviso de reemplazo -->
        <div v-if="reemplaza" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800">
          Se registrará un nuevo dispositivo para <strong>{{ reemplaza.persona?.apellido }}, {{ reemplaza.persona?.nombre }}</strong>.
          La entrega anterior (serie {{ reemplaza.dispositivo?.numero_serie || 's/n' }}) quedará como <strong>REEMPLAZADA</strong> y se cancelarán sus recordatorios pendientes.
        </div>

        <!-- PERSONA -->
        <section class="space-y-2">
          <h3 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">1. Persona titular</h3>

          <div v-if="esEdicion || reemplaza" class="p-3 rounded-xl bg-slate-50 border border-slate-200 font-semibold text-slate-800">
            {{ personaFija?.apellido }}, {{ personaFija?.nombre }}
          </div>

          <template v-else>
            <PersonaSearchSelect
              v-if="!mostrarNuevaPersona"
              v-model="form.persona_id"
              :initial-persona="personaInicial"
              placeholder="Buscar en el padrón por apellido, nombre, CUIL o documento..."
            />
            <button
              type="button"
              @click="mostrarNuevaPersona = !mostrarNuevaPersona"
              class="text-xs font-bold text-sky-600 hover:text-sky-800 hover:underline"
            >
              {{ mostrarNuevaPersona ? '← Buscar en el padrón' : '+ La persona no está en el padrón: registrarla' }}
            </button>

            <div v-if="mostrarNuevaPersona" class="p-4 rounded-xl border border-sky-200 bg-sky-50/40 space-y-3">
              <p class="text-[11px] text-slate-500">
                Solo apellido y nombre son obligatorios. El email es necesario para enviar los recordatorios de vencimiento.
              </p>
              <div class="grid grid-cols-2 gap-3">
                <input v-model="nuevaPersona.apellido" type="text" placeholder="Apellido *" class="campo" />
                <input v-model="nuevaPersona.nombre" type="text" placeholder="Nombre *" class="campo" />
                <select v-model="nuevaPersona.tipo_documento" class="campo">
                  <option value="">Tipo de documento</option>
                  <option v-for="t in TIPOS_DOCUMENTO" :key="t" :value="t">{{ t }}</option>
                </select>
                <input v-model="nuevaPersona.numero_documento" type="text" placeholder="Número de documento" class="campo font-mono" />
                <input v-model="nuevaPersona.email_principal" type="email" placeholder="Email de contacto" class="campo" />
                <input v-model="nuevaPersona.cuil_cuit" type="text" placeholder="CUIL (opcional)" class="campo font-mono" />
              </div>
            </div>
          </template>
        </section>

        <!-- ORGANISMO -->
        <section class="space-y-2">
          <h3 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">2. Organismo (opcional)</h3>
          <div class="flex flex-wrap gap-4 font-semibold text-slate-700">
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input v-model="tipoOrganismo" type="radio" value="ministerio" class="text-sky-600" /> Ministerio del ERP
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input v-model="tipoOrganismo" type="radio" value="externo" class="text-sky-600" /> Organismo externo
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input v-model="tipoOrganismo" type="radio" value="ninguno" class="text-sky-600" /> Sin organismo
            </label>
          </div>
          <select v-if="tipoOrganismo === 'ministerio'" v-model="form.ministerio_id" required class="campo">
            <option value="">Seleccione un ministerio...</option>
            <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.sigla }} - {{ m.nombre }}</option>
          </select>
          <input
            v-if="tipoOrganismo === 'externo'"
            v-model="form.organismo_externo"
            type="text"
            required
            placeholder="Ej: Hospital Privado San Martín"
            class="campo"
          />
          <input v-model="form.cargo_funcion" type="text" placeholder="Cargo o función (opcional). Ej: Autoridad de Registro" class="campo" />
        </section>

        <!-- ENTREGA Y DISPOSITIVO -->
        <section class="space-y-2">
          <h3 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">3. Entrega{{ esEdicion ? '' : ' y dispositivo' }}</h3>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="entrega-form-fecha-entrega" class="block font-bold text-slate-700 mb-1">Fecha de entrega *</label>
              <input id="entrega-form-fecha-entrega" v-model="form.fecha_entrega" type="date" required class="campo" />
            </div>
          </div>

          <template v-if="!esEdicion">
            <div class="flex flex-wrap gap-4 font-semibold text-slate-700 pt-1">
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input v-model="origenDispositivo" type="radio" value="nuevo" class="text-sky-600" /> Dispositivo nuevo
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input v-model="origenDispositivo" type="radio" value="existente" class="text-sky-600" /> Dispositivo disponible en stock
              </label>
            </div>

            <div v-if="origenDispositivo === 'nuevo'" class="grid grid-cols-2 gap-3">
              <select v-model="form.dispositivo.tipo_dispositivo" required class="campo">
                <option v-for="(label, valor) in TIPOS_DISPOSITIVO" :key="valor" :value="valor">{{ label }}</option>
              </select>
              <input v-model="form.dispositivo.numero_serie" type="text" placeholder="Número de serie (si está disponible)" class="campo font-mono" />
              <input v-model="form.dispositivo.marca" type="text" placeholder="Marca" class="campo" />
              <input v-model="form.dispositivo.modelo" type="text" placeholder="Modelo" class="campo" />
            </div>

            <div v-else>
              <select v-model="form.dispositivo_firma_id" required class="campo">
                <option value="">{{ dispositivosDisponibles.length ? 'Seleccione un dispositivo...' : 'No hay dispositivos disponibles' }}</option>
                <option v-for="d in dispositivosDisponibles" :key="d.id" :value="d.id">
                  {{ TIPOS_DISPOSITIVO[d.tipo_dispositivo] || d.tipo_dispositivo }} · {{ d.marca || 's/marca' }} {{ d.modelo || '' }} · Serie {{ d.numero_serie || 's/n' }}
                </option>
              </select>
            </div>
          </template>
        </section>

        <!-- CERTIFICADO -->
        <section v-if="!esEdicion" class="space-y-3">
          <h3 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">4. Certificado digital</h3>

          <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
            <input v-model="inicioDistinto" type="checkbox" class="w-4 h-4 text-sky-600 rounded border-slate-300" />
            El certificado comienza en una fecha distinta a la entrega
          </label>
          <div v-if="inicioDistinto" class="grid grid-cols-2 gap-3">
            <div>
              <label for="entrega-cert-fecha-inicio" class="block font-bold text-slate-700 mb-1">Inicio del certificado *</label>
              <input id="entrega-cert-fecha-inicio" v-model="cert.fecha_inicio" type="date" required class="campo" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
              <p class="font-bold text-slate-700">Vencimiento</p>
              <p v-if="!cert.vencimiento_manual" class="text-sm font-extrabold text-slate-900">
                {{ formatFecha(vencimientoCalculado) }}
                <span class="block text-[10px] font-semibold text-slate-400">Calculado: inicio + {{ parametros.vigencia_meses }} meses</span>
              </p>
              <input v-else v-model="cert.fecha_vencimiento" type="date" required class="campo" />
              <label class="inline-flex items-center gap-2 cursor-pointer text-[11px] text-slate-600">
                <input v-model="cert.vencimiento_manual" type="checkbox" class="w-3.5 h-3.5 text-sky-600 rounded" @change="alCambiarManualVencimiento" />
                Indicar fecha manualmente
              </label>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
              <p class="font-bold text-slate-700">Recordatorio por email</p>
              <p v-if="!cert.recordatorio_manual" class="text-sm font-extrabold text-slate-900">
                {{ formatFecha(recordatorioCalculado) }}
                <span class="block text-[10px] font-semibold text-slate-400">{{ parametros.recordatorio_dias_anticipacion }} días antes del vencimiento</span>
              </p>
              <input v-else v-model="cert.fecha_recordatorio" type="date" required class="campo" />
              <label class="inline-flex items-center gap-2 cursor-pointer text-[11px] text-slate-600">
                <input v-model="cert.recordatorio_manual" type="checkbox" class="w-3.5 h-3.5 text-sky-600 rounded" @change="alCambiarManualRecordatorio" />
                Indicar fecha manualmente
              </label>
            </div>
          </div>
        </section>

        <div>
          <label for="entrega-form-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones</label>
          <textarea id="entrega-form-observaciones" v-model="form.observaciones" rows="2" class="campo"></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" @click="$emit('close')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
          >
            {{ guardando ? 'Guardando...' : (esEdicion ? 'Guardar Cambios' : 'Registrar Entrega') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import { useFirmaDigital, TIPOS_DISPOSITIVO, TIPOS_DOCUMENTO } from '~/composables/useFirmaDigital'
import { formatFecha, hoyISO } from '~/composables/useFechas'

const props = defineProps({
  // Entrega a editar (solo datos de la entrega)
  entrega: { type: Object, default: null },
  // Entrega a reemplazar por un dispositivo nuevo
  reemplaza: { type: Object, default: null },
  ministerios: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved'])

const { get, post, put } = useApi()
const { toast } = useToast()
const { parametros, cargarParametros, calcularVencimiento, calcularRecordatorio } = useFirmaDigital()

const esEdicion = computed(() => !!props.entrega)
const personaFija = computed(() => props.entrega?.persona || props.reemplaza?.persona)
const titulo = computed(() => esEdicion.value ? 'Editar Entrega' : (props.reemplaza ? 'Reemplazar Dispositivo' : 'Registrar Entrega de Token'))

const guardando = ref(false)
const mostrarNuevaPersona = ref(false)
const personaInicial = ref(null)
const dispositivosDisponibles = ref([])
const origenDispositivo = ref('nuevo')
const inicioDistinto = ref(false)

const origen = props.entrega || props.reemplaza
const tipoOrganismo = ref(origen?.ministerio_id ? 'ministerio' : (origen?.organismo_externo ? 'externo' : 'ninguno'))

const form = reactive({
  persona_id: personaFija.value?.id || null,
  ministerio_id: origen?.ministerio_id || '',
  organismo_externo: origen?.organismo_externo || '',
  cargo_funcion: origen?.cargo_funcion || '',
  fecha_entrega: props.entrega?.fecha_entrega || hoyISO(),
  observaciones: props.entrega?.observaciones || '',
  dispositivo_firma_id: '',
  dispositivo: { tipo_dispositivo: 'TOKEN_USB', marca: '', modelo: '', numero_serie: '' },
})

const nuevaPersona = reactive({ apellido: '', nombre: '', tipo_documento: '', numero_documento: '', email_principal: '', cuil_cuit: '' })

const cert = reactive({
  fecha_inicio: '',
  vencimiento_manual: false,
  fecha_vencimiento: '',
  recordatorio_manual: false,
  fecha_recordatorio: '',
})

const inicioEfectivo = computed(() => (inicioDistinto.value && cert.fecha_inicio) ? cert.fecha_inicio : form.fecha_entrega)
const vencimientoCalculado = computed(() => calcularVencimiento(inicioEfectivo.value))
const vencimientoEfectivo = computed(() => cert.vencimiento_manual ? cert.fecha_vencimiento : vencimientoCalculado.value)
const recordatorioCalculado = computed(() => calcularRecordatorio(vencimientoEfectivo.value))

// Al pasar a manual se precarga la fecha calculada, para editar a partir de ella
const alCambiarManualVencimiento = () => {
  if (cert.vencimiento_manual && !cert.fecha_vencimiento) cert.fecha_vencimiento = vencimientoCalculado.value
}
const alCambiarManualRecordatorio = () => {
  if (cert.recordatorio_manual && !cert.fecha_recordatorio) cert.fecha_recordatorio = recordatorioCalculado.value
}

watch(tipoOrganismo, (tipo) => {
  if (tipo !== 'ministerio') form.ministerio_id = ''
  if (tipo !== 'externo') form.organismo_externo = ''
})

const cargarDispositivos = async () => {
  try {
    const res = await get('/firma-digital/dispositivos', { solo_disponibles: 1, per_page: 100 })
    if (res.success) dispositivosDisponibles.value = res.data.data
  } catch (err) {
    console.error('Error cargando dispositivos:', err)
  }
}

const registrarPersonaNueva = async () => {
  if (!nuevaPersona.apellido || !nuevaPersona.nombre) {
    toast.warning('Apellido y nombre de la persona son obligatorios.')
    return null
  }
  const res = await post('/personas', nuevaPersona)
  return res.success ? res.data : null
}

const guardar = async () => {
  guardando.value = true
  try {
    const organismo = {
      ministerio_id: tipoOrganismo.value === 'ministerio' ? form.ministerio_id : null,
      organismo_externo: tipoOrganismo.value === 'externo' ? form.organismo_externo : null,
    }

    if (esEdicion.value) {
      const res = await put(`/firma-digital/entregas/${props.entrega.id}`, {
        ...organismo,
        cargo_funcion: form.cargo_funcion,
        fecha_entrega: form.fecha_entrega,
        observaciones: form.observaciones,
      })
      if (res.success) {
        toast.success('Entrega actualizada correctamente.')
        emit('saved', res.data)
      }
      return
    }

    let personaId = form.persona_id
    if (!props.reemplaza && mostrarNuevaPersona.value) {
      const persona = await registrarPersonaNueva()
      if (!persona) return
      personaId = persona.id
      // Si luego falla la entrega, la persona ya quedó registrada: se la deja seleccionada
      personaInicial.value = persona
      form.persona_id = persona.id
      mostrarNuevaPersona.value = false
    }

    if (!personaId) {
      toast.warning('Seleccione o registre la persona titular del token.')
      return
    }

    const payload = {
      persona_id: personaId,
      ...organismo,
      cargo_funcion: form.cargo_funcion,
      fecha_entrega: form.fecha_entrega,
      observaciones: form.observaciones,
      reemplaza_entrega_id: props.reemplaza?.id || null,
      certificado: {
        fecha_inicio: inicioEfectivo.value,
        vencimiento_manual: cert.vencimiento_manual,
        fecha_vencimiento: cert.vencimiento_manual ? cert.fecha_vencimiento : null,
        recordatorio_manual: cert.recordatorio_manual,
        fecha_recordatorio: cert.recordatorio_manual ? cert.fecha_recordatorio : null,
      },
    }

    if (origenDispositivo.value === 'existente') {
      payload.dispositivo_firma_id = form.dispositivo_firma_id
    } else {
      payload.dispositivo = form.dispositivo
    }

    const res = await post('/firma-digital/entregas', payload)
    if (res.success) {
      toast.success(props.reemplaza ? 'Dispositivo reemplazado correctamente.' : 'Entrega de token registrada correctamente.')
      emit('saved', res.data)
    }
  } catch (err) {
    toast.error(err)
  } finally {
    guardando.value = false
  }
}

onMounted(() => {
  cargarParametros()
  if (!esEdicion.value) cargarDispositivos()
})
</script>

<style scoped>
.campo {
  @apply w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500;
}
</style>
