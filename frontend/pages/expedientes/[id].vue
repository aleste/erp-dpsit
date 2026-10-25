<template>
  <div class="space-y-6 max-w-6xl mx-auto">
    <NuxtLink to="/expedientes" class="text-sm font-bold text-primary-700 hover:underline">← Volver al seguimiento</NuxtLink>

    <div v-if="cargando && !expediente" class="p-12 text-center text-sm text-slate-500">Cargando expediente...</div>

    <template v-else-if="expediente">
      <!-- Encabezado del expediente -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-extrabold text-slate-900 font-mono">{{ expediente.numero }}</h1>
            <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-xs font-bold uppercase text-slate-600">{{ expediente.formato }}</span>
            <span v-if="actual" class="px-2 py-0.5 rounded-lg text-xs font-bold uppercase" :class="actual.en_area ? 'bg-primary-100 text-primary-700' : 'bg-emerald-100 text-emerald-700'">
              {{ actual.en_area ? 'En el área' : 'Egresado' }}
            </span>
          </div>
          <p class="text-sm text-slate-500">{{ expediente.ministerio?.nombre || 'Sin organismo' }}<span v-if="expediente.reparticion"> · Repartición {{ expediente.reparticion }}</span></p>
          <p class="text-sm text-slate-800">{{ expediente.caratula || 'Sin carátula' }}</p>
          <p class="text-xs text-slate-500">{{ expediente.intervenciones.length }} intervención(es) registradas</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button v-if="puedeOperar() && actual" @click="abrirEdicion" class="btn-sec">✏️ Editar datos</button>
          <button v-if="puedeOperar() && actual && !actual.en_area" @click="mostrarReingreso = true" class="btn-pri">↩ Registrar reingreso</button>
        </div>
      </div>

      <!-- Situación actual -->
      <div v-if="actual" class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-extrabold text-slate-900">Situación actual · {{ actual.numero_intervencion }}ª intervención</h2>
          <ExpedientesEstadoChip :estado="actual.estado" />
        </div>

        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
          <div v-for="d in datosActuales" :key="d.label" class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <dt class="text-xs font-bold uppercase text-slate-500">{{ d.label }}</dt>
            <dd class="font-semibold text-slate-800 mt-0.5 break-words" :class="d.clase">{{ d.valor }}</dd>
          </div>
        </dl>
        <p v-if="actual.descripcion" class="text-sm text-slate-600"><span class="font-bold">Motivo:</span> {{ actual.descripcion }}</p>
        <p v-if="actual.observaciones" class="text-sm text-slate-500 whitespace-pre-line"><span class="font-bold">Observaciones:</span> {{ actual.observaciones }}</p>

        <!-- Acciones del circuito -->
        <div v-if="puedeOperar()" class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
          <template v-if="actual.en_area">
            <button @click="abrirPanel('estado')" class="btn-sec">🔀 Cambiar estado</button>
            <button @click="abrirPanel('asignar')" class="btn-sec">👤 Asignar</button>
            <button @click="abrirPanel('revision')" class="btn-sec">📝 Enviar a revisión</button>
            <button @click="abrirPanel('firma')" class="btn-sec">✒️ Registrar firma</button>
            <button @click="abrirPanel('egreso')" class="btn-sec text-emerald-700">📤 Registrar egreso</button>
          </template>
          <button @click="abrirPanel('nota')" class="btn-sec">💬 Agregar nota</button>
          <template v-if="esSupervisor()">
            <button @click="abrirPanel('fechas')" class="btn-sec">📅 Corregir fechas</button>
            <button v-if="!actual.en_area" @click="abrirPanel('reabrir')" class="btn-sec text-rose-700">⟲ Reabrir (error)</button>
          </template>
        </div>

        <!-- Panel de la acción elegida -->
        <form v-if="panel" @submit.prevent="ejecutar" class="p-4 rounded-xl border border-primary-200 bg-primary-50/40 space-y-3 text-sm">
          <p class="font-bold text-slate-700">{{ TITULOS_PANEL[panel] }}</p>

          <template v-if="panel === 'estado'">
            <select v-model="accion.estado_id" required class="campo">
              <option value="">Seleccione el nuevo estado...</option>
              <option v-for="e in estadosTrabajo" :key="e.id" :value="e.id" :disabled="e.id === actual.estado_id">{{ e.nombre }}</option>
            </select>
          </template>

          <template v-if="panel === 'asignar'">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label for="expediente-accion-responsable-id" class="block font-bold text-slate-600 mb-1">Responsable</label>
                <select id="expediente-accion-responsable-id" v-model="accion.responsable_id" class="campo">
                  <option value="">Sin responsable</option>
                  <option v-for="u in opciones.integrantes" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div>
                <label for="expediente-accion-colaboradores" class="block font-bold text-slate-600 mb-1">Colaboran</label>
                <select id="expediente-accion-colaboradores" v-model="accion.colaboradores" multiple class="campo h-24">
                  <option v-for="u in opciones.integrantes" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>
            <p v-if="actual.responsable_id && !esSupervisor()" class="text-xs text-amber-700">Reasignar a otra persona requiere rol Supervisor.</p>
          </template>

          <template v-if="panel === 'revision'">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <select v-model="accion.revisor_id" required class="campo">
                <option value="">Revisor...</option>
                <option v-for="u in opciones.integrantes" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <input v-model="accion.fecha" type="date" required :max="hoyISO()" class="campo" />
              <select v-model="accion.estado_id" class="campo">
                <option value="">Mantener estado</option>
                <option v-for="e in estadosTrabajo" :key="e.id" :value="e.id">Pasar a: {{ e.nombre }}</option>
              </select>
            </div>
            <input v-model="accion.documento_respuesta" type="text" placeholder="Documento en revisión (opcional, p. ej. IF-2026-...)" class="campo font-mono" />
          </template>

          <template v-if="panel === 'firma'">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <input v-model="accion.fecha" type="date" required :max="hoyISO()" class="campo" />
              <input v-model="accion.documento_respuesta" type="text" placeholder="Documento firmado (p. ej. IF-2026-...)" class="campo font-mono" />
            </div>
          </template>

          <template v-if="panel === 'egreso'">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <input v-model="accion.fecha_egreso" type="date" required :max="hoyISO()" class="campo" />
              <select v-model="accion.estado_id" required class="campo">
                <option v-for="e in estadosFinales" :key="e.id" :value="e.id">{{ e.nombre }}</option>
              </select>
              <input v-model="accion.documento_respuesta" type="text" :required="documentoObligatorio" :placeholder="documentoObligatorio ? 'Documento de respuesta (GEDO) *' : 'Documento de respuesta (opcional)'" class="campo font-mono" />
            </div>
            <div>
              <label for="expediente-accion-dependencia-destino-nombre" class="block font-bold text-slate-600 mb-1">Remitido a *</label>
              <input id="expediente-accion-dependencia-destino-nombre" v-model="accion.dependencia_destino_nombre" list="destinos-expedientes" required placeholder="Dependencia de destino (elija una existente o escriba una nueva)" class="campo" />
              <datalist id="destinos-expedientes">
                <option v-for="d in opciones.destinos.filter(x => x.activo)" :key="d.id" :value="d.nombre" />
              </datalist>
            </div>
          </template>

          <template v-if="panel === 'nota'">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <input v-model="accion.fecha" type="date" :max="hoyISO()" class="campo" />
              <input v-model="accion.referencia_documento" type="text" placeholder="Documento relacionado (opcional)" class="campo font-mono" />
              <input v-model="accion.fecha_proximo_paso" type="date" title="Fecha del próximo paso (opcional)" class="campo" />
            </div>
            <p class="text-xs text-slate-500">Si indica la fecha del próximo paso, la nota pasa a ser la próxima acción del expediente.</p>
          </template>

          <template v-if="panel === 'fechas'">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <label class="space-y-1"><span class="font-bold text-slate-600">Ingreso</span><input v-model="accion.fecha_ingreso" type="date" :max="hoyISO()" class="campo" /></label>
              <label v-if="!actual.en_area" class="space-y-1"><span class="font-bold text-slate-600">Egreso</span><input v-model="accion.fecha_egreso" type="date" :max="hoyISO()" class="campo" /></label>
              <label class="space-y-1"><span class="font-bold text-slate-600">A revisión</span><input v-model="accion.fecha_envio_revision" type="date" :max="hoyISO()" class="campo" /></label>
              <label class="space-y-1"><span class="font-bold text-slate-600">Firma</span><input v-model="accion.fecha_firma" type="date" :max="hoyISO()" class="campo" /></label>
            </div>
          </template>

          <template v-if="panel === 'reabrir'">
            <select v-model="accion.estado_id" required class="campo">
              <option value="">Estado al reabrir...</option>
              <option v-for="e in estadosTrabajo" :key="e.id" :value="e.id">{{ e.nombre }}</option>
            </select>
            <p class="text-xs text-slate-500">Solo para corregir un egreso registrado por error. Si el expediente volvió al área, use «Registrar reingreso».</p>
          </template>

          <textarea
            v-model="accion.texto"
            rows="2"
            :required="['nota', 'fechas', 'reabrir'].includes(panel)"
            :placeholder="['fechas', 'reabrir'].includes(panel) ? 'Motivo (obligatorio, queda en el historial)' : (panel === 'nota' ? 'Tarea realizada, comentario o novedad *' : 'Descripción o comentario (opcional)')"
            class="campo"
          ></textarea>

          <div class="flex justify-end gap-2">
            <button type="button" @click="panel = null" class="px-3 py-1.5 bg-slate-100 rounded-lg font-semibold">Cancelar</button>
            <button type="submit" :disabled="procesando" class="px-4 py-1.5 bg-primary-700 text-white rounded-lg font-bold disabled:opacity-50">
              {{ procesando ? 'Registrando...' : 'Registrar' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Historial: intervenciones y movimientos -->
      <div class="space-y-3">
        <h2 class="text-sm font-extrabold text-slate-900">Historial del expediente</h2>
        <div v-for="i in expediente.intervenciones" :key="i.id" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
          <button @click="toggle(i.id)" class="w-full p-4 flex flex-wrap items-center gap-3 text-left text-sm hover:bg-slate-50">
            <span class="font-extrabold text-slate-900">{{ i.numero_intervencion }}ª intervención</span>
            <ExpedientesEstadoChip :estado="i.estado" />
            <span class="text-slate-500">{{ formatFecha(i.fecha_ingreso) }} → {{ i.en_area ? 'en el área' : (i.fecha_egreso ? formatFecha(i.fecha_egreso) : 'egreso sin fecha') }}</span>
            <span v-if="i.responsable" class="text-slate-500">· {{ i.responsable.name }}</span>
            <span v-if="i.documento_respuesta" class="font-mono text-slate-500">· {{ i.documento_respuesta }}</span>
            <span v-if="i.origen === 'PLANILLA'" class="px-1.5 rounded bg-slate-100 text-xs font-bold text-slate-500">Importado ({{ i.origen_referencia }})</span>
            <span class="ml-auto text-slate-500">{{ abiertas[i.id] ? '▲' : '▼' }} {{ i.movimientos.length }} movimientos</span>
          </button>
          <ol v-if="abiertas[i.id]" class="border-t border-slate-100 divide-y divide-slate-50">
            <li v-for="m in i.movimientos" :key="m.id" class="px-4 py-3 text-sm flex flex-col sm:flex-row gap-1 sm:gap-4">
              <span class="text-slate-500 whitespace-nowrap sm:w-24">{{ formatFecha(m.fecha) }}</span>
              <span class="font-bold text-slate-700 sm:w-40">{{ TIPOS_MOVIMIENTO[m.tipo] || m.tipo }}</span>
              <span class="flex-1 text-slate-600 whitespace-pre-line">
                <template v-if="m.estado_anterior || m.estado_nuevo">
                  <span class="font-semibold">{{ m.estado_anterior?.nombre || '—' }} → {{ m.estado_nuevo?.nombre || '—' }}.</span>
                </template>
                {{ m.descripcion }}
                <span v-if="m.referencia_documento" class="font-mono text-slate-500"> [{{ m.referencia_documento }}]</span>
                <span v-if="m.fecha_proximo_paso" class="text-primary-700"> · Próximo paso: {{ formatFecha(m.fecha_proximo_paso) }}</span>
              </span>
              <span class="text-slate-500 whitespace-nowrap">{{ m.usuario?.name || (m.importado ? 'Planilla' : '—') }} · {{ formatFechaHora(m.created_at) }}</span>
            </li>
          </ol>
        </div>
      </div>
    </template>

    <!-- Edición de datos (no cambia estado ni fechas del circuito) -->
    <div v-if="edicion.visible" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (edicion.visible = false)" class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[92vh] flex flex-col">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <h2 class="text-base font-bold">Editar datos del expediente</h2>
          <button type="button" aria-label="Cerrar" @click="edicion.visible = false" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form @submit.prevent="guardarEdicion" class="p-6 space-y-3 text-sm overflow-y-auto">
          <label class="block space-y-1"><span class="font-bold text-slate-700">Carátula</span><textarea v-model="edicion.caratula" rows="2" class="campo"></textarea></label>
          <div class="grid grid-cols-2 gap-3">
            <label class="space-y-1"><span class="font-bold text-slate-700">Organismo</span>
              <select v-model="edicion.ministerio_id" class="campo"><option :value="null">Sin especificar</option><option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.nombre }}</option></select>
            </label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Requerimiento</span>
              <select v-model="edicion.tipo_requerimiento_id" class="campo"><option :value="null">Sin especificar</option><option v-for="t in opciones.tipos_requerimiento" :key="t.id" :value="t.id">{{ t.nombre }}</option></select>
            </label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Prioridad</span>
              <select v-model="edicion.prioridad" class="campo"><option value="NORMAL">Normal</option><option value="ALTA">Alta</option></select>
            </label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Documento de respuesta</span><input v-model="edicion.documento_respuesta" type="text" class="campo font-mono" /></label>
            <label class="space-y-1 col-span-2"><span class="font-bold text-slate-700">Próxima acción</span><input v-model="edicion.proxima_accion" type="text" maxlength="255" class="campo" /></label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Fecha próxima acción</span><input v-model="edicion.fecha_proxima_accion" type="date" class="campo" /></label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Vencimiento (plazo real)</span><input v-model="edicion.fecha_vencimiento" type="date" class="campo" /></label>
            <label class="space-y-1"><span class="font-bold text-slate-700">ETB</span><input v-model="edicion.etb" type="text" maxlength="100" class="campo" /></label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Con acto administrativo</span>
              <select v-model="edicion.con_acto_administrativo" class="campo"><option :value="null">—</option><option v-for="(l, v) in ACTO_ADMINISTRATIVO" :key="v" :value="v">{{ l }}</option></select>
            </label>
          </div>
          <label class="block space-y-1"><span class="font-bold text-slate-700">Motivo de la intervención</span><textarea v-model="edicion.descripcion" rows="2" class="campo"></textarea></label>
          <label class="block space-y-1"><span class="font-bold text-slate-700">Observaciones</span><textarea v-model="edicion.observaciones" rows="3" class="campo"></textarea></label>
          <p class="text-xs text-slate-500">Cada cambio queda registrado en el historial como «Actualización de datos».</p>
          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="edicion.visible = false" class="px-4 py-2 bg-slate-100 rounded-xl font-semibold">Cancelar</button>
            <button type="submit" :disabled="procesando" class="px-5 py-2 bg-primary-700 text-white rounded-xl font-bold disabled:opacity-50">Guardar</button>
          </div>
        </form>
      </div>
    </div>

    <ExpedientesIngresoModal
      v-if="mostrarReingreso"
      :ministerios="ministerios"
      :numero-inicial="expediente?.numero"
      @close="mostrarReingreso = false"
      @saved="alReingresar"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import { useExpedientes, TIPOS_MOVIMIENTO, ACTO_ADMINISTRATIVO } from '~/composables/useExpedientes'
