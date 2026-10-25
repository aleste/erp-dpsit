<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Seguimiento de Expedientes</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Expedientes que ingresan al área: situación actual, responsables, intervenciones y salidas.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink
          v-if="authStore.isAdmin"
          to="/expedientes/catalogos"
          class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl text-xs font-bold text-slate-700"
        >
          ⚙️ Catálogos
        </NuxtLink>
        <button
          v-if="puedeOperar()"
          @click="mostrarIngreso = true"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition"
        >
          <span>+</span> Registrar Ingreso
        </button>
      </div>
    </div>

    <!-- Indicadores (clic = filtro rápido) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
      <button
        v-for="card in tarjetas"
        :key="card.key"
        @click="card.accion()"
        class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm text-left hover:border-sky-300 transition"
      >
        <p class="text-[10px] font-bold uppercase tracking-wider" :class="card.color">{{ card.label }}</p>
        <h3 class="text-2xl font-extrabold mt-0.5" :class="card.color">{{ card.valor }}</h3>
      </button>
    </div>

    <!-- En el área por estado -->
    <div v-if="tablero.por_estado?.length" class="flex flex-wrap items-center gap-2 text-xs">
      <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">En el área por estado:</span>
      <button
        v-for="e in tablero.por_estado"
        :key="e.id"
        @click="filtrarPor('listado', { situacion: 'EN_AREA', estado_id: e.id })"
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[11px] font-bold"
        :class="claseColor(e.color)"
      >
        {{ e.nombre }} <span class="opacity-70">{{ e.cantidad }}</span>
      </button>
    </div>

    <!-- Pestañas -->
    <div class="flex border-b border-slate-200 space-x-6">
      <button v-for="t in pestanas" :key="t.id" @click="cambiarTab(t.id)" class="pb-3 text-xs font-extrabold border-b-2 transition"
              :class="activeTab === t.id ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'">
        {{ t.label }}
      </button>
    </div>

    <!-- PESTAÑA: PRIORIDADES (reemplaza la hoja personal por antigüedad) -->
    <div v-if="activeTab === 'prioridad'" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 text-xs text-slate-500">
        Expedientes en el área ordenados por prioridad y antigüedad. Se resaltan los que superan {{ tablero.umbral_dias_alerta }} días.
      </div>
      <div v-if="!tablero.prioridad?.length" class="p-12 text-center text-xs text-slate-400">No hay expedientes en el área.</div>
      <TablaIntervenciones v-else :filas="tablero.prioridad" :umbral="tablero.umbral_dias_alerta" @abrir="abrirFicha" />
    </div>

    <!-- PESTAÑA: LISTADO -->
    <template v-if="activeTab === 'listado'">
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
          <div class="lg:col-span-2">
            <label for="expedientes-filtros-q" class="filtro-label">Buscar</label>
            <input id="expedientes-filtros-q" v-model="filtros.q" @input="buscarConDemora" type="text" placeholder="Nº de expediente, carátula, motivo o GEDO..." class="filtro" />
          </div>
          <div>
            <label for="expedientes-filtros-situacion" class="filtro-label">Situación</label>
            <select id="expedientes-filtros-situacion" v-model="filtros.situacion" @change="recargar" class="filtro">
              <option value="EN_AREA">En el área</option>
              <option value="EGRESADAS">Egresados</option>
              <option value="TODAS">Todos los registros</option>
            </select>
          </div>
          <div>
            <label for="expedientes-filtros-estado-id" class="filtro-label">Estado</label>
            <select id="expedientes-filtros-estado-id" v-model="filtros.estado_id" @change="recargar" class="filtro">
              <option value="">Todos</option>
              <option v-for="e in opciones.estados" :key="e.id" :value="e.id">{{ e.nombre }}</option>
            </select>
          </div>
          <div>
            <label for="expedientes-filtros-responsable-id" class="filtro-label">Responsable</label>
            <select id="expedientes-filtros-responsable-id" v-model="filtros.responsable_id" @change="recargar" class="filtro">
              <option value="">Todos</option>
              <option value="sin">Sin responsable</option>
              <option v-for="u in opciones.integrantes" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label for="expedientes-filtros-ministerio-id" class="filtro-label">Organismo</label>
            <select id="expedientes-filtros-ministerio-id" v-model="filtros.ministerio_id" @change="recargar" class="filtro">
              <option value="">Todos</option>
              <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.nombre }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
          <div>
            <label for="expedientes-filtros-tipo-requerimiento-id" class="filtro-label">Requerimiento</label>
            <select id="expedientes-filtros-tipo-requerimiento-id" v-model="filtros.tipo_requerimiento_id" @change="recargar" class="filtro">
              <option value="">Todos</option>
              <option v-for="t in opciones.tipos_requerimiento" :key="t.id" :value="t.id">{{ t.nombre }}</option>
            </select>
          </div>
          <div>
            <label for="expedientes-filtros-ingreso-desde" class="filtro-label">Ingreso desde</label>
            <input id="expedientes-filtros-ingreso-desde" v-model="filtros.ingreso_desde" @change="recargar" type="date" class="filtro" />
          </div>
          <div>
            <label for="expedientes-filtros-ingreso-hasta" class="filtro-label">Ingreso hasta</label>
            <input id="expedientes-filtros-ingreso-hasta" v-model="filtros.ingreso_hasta" @change="recargar" type="date" class="filtro" />
          </div>
          <div>
            <label for="expedientes-filtros-egreso-desde" class="filtro-label">Egreso desde</label>
            <input id="expedientes-filtros-egreso-desde" v-model="filtros.egreso_desde" @change="recargar" type="date" class="filtro" />
          </div>
          <div>
            <label for="expedientes-filtros-egreso-hasta" class="filtro-label">Egreso hasta</label>
            <input id="expedientes-filtros-egreso-hasta" v-model="filtros.egreso_hasta" @change="recargar" type="date" class="filtro" />
          </div>
          <div>
            <label for="expedientes-filtros-orden" class="filtro-label">Orden</label>
            <select id="expedientes-filtros-orden" v-model="filtros.orden" @change="recargar" class="filtro">
              <option value="antiguedad">Prioridad y antigüedad</option>
              <option value="ingreso_desc">Ingreso más reciente</option>
              <option value="egreso_desc">Egreso más reciente</option>
            </select>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs">
          <div class="flex flex-wrap items-center gap-4 font-semibold text-slate-700">
            <label class="inline-flex items-center gap-2 cursor-pointer"><input v-model="filtros.mis" @change="recargar" type="checkbox" class="w-4 h-4 rounded text-sky-600" /> Mis expedientes</label>
            <label class="inline-flex items-center gap-2 cursor-pointer"><input v-model="filtros.accion_vencida" @change="recargar" type="checkbox" class="w-4 h-4 rounded text-sky-600" /> Próxima acción vencida</label>
            <label class="inline-flex items-center gap-2 cursor-pointer"><input v-model="filtros.plazo_vencido" @change="recargar" type="checkbox" class="w-4 h-4 rounded text-sky-600" /> Plazo vencido</label>
            <label class="inline-flex items-center gap-2 cursor-pointer"><input v-model="filtros.demorados" @change="recargar" type="checkbox" class="w-4 h-4 rounded text-sky-600" /> Más de {{ tablero.umbral_dias_alerta }} días en el área</label>
          </div>
          <div class="flex items-center gap-3">
            <button v-if="hayFiltros" @click="limpiarFiltros" class="font-bold text-sky-600 hover:underline">Limpiar Filtros</button>
            <button @click="exportar" :disabled="exportando" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg font-bold text-slate-700 disabled:opacity-50">
              {{ exportando ? 'Exportando...' : '⬇ Exportar CSV' }}
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div v-if="cargando" class="p-12 text-center text-xs text-slate-400">Cargando...</div>
        <div v-else-if="filas.length === 0" class="p-12 text-center text-xs text-slate-400">No se encontraron expedientes con los criterios especificados.</div>
        <TablaIntervenciones v-else :filas="filas" :umbral="tablero.umbral_dias_alerta" mostrar-egreso @abrir="abrirFicha" />

        <div v-if="paginacion.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
          <span>{{ paginacion.from }}–{{ paginacion.to }} de {{ paginacion.total }}</span>
          <div class="flex gap-2">
            <button :disabled="paginacion.current_page <= 1" @click="irAPagina(paginacion.current_page - 1)" class="px-3 py-1.5 bg-slate-100 rounded-lg font-bold disabled:opacity-40">‹ Anterior</button>
            <button :disabled="paginacion.current_page >= paginacion.last_page" @click="irAPagina(paginacion.current_page + 1)" class="px-3 py-1.5 bg-slate-100 rounded-lg font-bold disabled:opacity-40">Siguiente ›</button>
          </div>
        </div>
      </div>
    </template>

    <!-- PESTAÑA: ÚLTIMOS MOVIMIENTOS -->
    <div v-if="activeTab === 'movimientos'" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm divide-y divide-slate-100">
      <div v-if="!tablero.ultimos_movimientos?.length" class="p-12 text-center text-xs text-slate-400">Sin movimientos registrados.</div>
      <button
        v-for="m in tablero.ultimos_movimientos"
        :key="m.id"
        @click="abrirFicha(m.intervencion?.expediente_id)"
        class="w-full text-left p-4 hover:bg-slate-50 text-xs flex flex-col sm:flex-row sm:items-center gap-2"
      >
        <span class="font-mono font-bold text-slate-900 sm:w-72 truncate">{{ m.intervencion?.expediente?.numero }}</span>
        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-[10px] font-bold uppercase text-slate-600 w-fit">{{ TIPOS_MOVIMIENTO[m.tipo] || m.tipo }}</span>
        <span class="flex-1 text-slate-600 truncate">{{ m.descripcion }}</span>
        <span class="text-slate-400 whitespace-nowrap">{{ formatFecha(m.fecha) }} · {{ m.usuario?.name || 'Importado' }}</span>
      </button>
    </div>

    <ExpedientesIngresoModal v-if="mostrarIngreso" :ministerios="ministerios" @close="mostrarIngreso = false" @saved="alIngresar" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useExpedientes, claseColor, TIPOS_MOVIMIENTO } from '~/composables/useExpedientes'
