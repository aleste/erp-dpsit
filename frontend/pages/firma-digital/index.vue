<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Dispositivos de Firma Digital</h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Entrega de tokens, vigencia de certificados digitales y recordatorios automáticos de vencimiento.
        </p>
      </div>
      <button
        v-if="authStore.isEditor"
        @click="abrirAlta()"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-sm font-bold shadow-md shadow-sky-600/20 transition"
      >
        <span>+</span> Registrar Entrega
      </button>
    </div>

    <!-- Indicadores (clic = filtro rápido) -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
      <button
        v-for="card in tarjetas"
        :key="card.key"
        @click="card.accion()"
        class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm text-left hover:border-sky-300 transition"
      >
        <p class="text-xs font-bold uppercase tracking-wider" :class="card.color">{{ card.label }}</p>
        <h3 class="text-2xl font-extrabold mt-0.5" :class="card.color">{{ card.valor }}</h3>
      </button>
    </div>

    <!-- Pestañas -->
    <div class="flex border-b border-slate-200 space-x-6">
      <button
        @click="cambiarTab('entregas')"
        class="pb-3 text-sm font-extrabold border-b-2 transition flex items-center gap-2"
        :class="activeTab === 'entregas' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        <span>🔑</span> Entregas y Certificados
      </button>
      <button
        @click="cambiarTab('recordatorios')"
        class="pb-3 text-sm font-extrabold border-b-2 transition flex items-center gap-2"
        :class="activeTab === 'recordatorios' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        <span>📬</span> Recordatorios de Vencimiento
      </button>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="lg:col-span-2">
          <label for="firma-filtros-q" class="filtro-label">Buscar</label>
          <input id="firma-filtros-q"
            v-model="filtros.q"
            @input="buscarConDemora"
            type="text"
            :placeholder="activeTab === 'entregas' ? 'Persona, CUIL, documento o N.º de serie...' : 'Apellido, nombre o documento...'"
            class="filtro"
          />
        </div>

        <div>
          <label for="firma-filtros-organismo" class="filtro-label">Organismo</label>
          <select id="firma-filtros-organismo" v-model="filtros.organismo" @change="recargar" class="filtro">
            <option value="">Todos</option>
            <option v-if="activeTab === 'entregas'" value="sin_organismo">Sin ministerio del ERP</option>
            <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.sigla }} - {{ m.nombre }}</option>
          </select>
        </div>

        <div>
          <label for="firma-filtros-estado-certificado" class="filtro-label">Estado del certificado</label>
          <select id="firma-filtros-estado-certificado" v-model="filtros.estado_certificado" @change="recargar" class="filtro">
            <option value="">Todos</option>
            <option v-for="(info, valor) in ESTADOS_CERTIFICADO" :key="valor" :value="valor">{{ info.label }}</option>
          </select>
        </div>

        <div>
          <label for="firma-filtros-desde" class="filtro-label">{{ activeTab === 'entregas' ? 'Vence desde' : 'Recordatorio desde' }}</label>
          <input id="firma-filtros-desde" v-model="filtros.desde" @change="recargar" type="date" class="filtro" />
        </div>
        <div>
          <label for="firma-filtros-hasta" class="filtro-label">{{ activeTab === 'entregas' ? 'Vence hasta' : 'Recordatorio hasta' }}</label>
          <input id="firma-filtros-hasta" v-model="filtros.hasta" @change="recargar" type="date" class="filtro" />
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 text-sm">
        <div class="flex flex-wrap items-center gap-4">
          <label v-if="activeTab === 'entregas'" class="inline-flex items-center gap-2 font-semibold text-slate-700">
            Entrega:
            <select v-model="filtros.estado_entrega" @change="recargar" class="filtro !w-auto">
              <option value="">Todas</option>
              <option v-for="(label, valor) in ESTADOS_ENTREGA" :key="valor" :value="valor">{{ label }}</option>
            </select>
          </label>
          <label class="inline-flex items-center gap-2 font-semibold text-slate-700">
            Recordatorio:
            <select v-model="filtros.recordatorio_estado" @change="recargar" class="filtro !w-auto">
              <option value="">Todos</option>
              <option v-for="(info, valor) in ESTADOS_RECORDATORIO" :key="valor" :value="valor">{{ info.label }}</option>
            </select>
          </label>
        </div>
        <button v-if="hayFiltros" @click="limpiarFiltros" class="text-sm font-bold text-sky-600 hover:text-sky-800 hover:underline">
          Limpiar Filtros
        </button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
      <div v-if="cargando" class="p-12 text-center text-sm text-slate-500">Cargando...</div>
      <div v-else-if="filas.length === 0" class="p-12 text-center text-sm text-slate-500">
        No se encontraron registros con los criterios especificados.
      </div>

      <!-- Pestaña: entregas -->
      <div v-else-if="activeTab === 'entregas'" class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-xs">
              <th class="py-3.5 px-4">Titular</th>
              <th class="py-3.5 px-4">Organismo</th>
              <th class="py-3.5 px-4">Dispositivo</th>
              <th class="py-3.5 px-4">Entrega</th>
              <th class="py-3.5 px-4">Vencimiento</th>
              <th class="py-3.5 px-4">Recordatorio</th>
              <th class="py-3.5 px-4 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-for="e in filas" :key="e.id" class="hover:bg-slate-50/80 transition">
              <td class="py-3.5 px-4">
                <button @click="detalleId = e.id" class="font-extrabold text-slate-900 hover:text-sky-600 text-left">
                  {{ e.persona?.apellido }}, {{ e.persona?.nombre }}
                </button>
                <p class="text-xs text-slate-500">{{ e.persona?.email_principal || e.persona?.email_alternativo || 'Sin email' }}</p>
              </td>
              <td class="py-3.5 px-4">
                <span v-if="e.ministerio">{{ e.ministerio.sigla || e.ministerio.nombre }}</span>
                <span v-else-if="e.organismo_externo" class="text-slate-600">{{ e.organismo_externo }} <span class="text-xs text-slate-500">(externo)</span></span>
                <span v-else class="text-slate-500">—</span>
                <p v-if="e.cargo_funcion" class="text-xs text-slate-500">{{ e.cargo_funcion }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p>{{ e.dispositivo?.marca || TIPOS_DISPOSITIVO[e.dispositivo?.tipo_dispositivo] }} {{ e.dispositivo?.modelo || '' }}</p>
                <p class="text-xs font-mono text-slate-500">{{ e.dispositivo?.numero_serie || 's/n' }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p>{{ formatFecha(e.fecha_entrega) }}</p>
                <p class="text-xs font-bold uppercase text-slate-500">{{ ESTADOS_ENTREGA[e.estado] }}</p>
              </td>
              <td class="py-3.5 px-4 space-y-1">
                <p class="font-semibold">{{ formatFecha(e.certificado_actual?.fecha_vencimiento) }}</p>
                <FirmaDigitalEstadoBadge :estado="e.estado === 'ASIGNADA' ? e.certificado_actual?.estado_certificado : 'NO_VIGENTE'" />
              </td>
              <td class="py-3.5 px-4 space-y-1">
                <p>{{ formatFecha(e.certificado_actual?.fecha_recordatorio) }}</p>
                <FirmaDigitalEstadoBadge tipo="recordatorio" :estado="e.certificado_actual?.recordatorio_estado" />
              </td>
              <td class="py-3.5 px-4 text-right">
                <button @click="detalleId = e.id" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700 whitespace-nowrap">
                  🔍 Ver detalle
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pestaña: recordatorios -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-xs">
              <th class="py-3.5 px-4">Titular</th>
              <th class="py-3.5 px-4">Destinatario</th>
              <th class="py-3.5 px-4">Vencimiento</th>
              <th class="py-3.5 px-4">Fecha recordatorio</th>
              <th class="py-3.5 px-4">Estado</th>
              <th class="py-3.5 px-4">Resultado</th>
              <th class="py-3.5 px-4 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-for="c in filas" :key="c.id" class="hover:bg-slate-50/80 transition">
              <td class="py-3.5 px-4 font-extrabold text-slate-900">
                {{ c.entrega?.persona?.apellido }}, {{ c.entrega?.persona?.nombre }}
                <p class="text-xs font-normal text-slate-500">{{ c.entrega?.ministerio?.sigla || c.entrega?.organismo_externo || '' }}</p>
              </td>
              <td class="py-3.5 px-4 break-all">
                {{ c.recordatorio_email || c.entrega?.persona?.email_principal || c.entrega?.persona?.email_alternativo || 'Sin email' }}
              </td>
              <td class="py-3.5 px-4 space-y-1">
                <p class="font-semibold">{{ formatFecha(c.fecha_vencimiento) }}</p>
                <FirmaDigitalEstadoBadge :estado="c.entrega?.estado === 'ASIGNADA' ? c.estado_certificado : 'NO_VIGENTE'" />
              </td>
              <td class="py-3.5 px-4">
                {{ formatFecha(c.fecha_recordatorio) }}
                <span v-if="c.recordatorio_manual" class="text-xs uppercase text-slate-500">(manual)</span>
              </td>
              <td class="py-3.5 px-4"><FirmaDigitalEstadoBadge tipo="recordatorio" :estado="c.recordatorio_estado" /></td>
              <td class="py-3.5 px-4 text-xs">
                <span v-if="c.recordatorio_enviado_at" class="text-emerald-700">Enviado el {{ formatFecha(c.recordatorio_enviado_at) }}</span>
                <span v-else-if="c.recordatorio_ultimo_error" class="text-rose-600 break-all">{{ c.recordatorio_ultimo_error }}</span>
                <span v-else class="text-slate-500">—</span>
                <p v-if="c.recordatorio_intentos > 0" class="text-xs text-slate-500">Intentos: {{ c.recordatorio_intentos }}</p>
              </td>
              <td class="py-3.5 px-4 text-right">
                <button @click="detalleId = c.entrega_token_id" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700 whitespace-nowrap">
                  🔍 Ver entrega
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="paginacion.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
        <span>{{ paginacion.from }}–{{ paginacion.to }} de {{ paginacion.total }}</span>
        <div class="flex gap-2">
          <button :disabled="paginacion.current_page <= 1" @click="irAPagina(paginacion.current_page - 1)" class="px-3 py-1.5 bg-slate-100 rounded-lg font-bold disabled:opacity-40">‹ Anterior</button>
          <button :disabled="paginacion.current_page >= paginacion.last_page" @click="irAPagina(paginacion.current_page + 1)" class="px-3 py-1.5 bg-slate-100 rounded-lg font-bold disabled:opacity-40">Siguiente ›</button>
        </div>
      </div>
    </div>

    <p class="text-xs text-slate-500">
      Los recordatorios se envían automáticamente todos los días a las {{ parametros.recordatorio_hora }},
      {{ parametros.recordatorio_dias_anticipacion }} días antes del vencimiento (valor configurable). Si la persona no tiene un email válido,
      el recordatorio queda como «Sin email» y puede reprogramarse luego de completar el dato en el padrón.
    </p>

    <!-- Modales -->
    <FirmaDigitalEntregaFormModal
      v-if="formulario.visible"
      :entrega="formulario.entrega"
      :reemplaza="formulario.reemplaza"
      :ministerios="ministerios"
      @close="formulario.visible = false"
      @saved="alGuardar"
    />

    <FirmaDigitalEntregaDetalleModal
      v-if="detalleId"
      :key="detalleId"
      :entrega-id="detalleId"
      @close="detalleId = null"
      @changed="recargar"
      @edit="abrirEdicion"
      @replace="abrirReemplazo"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useFirmaDigital, ESTADOS_CERTIFICADO, ESTADOS_RECORDATORIO, ESTADOS_ENTREGA, TIPOS_DISPOSITIVO } from '~/composables/useFirmaDigital'
import { formatFecha } from '~/composables/useFechas'

const { get } = useApi()
const authStore = useAuthStore()
const { toast } = useToast()
const { parametros, cargarParametros } = useFirmaDigital()

const activeTab = ref('entregas')
const filas = ref([])
const ministerios = ref([])
const cargando = ref(false)
const detalleId = ref(null)

const stats = reactive({
  asignados: 0,
  proximos_a_vencer: 0,
  vencidos: 0,
  recordatorios: { pendientes: 0, enviados: 0, con_error: 0, sin_email: 0 },
})

const paginacion = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 25 })