import { formatFecha, formatFechaHora, hoyISO } from '~/composables/useFechas'

const route = useRoute()
const { get, post, put } = useApi()
const { toast } = useToast()
const { opciones, cargarOpciones, puedeOperar, esSupervisor } = useExpedientes()

const TITULOS_PANEL = {
  estado: 'Cambiar estado', asignar: 'Asignar responsable y colaboradores', revision: 'Enviar a revisión',
  firma: 'Registrar firma', egreso: 'Registrar egreso del área', nota: 'Agregar nota / intervención',
  fechas: 'Corregir fechas (Supervisor)', reabrir: 'Reabrir intervención egresada (Supervisor)',
}

const expediente = ref(null)
const ministerios = ref([])
const cargando = ref(false)
const procesando = ref(false)
const panel = ref(null)
const accion = reactive({})
const abiertas = reactive({})
const mostrarReingreso = ref(false)
const edicion = reactive({ visible: false })

const actual = computed(() => expediente.value?.intervenciones?.[0] || null)
const estadosTrabajo = computed(() => opciones.value.estados.filter(e => !e.es_final && e.activo))
const estadosFinales = computed(() => opciones.value.estados.filter(e => e.es_final && e.activo))
const documentoObligatorio = computed(() => {
  const final = estadosFinales.value.find(e => e.id === accion.estado_id)
  return !!final?.requiere_documento && !actual.value?.estado?.exime_documento
})