import { formatFecha } from '~/composables/useFechas'
import TablaIntervenciones from '~/components/expedientes/TablaIntervenciones.vue'

const { get } = useApi()
const authStore = useAuthStore()
const config = useRuntimeConfig()
const { toast } = useToast()
const { opciones, cargarOpciones, puedeOperar } = useExpedientes()

const pestanas = [
  { id: 'prioridad', label: '⏱ Prioridades del área' },
  { id: 'listado', label: '📂 Expedientes' },
  { id: 'movimientos', label: '🕑 Últimos movimientos' },
]
const activeTab = ref('prioridad')
const tablero = ref({ totales: {}, por_estado: [], prioridad: [], ultimos_movimientos: [], umbral_dias_alerta: 30 })
const filas = ref([])
const ministerios = ref([])
const cargando = ref(false)
const exportando = ref(false)
const mostrarIngreso = ref(false)
const paginacion = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 25 })

const filtrosVacios = () => ({
  q: '', situacion: 'EN_AREA', estado_id: '', responsable_id: '', ministerio_id: '', tipo_requerimiento_id: '',
  ingreso_desde: '', ingreso_hasta: '', egreso_desde: '', egreso_hasta: '', orden: 'antiguedad',
  mis: false, accion_vencida: false, plazo_vencido: false, demorados: false,
})
const filtros = reactive(filtrosVacios())
const hayFiltros = computed(() => JSON.stringify({ ...filtros }) !== JSON.stringify(filtrosVacios()))

