<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Módulo de Exportación y Notificación Masiva</h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Filtrado y extracción personalizada de listas de correos y autoridades para comunicaciones oficiales y campañas institucionales.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="downloadCsv"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-700/30 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Descargar Archivo CSV
        </button>
      </div>
    </div>

    <!-- Panel de Filtros Personalizados -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-6">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-500">Configuración de Filtros de Extracción</h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- 1. Filtro por Tipos de Rol -->
        <div class="space-y-2">
          <label class="block text-sm font-bold text-slate-700">Roles / Cargos Seleccionados</label>
          <div class="max-h-48 overflow-y-auto space-y-1.5 p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm custom-scrollbar">
            <label
              v-for="r in tiposRol"
              :key="r.id"
              class="flex items-center gap-2 cursor-pointer hover:bg-slate-100 p-1 rounded transition"
            >
              <input
                type="checkbox"
                :value="r.id"
                v-model="filters.tipo_rol_ids"
                @change="fetchPreview"
                class="w-4 h-4 text-primary-700 rounded border-slate-300 focus:ring-primary-600"
              />
              <span class="text-slate-800">{{ r.nombre }}</span>
            </label>
          </div>
        </div>

        <!-- 2. Filtro por Ministerios -->
        <div class="space-y-2">
          <label class="block text-sm font-bold text-slate-700">Ministerios / Organismos</label>
          <div class="max-h-48 overflow-y-auto space-y-1.5 p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm custom-scrollbar">
            <label
              v-for="m in ministerios"
              :key="m.id"
              class="flex items-center gap-2 cursor-pointer hover:bg-slate-100 p-1 rounded transition"
            >
              <input
                type="checkbox"
                :value="m.id"
                v-model="filters.ministerio_ids"
                @change="fetchPreview"
                class="w-4 h-4 text-primary-700 rounded border-slate-300 focus:ring-primary-600"
              />
              <span class="text-slate-800 font-semibold">{{ m.sigla }}</span>
              <span class="text-slate-500 truncate">- {{ m.nombre }}</span>
            </label>
          </div>
        </div>

        <!-- 3. Parámetros Especiales de Extracción -->
        <div class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-200 text-sm">
          <label class="block font-bold text-slate-700">Criterios de Autoridad y Correo</label>

          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              v-model="filters.solo_maximas_autoridades"
              @change="fetchPreview"
              class="w-4 h-4 text-amber-500 rounded border-slate-300 focus:ring-amber-600"
            />
            <span class="font-bold text-slate-800">⭐ Solo Máximas Autoridades TI</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              v-model="filters.solo_vigentes"
              @change="fetchPreview"
              class="w-4 h-4 text-emerald-700 rounded border-slate-300 focus:ring-emerald-600"
            />
            <span class="text-slate-700">Solo Asignaciones Vigentes Actuales</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              v-model="filters.solo_homologadas"
              @change="fetchPreview"
              class="w-4 h-4 text-primary-700 rounded border-slate-300 focus:ring-primary-600"
            />
            <span class="text-slate-700">Solo Áreas Homologadas {{ agency.shortName }}</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer pt-2 border-t border-slate-200">
            <input
              type="checkbox"
              v-model="filters.incluir_alternativos"
              @change="fetchPreview"
              class="w-4 h-4 text-indigo-700 rounded border-slate-300 focus:ring-indigo-600"
            />
            <span class="text-indigo-900 font-semibold">Incluir Correos Alternativos</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Resultados y Vista Previa -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Resumen de Destinatarios y Copia Rápida -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-slate-900">Resumen de Destinatarios</h3>

        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="p-3 bg-primary-50 rounded-xl border border-primary-100">
            <p class="text-xs font-bold text-primary-700 uppercase">Contactos</p>
            <p class="text-2xl font-extrabold text-primary-900 mt-1">{{ previewData.total_registros || 0 }}</p>
          </div>
          <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
            <p class="text-xs font-bold text-emerald-700 uppercase">Emails Únicos</p>
            <p class="text-2xl font-extrabold text-emerald-900 mt-1">{{ previewData.total_emails_unicos || 0 }}</p>
          </div>
        </div>

        <div>
          <label for="exportador-campo" class="block text-sm font-bold text-slate-500 mb-1.5">Lista de Correos para Notificación</label>
          <textarea id="exportador-campo"
            readonly
            :value="emailsFormatted"
            rows="6"
            placeholder="No hay correos para los filtros seleccionados"
            class="w-full p-2.5 bg-slate-900 text-cyan-300 font-mono text-xs rounded-xl border border-slate-800 focus:outline-none custom-scrollbar"
          ></textarea>
        </div>

        <button
          @click="copyToClipboard"
          :disabled="!emailsFormatted"
          class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-semibold transition disabled:opacity-40"
        >
          {{ copied ? '✓ ¡Correos Copiados al Portapapeles!' : '📋 Copiar Lista de Correos (CCO / BCC)' }}
        </button>
      </div>

      <!-- Tabla de Previsualización de Registros -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm lg:col-span-2 overflow-hidden flex flex-col">
        <h3 class="text-base font-bold text-slate-900 mb-4">
          Previsualización de Registros a Exportar (Primeros 50)
        </h3>

        <div class="overflow-x-auto flex-1 custom-scrollbar">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase">
                <th class="py-2.5 px-3">Autoridad / Referente</th>
                <th class="py-2.5 px-3">Correo Principal</th>
                <th class="py-2.5 px-3">Organismo / Rol</th>
                <th class="py-2.5 px-3 text-center">Autoridad</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="item in previewData.data" :key="item.persona_id + '-' + item.rol_codigo" class="hover:bg-slate-50/80">
                <td class="py-2.5 px-3 font-bold text-slate-900">
                  {{ item.nombre_completo }}
                </td>
                <td class="py-2.5 px-3 text-primary-700 font-medium">
                  {{ item.email_principal }}
                </td>
                <td class="py-2.5 px-3">
                  <p class="font-semibold text-slate-800">{{ item.ministerio_sigla }}</p>
                  <p class="text-xs text-slate-500">{{ item.rol }}</p>
                </td>
                <td class="py-2.5 px-3 text-center">
                  <span v-if="item.es_maxima_autoridad" class="text-amber-500 font-bold">⭐</span>
                  <span v-else class="text-slate-300">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useAgency } from '~/composables/useAgency'