const datosActuales = computed(() => {
  const i = actual.value
  if (!i) return []
  const hoy = hoyISO()
  return [
    { label: 'Responsable', valor: i.responsable?.name || 'Sin asignar', clase: !i.responsable && 'text-amber-700' },
    { label: 'Colaboran', valor: i.colaboradores?.map(c => c.name).join(', ') || '—' },
    { label: 'Ingreso', valor: formatFecha(i.fecha_ingreso) },
    { label: i.en_area ? 'Días en el área' : 'Egreso', valor: i.en_area ? i.dias_en_area : (i.fecha_egreso ? formatFecha(i.fecha_egreso) : 'Sin fecha') },
    { label: 'Requerimiento', valor: i.tipo_requerimiento?.nombre || '—' },
    { label: 'Prioridad', valor: i.prioridad === 'ALTA' ? 'Alta' : 'Normal', clase: i.prioridad === 'ALTA' && 'text-rose-700' },
    { label: 'Próxima acción', valor: i.proxima_accion ? `${i.proxima_accion}${i.fecha_proxima_accion ? ' (' + formatFecha(i.fecha_proxima_accion) + ')' : ''}` : '—', clase: i.fecha_proxima_accion && i.fecha_proxima_accion < hoy && i.en_area && 'text-rose-700' },
    { label: 'Vencimiento', valor: formatFecha(i.fecha_vencimiento), clase: i.fecha_vencimiento && i.fecha_vencimiento < hoy && i.en_area && 'text-rose-700' },
    { label: 'Revisión', valor: i.revisor ? `${i.revisor.name} (${formatFecha(i.fecha_envio_revision)})` : '—' },
    { label: 'Firma', valor: formatFecha(i.fecha_firma) },
    { label: 'Documento de respuesta', valor: i.documento_respuesta || '—', clase: 'font-mono' },
    { label: 'Remitido a', valor: i.dependencia_destino?.nombre || '—' },
  ]
})