const filtrosVacios = () => ({
  q: '', organismo: '', estado_certificado: '', estado_entrega: '', recordatorio_estado: '', desde: '', hasta: '',
})
const filtros = reactive(filtrosVacios())

const formulario = reactive({ visible: false, entrega: null, reemplaza: null })

const hayFiltros = computed(() => Object.values(filtros).some(v => v !== ''))

// Tarjetas de resumen: cada una aplica el filtro correspondiente
const filtrarPor = (tab, cambios) => {
  activeTab.value = tab
  Object.assign(filtros, filtrosVacios(), cambios)
  recargar()
}
const tarjetas = computed(() => [
  { key: 'asignados', label: 'Tokens asignados', valor: stats.asignados, color: 'text-slate-800', accion: () => filtrarPor('entregas', { estado_entrega: 'ASIGNADA' }) },
  { key: 'proximos', label: 'Próximos a vencer', valor: stats.proximos_a_vencer, color: 'text-amber-600', accion: () => filtrarPor('entregas', { estado_certificado: 'PROXIMO_A_VENCER' }) },
  { key: 'vencidos', label: 'Vencidos', valor: stats.vencidos, color: 'text-rose-600', accion: () => filtrarPor('entregas', { estado_certificado: 'VENCIDO' }) },
  { key: 'pendientes', label: 'Recordatorios pendientes', valor: stats.recordatorios.pendientes, color: 'text-sky-600', accion: () => filtrarPor('recordatorios', { recordatorio_estado: 'PENDIENTE' }) },
  { key: 'enviados', label: 'Recordatorios enviados', valor: stats.recordatorios.enviados, color: 'text-emerald-600', accion: () => filtrarPor('recordatorios', { recordatorio_estado: 'ENVIADO' }) },
  { key: 'problemas', label: 'Con error / sin email', valor: stats.recordatorios.con_error + stats.recordatorios.sin_email, color: 'text-orange-600', accion: () => filtrarPor('recordatorios', { recordatorio_estado: stats.recordatorios.con_error ? 'ERROR' : 'SIN_EMAIL' }) },
])

