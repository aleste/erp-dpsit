<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado y Botón de Creación -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Gestión de Ministerios y Organismos</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Estructura del Poder Ejecutivo Provincial y entidades rectoras de gobierno.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="authStore.isEditor"
          @click="openModalCreate"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition"
        >
          <span>+</span> Nuevo Ministerio / Organismo
        </button>
      </div>
    </div>

    <!-- Indicadores Rápidos -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total de Ministerios</p>
          <h3 class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ ministerios.length }}</h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-base">
          🏛️
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-sky-600">Total Áreas TI Dependientes</p>
          <h3 class="text-2xl font-extrabold text-sky-600 mt-0.5">{{ totalAreasTi }}</h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-base">
          🏢
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Organismos Activos</p>
          <h3 class="text-2xl font-extrabold text-emerald-600 mt-0.5">
            {{ ministerios.filter(m => m.activo).length }}
          </h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
          ✓
        </div>
      </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="lg:col-span-3">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Buscar Ministerio u Organismo</label>
          <input
            v-model="filters.q"
            @input="debounceSearch"
            type="text"
            placeholder="Buscar por nombre, código o sigla (ej. Salud, MS, MEYF)..."
            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition"
          />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Estado</label>
          <select
            v-model="filters.activo"
            @change="fetchMinisterios"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
          >
            <option value="">Todos los Estados</option>
            <option value="true">Solo Activos</option>
            <option value="false">Inactivos</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Lista de Ministerios -->
    <div v-if="loading" class="p-12 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
      Cargando ministerios y organismos...
    </div>

    <div v-else-if="ministerios.length === 0" class="p-12 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
      No se encontraron ministerios registrados.
    </div>

    <div v-else class="space-y-6">
      <div
        v-for="m in ministerios"
        :key="m.id"
        class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden"
      >
        <!-- Cabecera del Ministerio -->
        <div class="p-6 bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-sky-600 text-white rounded-xl text-xs font-extrabold tracking-wider">
              {{ m.sigla || m.codigo }}
            </span>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold">{{ m.nombre }}</h2>
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                  :class="m.activo ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700 text-slate-400'"
                >
                  {{ m.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </div>
              <p class="text-xs text-slate-400">Código Oficial: <strong class="font-mono text-slate-300">{{ m.codigo }}</strong> | Prioridad: {{ m.orden_prioridad }}</p>
            </div>
          </div>

          <!-- Acciones de Ministerio -->
          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 mr-2 hidden sm:inline">
              {{ m.areas_ti_count || (m.areas_ti ? m.areas_ti.length : 0) }} Áreas TI
            </span>

            <button
              v-if="authStore.isEditor"
              @click="openModalEdit(m)"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold border border-slate-700 transition"
              title="Editar Ministerio"
            >
              ✏️ Editar
            </button>

            <button
              v-if="authStore.isAdmin"
              @click="confirmDelete(m)"
              class="px-3 py-1.5 bg-rose-950 hover:bg-rose-900 text-rose-300 rounded-lg text-xs font-semibold border border-rose-800 transition"
              title="Eliminar Ministerio"
            >
              🗑️ Eliminar
            </button>
          </div>
        </div>

        <!-- Áreas TI dependientes del Ministerio -->
        <div class="p-6">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
              Áreas y Direcciones TI Dependientes
            </h3>
            <NuxtLink
              to="/areas-ti"
              class="text-xs font-bold text-sky-600 hover:text-sky-800 transition"
            >
              Gestionar Áreas TI →
            </NuxtLink>
          </div>

          <div v-if="!m.areas_ti || m.areas_ti.length === 0" class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-xs text-slate-400 italic">
            Este ministerio no tiene áreas de tecnología asignadas actualmente.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="area in m.areas_ti"
              :key="area.id"
              class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-sky-300 hover:shadow-md transition space-y-2"
            >
              <div class="flex items-start justify-between gap-2">
                <span class="font-mono text-[10px] font-bold text-slate-600 bg-slate-200/80 px-1.5 py-0.5 rounded">
                  {{ area.codigo }}
                </span>
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                  :class="area.es_homologada ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ area.es_homologada ? 'Homologada' : 'En Trámite' }}
                </span>
              </div>

              <h4 class="font-bold text-slate-900 text-xs">
                <NuxtLink :to="`/areas-ti/${area.id}`" class="hover:text-sky-600 transition">{{ area.nombre }}</NuxtLink>
              </h4>

              <!-- Máxima autoridad del área -->
              <div v-if="area.maxima_autoridad_vigente" class="text-[11px] text-slate-600 pt-1 border-t border-slate-200/60">
                <span class="text-amber-500 font-bold">⭐</span>
                <strong>{{ area.maxima_autoridad_vigente.persona?.apellido }}, {{ area.maxima_autoridad_vigente.persona?.nombre }}</strong>
                <span class="text-slate-400"> ({{ area.maxima_autoridad_vigente.tipo_rol?.nombre }})</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: CREAR / EDITAR MINISTERIO                                      -->
    <!-- ===================================================================== -->
    <div v-if="showModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <h2 class="text-base font-bold">
            {{ isEditing ? 'Editar Ministerio u Organismo' : 'Nuevo Ministerio u Organismo' }}
          </h2>
          <button @click="showModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveMinisterio" class="p-6 space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Código Único *</label>
              <input
                v-model="form.codigo"
                type="text"
                required
                placeholder="Ej: MS, ME, MEYF"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-slate-800 uppercase focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Sigla / Abreviatura</label>
              <input
                v-model="form.sigla"
                type="text"
                placeholder="Ej: MSALUD, DGCYE"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 uppercase focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Nombre Completo del Organismo *</label>
            <input
              v-model="form.nombre"
              type="text"
              required
              placeholder="Ej: Ministerio de Salud"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Orden de Prioridad Protocolar</label>
              <input
                v-model.number="form.orden_prioridad"
                type="number"
                min="0"
                placeholder="0"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div class="flex items-center pt-6">
              <label class="inline-flex items-center gap-2 cursor-pointer font-bold text-slate-700">
                <input
                  type="checkbox"
                  v-model="form.activo"
                  class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
                />
                Organismo Activo
              </label>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
            >
              {{ saving ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Crear Organismo') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

const { get, post, put, delete: del } = useApi()
const authStore = useAuthStore()
const { toast, confirmDialog } = useToast()

const ministerios = ref([])
const loading = ref(true)
const saving = ref(false)

const showModal = ref(false)
const isEditing = ref(false)

const filters = reactive({
  q: '',
  activo: '',
})

const form = reactive({
  id: null,
  codigo: '',
  nombre: '',
  sigla: '',
  orden_prioridad: 0,
  activo: true,
})

const totalAreasTi = computed(() => {
  return ministerios.value.reduce((acc, m) => {
    return acc + (m.areas_ti_count || (m.areas_ti ? m.areas_ti.length : 0))
  }, 0)
})

let debounceTimer = null
const debounceSearch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    fetchMinisterios()
  }, 350)
}

const fetchMinisterios = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.q) params.q = filters.q
    if (filters.activo !== '') params.activo = filters.activo

    const res = await get('/ministerios', params)
    if (res.success) {
      // Cargar cada ministerio con sus áreas asociadas
      const listWithDetails = await Promise.all(
        res.data.map(async (m) => {
          try {
            const detail = await get(`/ministerios/${m.id}`)
            return detail.success ? detail.data : m
          } catch (e) {
            return m
          }
        })
      )
      ministerios.value = listWithDetails
    }
  } catch (err) {
    console.error('Error cargando ministerios:', err)
    toast.error(err, { title: 'Error de Carga' })
  } finally {
    loading.value = false
  }
}