const cargar = async () => {
  cargando.value = true
  try {
    const res = await get(`/expedientes/${route.params.id}`)
    if (res.success) {
      expediente.value = res.data
      if (res.data.intervenciones[0]) abiertas[res.data.intervenciones[0].id] = true
    }
  } catch (err) {
    toast.error(err, { title: 'Error al cargar el expediente' })
  } finally {
    cargando.value = false
  }
}

const toggle = (id) => { abiertas[id] = !abiertas[id] }

const abrirPanel = (tipo) => {
  const i = actual.value
  for (const k of Object.keys(accion)) delete accion[k]
  Object.assign(accion, { texto: '', fecha: hoyISO() })
  if (tipo === 'asignar') Object.assign(accion, { responsable_id: i.responsable_id || '', colaboradores: i.colaboradores?.map(c => c.id) || [] })
  if (tipo === 'revision') Object.assign(accion, { revisor_id: i.revisor_id || '', estado_id: '', documento_respuesta: i.documento_respuesta || '' })
  if (tipo === 'firma') Object.assign(accion, { documento_respuesta: i.documento_respuesta || '' })
  if (tipo === 'egreso') Object.assign(accion, { fecha_egreso: hoyISO(), estado_id: estadosFinales.value[0]?.id || '', documento_respuesta: i.documento_respuesta || '', dependencia_destino_nombre: '' })
  if (tipo === 'nota') Object.assign(accion, { referencia_documento: '', fecha_proximo_paso: '' })
  if (tipo === 'fechas') Object.assign(accion, { fecha_ingreso: i.fecha_ingreso, fecha_egreso: i.fecha_egreso || '', fecha_envio_revision: i.fecha_envio_revision || '', fecha_firma: i.fecha_firma || '' })
  if (tipo === 'estado' || tipo === 'reabrir') accion.estado_id = ''
  panel.value = tipo
}