const filtrarPor = (tab, cambios) => {
  activeTab.value = tab
  Object.assign(filtros, filtrosVacios(), cambios)
  recargar()
}

const tarjetas = computed(() => {
  const t = tablero.value.totales || {}
  return [
    { key: 'en_area', label: 'En el área', valor: t.en_area ?? 0, color: 'text-slate-800', accion: () => filtrarPor('listado', {}) },
    { key: 'mis', label: 'Mis expedientes', valor: t.mis_expedientes ?? 0, color: 'text-sky-600', accion: () => filtrarPor('listado', { mis: true }) },
    { key: 'sin', label: 'Sin responsable', valor: t.sin_responsable ?? 0, color: 'text-amber-600', accion: () => filtrarPor('listado', { responsable_id: 'sin' }) },
    { key: 'demorados', label: `Más de ${tablero.value.umbral_dias_alerta} días`, valor: t.demorados ?? 0, color: 'text-orange-600', accion: () => filtrarPor('listado', { demorados: true }) },
    { key: 'accion', label: 'Acción vencida', valor: t.accion_vencida ?? 0, color: 'text-rose-600', accion: () => filtrarPor('listado', { accion_vencida: true }) },
    { key: 'plazo', label: 'Plazo vencido', valor: t.plazo_vencido ?? 0, color: 'text-rose-700', accion: () => filtrarPor('listado', { plazo_vencido: true }) },
    { key: 'egresos', label: 'Egresos del mes', valor: t.egresos_mes ?? 0, color: 'text-emerald-600', accion: () => filtrarPor('listado', { situacion: 'EGRESADAS', orden: 'egreso_desc' }) },
  ]
})

