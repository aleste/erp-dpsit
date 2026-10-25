<template>
  <div class="space-y-8 max-w-7xl mx-auto">
    <!-- Encabezado de Bienvenida -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Panel de Control y Gobierno TI</h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Resumen ejecutivo del ecosistema tecnológico, autoridades homologadas y provisión de servicios.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <NuxtLink
          to="/exportador"
          class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-600 text-white rounded-xl text-sm font-semibold shadow-md shadow-emerald-700/20 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Exportar Correos y Autoridades
        </NuxtLink>
      </div>
    </div>

    <!-- Indicadores Clave (KPIs) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <!-- Ministerios -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Ministerios</p>
          <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ stats.totales?.ministerios ?? '...' }}</h3>
          <p class="text-xs text-slate-500 mt-0.5">Organismos provinciales</p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold text-lg">
          🏛️
        </div>
      </div>

      <!-- Áreas Homologadas -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Áreas TI Homologadas</p>
          <h3 class="text-2xl font-extrabold text-primary-700 mt-1">
            {{ stats.totales?.areas_homologadas ?? '...' }} / {{ stats.totales?.areas_ti ?? '...' }}
          </h3>
          <p class="text-xs text-emerald-700 font-medium mt-0.5">Homologación formal {{ agency.shortName }}</p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-700 flex items-center justify-center font-bold text-lg">
          🏢
        </div>
      </div>

      <!-- Personas y Autoridades -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Máximas Autoridades TI</p>
          <h3 class="text-2xl font-extrabold text-slate-800 mt-1">
            {{ stats.totales?.maximas_autoridades ?? '...' }}
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">{{ stats.totales?.personas ?? '...' }} personas en padrón</p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-lg">
          👤
        </div>
      </div>

      <!-- Servicios Activos -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Servicios Aprovisionados</p>
          <h3 class="text-2xl font-extrabold text-emerald-700 mt-1">
            {{ stats.totales?.servicios_activos ?? '...' }}
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">Cloud, SSO e Interoperabilidad</p>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-lg">
          ⚡
        </div>
      </div>
    </div>

    <!-- Sección de Catálogo de Servicios y Asignaciones Recientes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Provisión por Tipo de Servicio -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm lg:col-span-1">
        <h2 class="text-base font-bold text-slate-900 mb-4">Catálogo de Servicios Gobernados</h2>
        <div class="space-y-4">
          <div
            v-for="srv in stats.servicios_por_tipo"
            :key="srv.id"
            class="flex items-center justify-between p-3.5 bg-slate-50 hover:bg-primary-50/50 rounded-xl border border-slate-100 transition"
          >
            <div>
              <p class="text-sm font-bold text-slate-800">{{ srv.nombre }}</p>
              <span class="inline-block mt-0.5 px-2 py-0.5 bg-slate-200/70 text-slate-600 rounded text-xs font-semibold">
                {{ srv.categoria }}
              </span>
            </div>
            <div class="text-right">
              <span class="text-sm font-extrabold text-primary-700">{{ srv.servicios_areas_count }}</span>
              <p class="text-xs text-slate-500">instancias</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Últimas Designaciones y Asignaciones -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-slate-900">Últimas Asignaciones de Roles</h2>
          <NuxtLink to="/roles" class="text-sm font-semibold text-primary-700 hover:text-primary-800">Ver todas →</NuxtLink>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-slate-500 font-semibold text-xs uppercase">
                <th class="py-2.5 px-3">Persona</th>
                <th class="py-2.5 px-3">Ministerio / Área</th>
                <th class="py-2.5 px-3">Rol</th>
                <th class="py-2.5 px-3">Vigencia</th>
                <th class="py-2.5 px-3 text-center">Autoridad</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-slate-700">
              <tr v-for="asig in stats.ultimas_asignaciones" :key="asig.id" class="hover:bg-slate-50/80 transition">
                <td class="py-3 px-3">
                  <p class="font-bold text-slate-800">{{ asig.persona?.apellido }}, {{ asig.persona?.nombre }}</p>
                  <p class="text-xs text-slate-500">{{ asig.persona?.email_principal }}</p>
                </td>
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-800">{{ asig.area_ti?.ministerio?.sigla }}</p>
                  <p class="text-xs text-slate-500">{{ asig.area_ti?.nombre }}</p>
                </td>
                <td class="py-3 px-3">
                  <span class="inline-block font-medium text-primary-700 bg-primary-50 px-2 py-0.5 rounded border border-primary-100">
                    {{ asig.tipo_rol?.nombre }}
                  </span>
                </td>
                <td class="py-3 px-3 whitespace-nowrap">
                  <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                    {{ asig.fecha_fin ? `Hasta ${asig.fecha_fin}` : 'Vigente' }}
                  </span>
                </td>
                <td class="py-3 px-3 text-center">
                  <span v-if="asig.es_maxima_autoridad" class="text-amber-500 font-bold" title="Máxima Autoridad">⭐</span>
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
import { ref, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAgency } from '~/composables/useAgency'

const { get } = useApi()
const agency = useAgency()
const stats = ref({
  totales: null,
  servicios_por_tipo: [],
  ultimas_asignaciones: [],
})

onMounted(async () => {
  try {
    const res = await get('/dashboard/stats')
    if (res.success) {
      stats.value = res.data
    }
  } catch (err) {
    console.error('Error cargando estadísticas del dashboard:', err)
  }
})
</script>