// Cada acción usa su endpoint; el backend registra el movimiento correspondiente
const ejecutar = async () => {
  const id = actual.value.id
  const t = accion.texto || null
  const solicitudes = {
    estado: () => post(`/expedientes/intervenciones/${id}/estado`, { estado_id: accion.estado_id, descripcion: t }),
    asignar: () => post(`/expedientes/intervenciones/${id}/asignar`, { responsable_id: accion.responsable_id || null, colaboradores: accion.colaboradores, descripcion: t }),
    revision: () => post(`/expedientes/intervenciones/${id}/revision`, { revisor_id: accion.revisor_id, fecha: accion.fecha, estado_id: accion.estado_id || null, documento_respuesta: accion.documento_respuesta || null, descripcion: t }),
    firma: () => post(`/expedientes/intervenciones/${id}/firma`, { fecha: accion.fecha, documento_respuesta: accion.documento_respuesta || null, descripcion: t }),
    egreso: () => {
      const existente = opciones.value.destinos.find(d => d.nombre.toLowerCase() === accion.dependencia_destino_nombre.trim().toLowerCase())
      return post(`/expedientes/intervenciones/${id}/egreso`, {
        fecha_egreso: accion.fecha_egreso, estado_id: accion.estado_id, documento_respuesta: accion.documento_respuesta || null, descripcion: t,
        ...(existente ? { dependencia_destino_id: existente.id } : { dependencia_destino_nombre: accion.dependencia_destino_nombre }),
      })
    },
    nota: () => post(`/expedientes/intervenciones/${id}/notas`, { descripcion: t, fecha: accion.fecha || null, referencia_documento: accion.referencia_documento || null, fecha_proximo_paso: accion.fecha_proximo_paso || null }),
    fechas: () => {
      const cambios = {}
      for (const campo of ['fecha_ingreso', 'fecha_egreso', 'fecha_envio_revision', 'fecha_firma']) {
        const original = actual.value[campo] || ''
        if (campo in accion && accion[campo] !== original) cambios[campo] = accion[campo] || null
      }
      return post(`/expedientes/intervenciones/${id}/corregir-fechas`, { motivo: t, ...cambios })
    },
    reabrir: () => post(`/expedientes/intervenciones/${id}/reabrir`, { motivo: t, estado_id: accion.estado_id }),
  }

  procesando.value = true
  try {
    const res = await solicitudes[panel.value]()
    if (res.success) {
      toast.success(res.message)
      panel.value = null
      // Un egreso puede haber creado un destino nuevo: se recargan también las opciones
      await Promise.all([cargar(), cargarOpciones(true)])
    }
  } catch (err) {
    toast.error(err)
  } finally {
    procesando.value = false
  }
}

