<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Búsqueda y Gestión por Roles TI</h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Localización transversal de referentes y autoridades según el tipo de responsabilidad tecnológica.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="authStore.isEditor"
          @click="openNewAsignacionModal"
          class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-sm font-semibold shadow-md shadow-sky-600/20 transition"
        >
          + Asignar Nuevo Cargo/Rol
        </button>
      </div>
    </div>

    <!-- Filtros de Rol -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Selector de Rol Obligatorio/Filtro -->
        <div>
          <label for="roles-filters-tipo-rol-id" class="block text-sm font-bold text-slate-500 mb-1">Tipo de Rol / Función</label>
          <select id="roles-filters-tipo-rol-id"
            v-model="filters.tipo_rol_id"
            @change="fetchAsignaciones"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
          >
            <option value="">Todos los Roles</option>
            <option v-for="r in tiposRol" :key="r.id" :value="r.id">
              {{ r.nombre }} ({{ r.nivel_responsabilidad }})
            </option>
          </select>
        </div>

        <!-- Filtro por Ministerio -->
        <div>
          <label for="roles-filters-ministerio-id" class="block text-sm font-bold text-slate-500 mb-1">Ministerio</label>
          <select id="roles-filters-ministerio-id"
            v-model="filters.ministerio_id"
            @change="fetchAsignaciones"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
          >
            <option value="">Todos los Ministerios</option>
            <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.sigla }} - {{ m.nombre }}</option>
          </select>
        </div>

        <!-- Filtro por Vigencia -->
        <div>
          <label for="roles-filters-estado" class="block text-sm font-bold text-slate-500 mb-1">Estado de Vigencia</label>
          <select id="roles-filters-estado"
            v-model="filters.estado"
            @change="fetchAsignaciones"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
          >
            <option value="VIGENTE">Solo Vigentes (Actuales)</option>
            <option value="HISTORICO">Solo Históricos (Bajas)</option>
            <option value="">Todos los Registros</option>
          </select>
        </div>

        <!-- Filtro solo Máximas Autoridades -->
        <div class="flex items-end pb-2">
          <label class="inline-flex items-center gap-2 cursor-pointer text-sm font-semibold text-slate-700">
            <input
              v-model="filters.solo_maximas_autoridades"
              @change="fetchAsignaciones"
              type="checkbox"
              class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
            />
            Solo Máximas Autoridades
          </label>
        </div>
      </div>
    </div>

    <!-- Grilla de Resultados -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-sm text-slate-500">
        Buscando asignaciones de roles...
      </div>

      <div v-else-if="asignaciones.length === 0" class="p-12 text-center text-sm text-slate-500">
        No se encontraron personas con los roles y filtros seleccionados.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold text-xs uppercase">
              <th class="py-3 px-4">Rol Asignado</th>
              <th class="py-3 px-4">Persona / Referente</th>
              <th class="py-3 px-4">Organismo / Área TI</th>
              <th class="py-3 px-4">Vigencia y Acto</th>
              <th class="py-3 px-4 text-center">Autoridad</th>
              <th class="py-3 px-4 text-right">Contacto y Gestión</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-for="asig in asignaciones" :key="asig.id" class="hover:bg-slate-50/80 transition">
              <!-- Rol -->
              <td class="py-3.5 px-4">
                <span class="font-extrabold text-slate-900 block text-sm">{{ asig.tipo_rol?.nombre }}</span>
                <span class="text-xs text-slate-500 font-medium">{{ asig.tipo_rol?.nivel_responsabilidad }}</span>
              </td>

              <!-- Persona -->
              <td class="py-3.5 px-4">
                <p class="font-bold text-slate-900">{{ asig.persona?.apellido }}, {{ asig.persona?.nombre }}</p>
                <p class="text-xs text-slate-500">{{ asig.persona?.cuil_cuit }}</p>
              </td>

              <!-- Área TI y Ministerio -->
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-800">{{ asig.area_ti?.ministerio?.sigla }} - {{ asig.area_ti?.ministerio?.nombre }}</p>
                <p class="text-xs text-slate-500">{{ asig.area_ti?.nombre }}</p>
              </td>

              <!-- Vigencia -->
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-2">
                  <span
                    class="px-2 py-0.5 rounded text-xs font-bold uppercase"
                    :class="asig.estado_vigencia === 'VIGENTE' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ asig.estado_vigencia }}
                  </span>
                  <span class="text-xs text-slate-500">
                    {{ asig.fecha_inicio }} {{ asig.fecha_fin ? `al ${asig.fecha_fin}` : '(Indefinido)' }}
                  </span>
                </div>
                <p v-if="asig.acto_administrativo" class="text-xs text-slate-500 italic mt-0.5">
                  {{ asig.acto_administrativo }}
                </p>
              </td>

              <!-- Máxima Autoridad -->
              <td class="py-3.5 px-4 text-center">
                <span v-if="asig.es_maxima_autoridad" class="text-amber-500 font-bold" title="Máxima Autoridad TI del Área">⭐</span>
                <span v-else class="text-slate-300">-</span>
              </td>

              <!-- Contacto y Acciones -->
              <td class="py-3.5 px-4 text-right space-x-2">
                <a
                  :href="`mailto:${asig.persona?.email_principal}`"
                  class="p-1.5 inline-block text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-lg transition text-sm font-semibold"
                  title="Enviar correo institucional"
                >
                  ✉️ Correo
                </a>

                <button
                  v-if="authStore.isEditor && asig.estado_vigencia === 'VIGENTE'"
                  @click="finalizarVigenciaModal(asig)"
                  class="p-1.5 inline-block text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition text-sm font-semibold"
                  title="Finalizar vigencia / Dar de baja"
                >
                  ⏱️ Finalizar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL: ASIGNAR NUEVO CARGO / ROL -->
    <div v-if="showModalAsignar" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalAsignar = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <h2 class="text-base font-bold">Asignar Cargo / Rol Institucional</h2>
          <button type="button" aria-label="Cerrar" @click="showModalAsignar = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveAsignacion" class="p-6 space-y-4 text-sm">
          <div>
            <label for="roles-formasig-persona-id" class="block font-bold text-slate-700 mb-1">Persona *</label>
            <PersonaSearchSelect input-id="roles-formasig-persona-id"
              v-model="formAsig.persona_id"
              placeholder="Buscar agente por apellido, nombre o CUIL..."
            />
          </div>

          <div>
            <label for="roles-formasig-area-ti-id" class="block font-bold text-slate-700 mb-1">Área TI de Destino *</label>
            <select id="roles-formasig-area-ti-id"
              v-model="formAsig.area_ti_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              <option value="" disabled>Seleccione Área TI...</option>
              <option v-for="a in areasList" :key="a.id" :value="a.id">
                {{ a.ministerio?.sigla }} - {{ a.nombre }}
              </option>
            </select>
          </div>

          <div>
            <label for="roles-formasig-tipo-rol-id" class="block font-bold text-slate-700 mb-1">Cargo / Tipo de Rol *</label>
            <select id="roles-formasig-tipo-rol-id"
              v-model="formAsig.tipo_rol_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              <option value="" disabled>Seleccione Rol...</option>
              <option v-for="r in tiposRol" :key="r.id" :value="r.id">
                {{ r.nombre }} ({{ r.nivel_responsabilidad }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="roles-formasig-fecha-inicio" class="block font-bold text-slate-700 mb-1">Fecha de Inicio *</label>
              <input id="roles-formasig-fecha-inicio"
                v-model="formAsig.fecha_inicio"
                type="date"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label for="roles-formasig-acto-administrativo" class="block font-bold text-slate-700 mb-1">Acto de Designación</label>
              <input id="roles-formasig-acto-administrativo"
                v-model="formAsig.acto_administrativo"
                type="text"
                placeholder="Ej: RES-2024-400"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div>
            <label class="flex items-center gap-2 cursor-pointer pt-1">
              <input
                type="checkbox"
                v-model="formAsig.es_maxima_autoridad"
                class="w-4 h-4 text-amber-500 rounded border-slate-300 focus:ring-amber-500"
              />
              <span class="font-bold text-slate-800">⭐ Designar formalmente como Máxima Autoridad del Área</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalAsignar = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
            >
              {{ saving ? 'Guardando...' : 'Asignar Cargo' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

const { get, post } = useApi()
const authStore = useAuthStore()
const { toast, confirmDialog } = useToast()

const asignaciones = ref([])
const tiposRol = ref([])
const ministerios = ref([])
const areasList = ref([])
const loading = ref(false)
const saving = ref(false)

const showModalAsignar = ref(false)

const filters = reactive({
  tipo_rol_id: '',
  ministerio_id: '',
  estado: 'VIGENTE',
  solo_maximas_autoridades: false,
})

const formAsig = reactive({
  persona_id: '',
  area_ti_id: '',
  tipo_rol_id: '',
  fecha_inicio: new Date().toISOString().split('T')[0],
  acto_administrativo: '',
  es_maxima_autoridad: false,
  observaciones: '',
})

const fetchAsignaciones = async () => {
  loading.value = true
  try {
    const res = await get('/asignaciones-rol', filters)
    if (res.success) {
      asignaciones.value = res.data.data
    }
  } catch (err) {
    console.error('Error cargando asignaciones por rol:', err)
    toast.error(err, { title: 'Error al consultar asignaciones' })
  } finally {
    loading.value = false
  }
}

const fetchCatalogos = async () => {
  try {
    const [resRoles, resMin] = await Promise.all([
      get('/tipos-rol'),
      get('/ministerios'),
    ])
    if (resRoles.success) tiposRol.value = resRoles.data
    if (resMin.success) ministerios.value = resMin.data
  } catch (err) {
    console.error('Error cargando catálogos:', err)
  }
}

const openNewAsignacionModal = async () => {
  try {
    const resA = await get('/areas-ti')
    if (resA.success) areasList.value = resA.data

    Object.assign(formAsig, {
      persona_id: '',
      area_ti_id: areasList.value[0]?.id || '',
      tipo_rol_id: tiposRol.value[0]?.id || '',
      fecha_inicio: new Date().toISOString().split('T')[0],
      acto_administrativo: '',
      es_maxima_autoridad: false,
      observaciones: '',
    })

    showModalAsignar.value = true
  } catch (err) {
    toast.error(err, { title: 'Error al abrir formulario de asignación' })
  }
}

const saveAsignacion = async () => {
  if (!formAsig.persona_id) {
    toast.warning('Por favor seleccione un agente del padrón.')
    return
  }
  saving.value = true
  try {
    const res = await post('/asignaciones-rol', formAsig)
    if (res.success) {
      toast.success('Designación y asignación de rol registrada exitosamente.')
      showModalAsignar.value = false
      await fetchAsignaciones()
    }
  } catch (err) {
    toast.error(err)
  } finally {
    saving.value = false
  }
}

const finalizarVigenciaModal = async (asig) => {
  const personaNombre = `${asig.persona?.apellido}, ${asig.persona?.nombre}`
  const rolNombre = asig.tipo_rol?.nombre
  const confirmed = await confirmDialog({
    title: 'Finalizar Vigencia del Cargo',
    message: `¿Desea registrar el cese o baja del cargo "${rolNombre}" para ${personaNombre}? La asignación pasará al historial de la persona y del área.`,
    confirmText: 'Sí, finalizar vigencia',
    cancelText: 'Cancelar',
    type: 'warning',
  })

  if (confirmed) {
    try {
      const today = new Date().toISOString().split('T')[0]
      const res = await post(`/asignaciones-rol/${asig.id}/finalizar-vigencia`, {
        fecha_fin: today,
        motivo: 'Baja formal registrada desde el portal',
      })
      if (res.success) {
        toast.success('Vigencia finalizada correctamente. El registro ahora figura como histórico.')
        await fetchAsignaciones()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

onMounted(() => {
  fetchCatalogos()
  fetchAsignaciones()
})
</script>