const parametros = (pagina = true) => {
  const p = { ...filtros }
  p.mis = filtros.mis ? 1 : ''
  p.accion_vencida = filtros.accion_vencida ? 1 : ''
  p.plazo_vencido = filtros.plazo_vencido ? 1 : ''
  p.dias_min = filtros.demorados ? tablero.value.umbral_dias_alerta : ''
  delete p.demorados
  if (pagina) Object.assign(p, { page: paginacion.current_page, per_page: paginacion.per_page })
  return p
}

const cargarTablero = async () => {
  try {
    const res = await get('/expedientes/tablero')
    if (res.success) tablero.value = res.data
  } catch (err) {
    toast.error(err, { title: 'Error al cargar el tablero' })
  }
}

const cargarListado = async () => {
  cargando.value = true
  try {
    const res = await get('/expedientes', parametros())
    if (res.success) {
      filas.value = res.data.data
      Object.assign(paginacion, {
        current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total, from: res.data.from, to: res.data.to,
      })
    }
  } catch (err) {
    toast.error(err, { title: 'Error al cargar expedientes' })
  } finally {
    cargando.value = false
  }
}

const recargar = () => {
  paginacion.current_page = 1
  if (activeTab.value === 'listado') cargarListado()
}

let temporizador = null
const buscarConDemora = () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(recargar, 350)
}

const irAPagina = (pagina) => {
  paginacion.current_page = pagina
  cargarListado()
}

const cambiarTab = (tab) => {
  activeTab.value = tab
  if (tab === 'listado') cargarListado()
  else cargarTablero()
}

const limpiarFiltros = () => {
  Object.assign(filtros, filtrosVacios())
  recargar()
}

const abrirFicha = (expedienteId) => {
  if (expedienteId) navigateTo(`/expedientes/${expedienteId}`)
}

const alIngresar = (intervencion) => {
  mostrarIngreso.value = false
  abrirFicha(intervencion.expediente_id)
}

// Descarga con el token de la sesión (sin rutas públicas: ver docs/security.md SEC-01)
const exportar = async () => {
  exportando.value = true
  try {
    const query = Object.fromEntries(Object.entries(parametros(false)).filter(([, v]) => v !== '' && v !== null && v !== false))
    const blob = await $fetch(`${config.public.apiBase}/expedientes/exportar`, {
      query,
      headers: { Authorization: `Bearer ${authStore.token}` },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `expedientes_${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    toast.error(err, { title: 'Error al exportar' })
  } finally {
    exportando.value = false
  }
}

onMounted(async () => {
  await Promise.all([cargarOpciones(), cargarTablero()])
  try {
    const res = await get('/ministerios')
    if (res.success) ministerios.value = res.data
  } catch (err) {
    console.error('Error cargando organismos:', err)
  }
})
</script>

<style scoped>
.filtro {
  @apply w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition;
}
.filtro-label {
  @apply block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1;
}
</style>