const abrirEdicion = () => {
  const i = actual.value
  Object.assign(edicion, {
    visible: true,
    caratula: expediente.value.caratula || '',
    ministerio_id: expediente.value.ministerio_id,
    tipo_requerimiento_id: i.tipo_requerimiento_id,
    descripcion: i.descripcion || '',
    prioridad: i.prioridad,
    proxima_accion: i.proxima_accion || '',
    fecha_proxima_accion: i.fecha_proxima_accion || '',
    fecha_vencimiento: i.fecha_vencimiento || '',
    etb: i.etb || '',
    con_acto_administrativo: i.con_acto_administrativo,
    observaciones: i.observaciones || '',
    documento_respuesta: i.documento_respuesta || '',
  })
}

const guardarEdicion = async () => {
  procesando.value = true
  try {
    const { visible, ...datos } = edicion
    // Vacíos como null para que el historial no registre cambios falsos ('' → null)
    const payload = Object.fromEntries(Object.entries(datos).map(([k, v]) => [k, v === '' ? null : v]))
    const res = await put(`/expedientes/intervenciones/${actual.value.id}`, payload)
    if (res.success) {
      toast.success(res.message)
      edicion.visible = false
      await cargar()
    }
  } catch (err) {
    toast.error(err)
  } finally {
    procesando.value = false
  }
}

const alReingresar = () => {
  mostrarReingreso.value = false
  cargar()
}

onMounted(async () => {
  await Promise.all([cargarOpciones(), cargar()])
  try {
    const res = await get('/ministerios')
    if (res.success) ministerios.value = res.data
  } catch (err) {
    console.error('Error cargando organismos:', err)
  }
})
</script>

<style scoped>
.campo {
  @apply w-full p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600;
}
.btn-sec {
  @apply px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg text-xs font-bold text-slate-700 transition;
}
.btn-pri {
  @apply px-3 py-1.5 bg-primary-700 hover:bg-primary-600 rounded-lg text-xs font-bold text-white transition;
}
</style>
