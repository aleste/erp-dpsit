<template>
  <div class="flex h-screen bg-slate-100 overflow-hidden font-sans">
    <!-- Sidebar de Navegación Colapsable -->
    <aside
      class="bg-slate-900 text-slate-200 flex flex-col justify-between shadow-xl flex-shrink-0 z-20 transition-all duration-300 ease-in-out select-none"
      :class="isCollapsed ? 'w-20' : 'w-72'"
    >
      <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Logotipo Institucional y Botón de Colapso Interno -->
        <div
          class="h-20 flex items-center bg-slate-950 border-b border-slate-800/80 transition-all duration-300"
          :class="isCollapsed ? 'justify-center px-2' : 'justify-between px-5'"
        >
          <!-- Logotipo Expandido -->
          <NuxtLink to="/" class="flex items-center gap-3 overflow-hidden group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-cyan-400 flex items-center justify-center text-white font-extrabold text-sm shadow-lg shadow-sky-500/20 flex-shrink-0 group-hover:scale-105 transition-transform">
              {{ agency.acronym }}
            </div>
            <div v-show="!isCollapsed" class="truncate transition-opacity duration-300">
              <h1 class="font-extrabold text-sm tracking-wider uppercase text-white truncate">ERP {{ agency.shortName }}</h1>
              <p class="text-[11px] text-slate-400 font-medium truncate">{{ agency.tagline }}</p>
            </div>
          </NuxtLink>

          <!-- Botón de Colapso (En modo expandido) -->
          <button
            v-show="!isCollapsed"
            @click="toggleSidebar"
            class="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800/80 rounded-lg transition"
            title="Plegar menú lateral"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
          </button>
        </div>

        <!-- Menú Principal con Íconos y Tooltips Flotantes -->
        <nav class="p-3 space-y-1 overflow-y-auto flex-1 custom-scrollbar">
          <!-- 1. Panel de Control -->
          <div class="relative group/item">
            <NuxtLink
              to="/"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path === '/' ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Panel de Control</span>
            </NuxtLink>

            <!-- Tooltip Flotante en Modo Plegado -->
            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50 flex items-center gap-1.5"
            >
              <span>Panel de Control</span>
            </div>
          </div>

          <!-- SECCIÓN: Estructura y Personas -->
          <div v-show="!isCollapsed" class="pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">
            Estructura y Personas
          </div>
          <div v-show="isCollapsed" class="my-2 border-t border-slate-800/80"></div>

          <!-- 2. Ministerios -->
          <div class="relative group/item">
            <NuxtLink
              to="/ministerios"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path === '/ministerios' ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Ministerios</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Ministerios</span>
            </div>
          </div>

          <!-- 3. Áreas TI Homologadas -->
          <div class="relative group/item">
            <NuxtLink
              to="/areas-ti"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path.startsWith('/areas-ti') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Áreas TI Homologadas</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Áreas TI Homologadas</span>
            </div>
          </div>

          <!-- 4. Directorio de Personas -->
          <div class="relative group/item">
            <NuxtLink
              to="/personas"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path.startsWith('/personas') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Directorio de Personas</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Directorio de Personas</span>
            </div>
          </div>

          <!-- 5. Búsqueda por Roles -->
          <div class="relative group/item">
            <NuxtLink
              to="/roles"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path.startsWith('/roles') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Búsqueda por Roles</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Búsqueda por Roles</span>
            </div>
          </div>

          <!-- SECCIÓN: Servicios y Documentos -->
          <div v-show="!isCollapsed" class="pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">
            Servicios y Documentos
          </div>
          <div v-show="isCollapsed" class="my-2 border-t border-slate-800/80"></div>

          <!-- 6. Servicios Tecnológicos -->
          <div class="relative group/item">
            <NuxtLink
              to="/servicios"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path.startsWith('/servicios') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Servicios Tecnológicos</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Servicios Tecnológicos</span>
            </div>
          </div>

          <!-- 6b. Firma Digital (Tokens) -->
          <div class="relative group/item">
            <NuxtLink
              to="/firma-digital"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path.startsWith('/firma-digital') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Firma Digital (Tokens)</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Firma Digital (Tokens)</span>
            </div>
          </div>

          <!-- SECCIÓN: Comunicaciones y Datos -->
          <div v-show="!isCollapsed" class="pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">
            Comunicaciones y Datos
          </div>
          <div v-show="isCollapsed" class="my-2 border-t border-slate-800/80"></div>

          <!-- 7. Exportador de Contactos -->
          <div class="relative group/item">
            <NuxtLink
              to="/exportador"
              class="flex items-center rounded-xl text-xs font-semibold transition-all"
              :class="[
                route.path.startsWith('/exportador') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white',
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3.5 py-2.5'
              ]"
            >
              <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              <span v-show="!isCollapsed" class="truncate">Exportador de Contactos</span>
            </NuxtLink>

            <div
              v-if="isCollapsed"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-950 text-white text-xs font-bold rounded-lg shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/item:opacity-100 pointer-events-none transition-all duration-150 z-50"
            >
              <span>Exportador de Contactos</span>
            </div>
          </div>
        </nav>
      </div>

      <!-- Perfil de Usuario en Footer de Sidebar -->
      <div
        class="bg-slate-950/80 border-t border-slate-800/80 transition-all duration-300"
        :class="isCollapsed ? 'p-3' : 'p-4'"
      >
        <!-- Vista Expandida del Usuario -->
        <div v-show="!isCollapsed" class="flex items-center justify-between">
          <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-white font-bold text-xs flex-shrink-0 shadow-sm">
              {{ authStore.user?.name ? authStore.user.name.charAt(0) : 'U' }}
            </div>
            <div class="truncate">
              <p class="text-xs font-semibold text-white truncate">{{ authStore.user?.name || 'Usuario' }}</p>
              <span 
                class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase mt-0.5"
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
            class="p-2 text-slate-400 hover:text-rose-400 hover:bg-slate-800/80 rounded-lg transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </button>
        </div>

        <!-- Vista Plegada del Usuario -->
        <div v-show="isCollapsed" class="flex flex-col items-center gap-2 relative group/user">
          <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-white font-bold text-xs flex-shrink-0 cursor-pointer shadow-sm">
            {{ authStore.user?.name ? authStore.user.name.charAt(0) : 'U' }}
          </div>

          <button 
            @click="authStore.logout()"
            title="Cerrar Sesión"
            class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </button>

          <!-- Tooltip Perfil -->
          <div
            class="absolute left-full bottom-0 ml-3 px-3 py-2 bg-slate-950 text-white text-xs rounded-xl shadow-xl border border-slate-700 whitespace-nowrap opacity-0 group-hover/user:opacity-100 pointer-events-none transition-all duration-150 z-50"
          >
            <p class="font-bold">{{ authStore.user?.name || 'Usuario' }}</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider">{{ authStore.user?.perfil_acceso || 'LECTOR' }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Topbar Header con Botón Toggle de Sidebar -->
      <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 z-10">
        <div class="flex items-center gap-3">
          <!-- Botón Toggle de Menú Lateral -->
          <button
            @click="toggleSidebar"
            class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition"
            :title="isCollapsed ? 'Desplegar menú lateral' : 'Plegar menú lateral'"
          >
            <svg v-if="!isCollapsed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <span class="text-xs font-bold text-sky-700 bg-sky-50 px-3 py-1 rounded-full border border-sky-100 uppercase tracking-wider truncate max-w-md hidden sm:inline-block">
            {{ agency.name }}
          </span>
        </div>

        <div class="flex items-center gap-4">
          <div class="text-right hidden md:block">
            <p class="text-xs font-semibold text-slate-700">{{ agency.government }}</p>
            <p class="text-[11px] text-slate-400">Sistema Homologado de TI</p>
          </div>
          <div class="h-6 w-px bg-slate-200 hidden md:block"></div>
          <div class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg">
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
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useAgency } from '~/composables/useAgency'

const route = useRoute()
const authStore = useAuthStore()
const agency = useAgency()

const isCollapsed = ref(false)

const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value
  if (import.meta.client) {
    localStorage.setItem('sidebar_collapsed', isCollapsed.value ? 'true' : 'false')
  }
}

onMounted(() => {
  if (import.meta.client) {
    const savedState = localStorage.getItem('sidebar_collapsed')
    if (savedState !== null) {
      isCollapsed.value = savedState === 'true'
    }
  }

  if (!authStore.isAuthenticated && route.path !== '/login') {
    navigateTo('/login')
  }
})
</script>