import { useToast } from '~/composables/useToast'

const { get, post, descargar } = useApi()
const authStore = useAuthStore()
const agency = useAgency()
const { toast } = useToast()

const tiposRol = ref([])
const ministerios = ref([])
const copied = ref(false)

const filters = reactive({
  tipo_rol_ids: [],
  ministerio_ids: [],
  solo_maximas_autoridades: false,
  solo_vigentes: true,
  solo_homologadas: true,
  incluir_alternativos: false,
})

const previewData = ref({
  total_registros: 0,
  total_emails_unicos: 0,
  data: [],
  emails_unicos: [],
})

const emailsFormatted = computed(() => {
  if (!previewData.value.emails_unicos) return ''
  return previewData.value.emails_unicos.map(e => e.email).join(', ')
})

const fetchPreview = async () => {
  try {
    const res = await post('/export/preview', filters)
    if (res.success) {
      previewData.value = res
    }
  } catch (err) {
    console.error('Error fetching export preview:', err)
    toast.error(err, { title: 'Error en Vista Previa' })
  }
}

const copyToClipboard = () => {
  if (!emailsFormatted.value) {
    toast.warning('No hay correos electrónicos para copiar con los filtros actuales.')
    return
  }

  if (navigator.clipboard) {
    navigator.clipboard.writeText(emailsFormatted.value)
    copied.value = true
    toast.success(`${previewData.value.total_emails_unicos || 0} correos únicos copiados al portapapeles.`, { title: 'Copiado' })
    setTimeout(() => {
      copied.value = false
    }, 2500)
  }
}

const downloadCsv = async () => {
  if (!previewData.value.total_registros || previewData.value.total_registros === 0) {
    toast.warning('No hay registros para exportar con los filtros seleccionados.', { title: 'Exportación Vacía' })
    return
  }

  toast.info('Generando y descargando archivo CSV...', { title: 'Exportación Iniciada' })

  const params = {
    tipo_rol_ids: filters.tipo_rol_ids.join(','),
    ministerio_ids: filters.ministerio_ids.join(','),
    solo_maximas_autoridades: filters.solo_maximas_autoridades ? '1' : '',
    solo_vigentes: filters.solo_vigentes ? '1' : '',
    solo_homologadas: filters.solo_homologadas ? '1' : '',
  }

  try {
    await descargar('/export/download', `contactos_autoridades_${new Date().toISOString().slice(0, 10)}.csv`, params)
  } catch (err) {
    toast.error(err, { title: 'Error al exportar' })
  }
}

onMounted(async () => {
  try {
    const [resRoles, resMin] = await Promise.all([
      get('/tipos-rol'),
      get('/ministerios'),
    ])
    if (resRoles.success) tiposRol.value = resRoles.data
    if (resMin.success) ministerios.value = resMin.data
    
    await fetchPreview()
  } catch (err) {
    console.error('Error inicializando exportador:', err)
  }
})
</script>