const openModalCreate = () => {
  isEditing.value = false
  Object.assign(form, {
    id: null,
    codigo: '',
    nombre: '',
    sigla: '',
    orden_prioridad: ministerios.value.length + 1,
    activo: true,
  })
  showModal.value = true
}

const openModalEdit = (m) => {
  isEditing.value = true
  Object.assign(form, {
    id: m.id,
    codigo: m.codigo,
    nombre: m.nombre,
    sigla: m.sigla || '',
    orden_prioridad: m.orden_prioridad || 0,
    activo: m.activo,
  })
  showModal.value = true
}

const saveMinisterio = async () => {
  saving.value = true
  try {
    if (isEditing.value) {
      const res = await put(`/ministerios/${form.id}`, form)
      if (res.success) {
        toast.success('Ministerio u organismo actualizado exitosamente.')
        showModal.value = false
        await fetchMinisterios()
      }
    } else {
      const res = await post('/ministerios', form)
      if (res.success) {
        toast.success('Nuevo ministerio u organismo registrado con éxito.')
        showModal.value = false
        await fetchMinisterios()
      }
    }
  } catch (err) {
    toast.error(err)
  } finally {
    saving.value = false
  }
}

const confirmDelete = async (m) => {
  const count = m.areas_ti ? m.areas_ti.length : (m.areas_ti_count || 0)
  if (count > 0) {
    toast.warning(
      `No es posible eliminar el ministerio "${m.nombre}" porque posee ${count} área(s) TI asociadas. Debe reasignar o eliminar dichas áreas primero.`,
      { title: 'Acción No Permitida' }
    )
    return
  }

  const confirmed = await confirmDialog({
    title: 'Eliminar Ministerio u Organismo',
    message: `¿Está seguro de que desea eliminar el ministerio "${m.nombre}" (${m.sigla || m.codigo})? Esta acción no se puede deshacer.`,
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/ministerios/${m.id}`)
      if (res.success) {
        toast.success('Ministerio eliminado exitosamente.')
        await fetchMinisterios()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

onMounted(() => {
  fetchMinisterios()
})
</script>