const parametrosConsulta = () => {
  const base = {
    q: filtros.q,
    estado_certificado: filtros.estado_certificado,
    recordatorio_estado: filtros.recordatorio_estado,
    page: paginacion.current_page,
    per_page: paginacion.per_page,
  }
  if (filtros.organismo === 'sin_organismo') base.sin_organismo = 1
  else if (filtros.organismo) base.ministerio_id = filtros.organismo

  if (activeTab.value === 'entregas') {
    return { ...base, estado_entrega: filtros.estado_entrega, vencimiento_desde: filtros.desde, vencimiento_hasta: filtros.hasta }
  }
  return { ...base, recordatorio_desde: filtros.desde, recordatorio_hasta: filtros.hasta }
}

const cargar = async () => {
  cargando.value = true
  try {
    const url = activeTab.value === 'entregas' ? '/firma-digital/entregas' : '/firma-digital/certificados'
    const res = await get(url, parametrosConsulta())
    if (res.success) {
      filas.value = res.data.data
      Object.assign(paginacion, {
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        total: res.data.total,
        from: res.data.from,
        to: res.data.to,
      })
      if (res.stats) Object.assign(stats, res.stats)
    }
  } catch (err) {
    toast.error(err, { title: 'Error al cargar el módulo de firma digital' })
  } finally {
    cargando.value = false
  }
}

