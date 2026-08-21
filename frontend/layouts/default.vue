<template>
  <div class="flex h-screen bg-slate-100 overflow-hidden font-sans">
    <!-- Sidebar de Navegación -->
    <aside class="w-72 bg-slate-900 text-slate-200 flex flex-col justify-between shadow-xl flex-shrink-0 z-20">
      <div>
        <!-- Logotipo Institucional -->
        <div class="h-20 flex items-center px-6 bg-slate-950 border-b border-slate-800/80 gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-cyan-400 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-sky-500/20">
            {{ agency.acronym }}
          </div>
          <div class="truncate">
            <h1 class="font-extrabold text-sm tracking-wider uppercase text-white truncate">ERP {{ agency.shortName }}</h1>
            <p class="text-[11px] text-slate-400 font-medium truncate">{{ agency.tagline }}</p>
          </div>
        </div>

        <!-- Menú Principal -->
        <nav class="p-4 space-y-1.5">
          <NuxtLink
            to="/"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path === '/' ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path === '/' ? 'bg-white' : 'bg-slate-500'"></span>
            Panel de Control
          </NuxtLink>

          <div class="pt-4 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            Estructura y Personas
          </div>

          <NuxtLink
            to="/ministerios"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path === '/ministerios' ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path === '/ministerios' ? 'bg-white' : 'bg-slate-500'"></span>
            Ministerios
          </NuxtLink>

          <NuxtLink
            to="/areas-ti"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path.startsWith('/areas-ti') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path.startsWith('/areas-ti') ? 'bg-white' : 'bg-slate-500'"></span>
            Áreas TI Homologadas
          </NuxtLink>

          <NuxtLink
            to="/personas"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path.startsWith('/personas') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path.startsWith('/personas') ? 'bg-white' : 'bg-slate-500'"></span>
            Directorio de Personas
          </NuxtLink>

          <NuxtLink
            to="/roles"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path.startsWith('/roles') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path.startsWith('/roles') ? 'bg-white' : 'bg-slate-500'"></span>
            Búsqueda por Roles
          </NuxtLink>

          <div class="pt-4 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            Servicios y Documentos
          </div>

          <NuxtLink
            to="/servicios"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path.startsWith('/servicios') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path.startsWith('/servicios') ? 'bg-white' : 'bg-slate-500'"></span>
            Servicios Tecnológicos
          </NuxtLink>

          <div class="pt-4 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            Comunicaciones y Datos
          </div>

          <NuxtLink
            to="/exportador"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all"
            :class="route.path.startsWith('/exportador') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
          >
            <span class="w-2 h-2 rounded-full" :class="route.path.startsWith('/exportador') ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'"></span>
            Exportador de Contactos
          </NuxtLink>
        </nav>
      </div>

      <!-- Perfil de Usuario en Footer de Sidebar -->
      <div class="p-4 bg-slate-950/80 border-t border-slate-800/80">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
              {{ authStore.user?.name ? authStore.user.name.charAt(0) : 'U' }}
            </div>
            <div class="truncate">
              <p class="text-xs font-semibold text-white truncate">{{ authStore.user?.name || 'Usuario' }}</p>
              <span 
                class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase"
                :class="{
                  'bg-rose-950 text-rose-300 border border-rose-800': authStore.user?.perfil_acceso === 'ADMINISTRADOR',
                  'bg-amber-950 text-amber-300 border border-amber-800': authStore.user?.perfil_acceso === 'EDITOR',
                  'bg-slate-800 text-slate-300 border border-slate-700': authStore.user?.perfil_acceso === 'LECTOR'
                }"
              >
                {{ authStore.user?.perfil_acceso || 'LECTOR' }}
              </span>
            </div>
          </div>
          <button 
            @click="authStore.logout()"
            title="Cerrar Sesión"
            class="p-2 text-slate-400 hover:text-rose-400 hover:bg-slate-800/80 rounded-lg transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Topbar Header -->
      <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 z-10">
        <div class="flex items-center gap-3">
          <span class="text-xs font-bold text-sky-600 bg-sky-50 px-2.5 py-1 rounded-full border border-sky-100 uppercase tracking-wider">
            {{ agency.name }}
          </span>
        </div>

        <div class="flex items-center gap-4">
          <div class="text-right hidden sm:block">
            <p class="text-xs font-semibold text-slate-700">{{ agency.government }}</p>
            <p class="text-[11px] text-slate-400">Sistema Homologado de TI</p>
          </div>
          <div class="h-6 w-px bg-slate-200"></div>
          <div class="text-xs font-medium text-slate-500">
            v1.0.0
          </div>
        </div>
      </header>

      <!-- Vista de Página con scroll independiente -->
      <main class="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <slot />
      </main>
    </div>

    <!-- Contenedor Global de Notificaciones Toast y Modal de Confirmación -->
    <ToastContainer />
    <ConfirmModal />
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useAgency } from '~/composables/useAgency'

const route = useRoute()
const authStore = useAuthStore()
const agency = useAgency()

onMounted(() => {
  if (!authStore.isAuthenticated && route.path !== '/login') {
    navigateTo('/login')
  }
})
</script>