// Las estadísticas vienen con el listado de entregas; en la pestaña de recordatorios se refrescan aparte
const cargarStats = async () => {
  try {
    const res = await get('/firma-digital/entregas', { per_page: 1 })
    if (res.success && res.stats) Object.assign(stats, res.stats)
  } catch (err) {
    console.error('Error cargando estadísticas:', err)
  }
}

const recargar = () => {
  paginacion.current_page = 1
  cargar()
  if (activeTab.value !== 'entregas') cargarStats()
}

let temporizador = null
const buscarConDemora = () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(recargar, 350)
}

const irAPagina = (pagina) => {
  paginacion.current_page = pagina
  cargar()
}

const cambiarTab = (tab) => {
  if (activeTab.value === tab) return
  activeTab.value = tab
  Object.assign(filtros, filtrosVacios())
  recargar()
}

const limpiarFiltros = () => {
  Object.assign(filtros, filtrosVacios())
  recargar()
}

const abrirAlta = () => Object.assign(formulario, { visible: true, entrega: null, reemplaza: null })
const abrirEdicion = (entrega) => {
  detalleId.value = null
  Object.assign(formulario, { visible: true, entrega, reemplaza: null })
}
const abrirReemplazo = (entrega) => {
  detalleId.value = null
  Object.assign(formulario, { visible: true, entrega: null, reemplaza: entrega })
}

const alGuardar = (entrega) => {
  formulario.visible = false
  recargar()
  if (entrega?.id) detalleId.value = entrega.id
}

onMounted(async () => {
  cargarParametros()
  cargar()
  try {
    const res = await get('/ministerios')
    if (res.success) ministerios.value = res.data
  } catch (err) {
    console.error('Error cargando ministerios:', err)
  }
})
</script>

<style scoped>
.filtro {
  @apply w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition;
}
.filtro-label {
  @apply block text-sm font-bold text-slate-500 mb-1;
}
</style>
