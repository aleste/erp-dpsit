<template>
  <div
    aria-live="polite"
    class="fixed top-5 right-5 z-[9999] flex flex-col gap-3 w-full max-w-sm sm:max-w-md pointer-events-none px-4 sm:px-0"
  >
    <TransitionGroup
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-for="toast in notificationStore.toasts"
        :key="toast.id"
        class="pointer-events-auto w-full rounded-2xl shadow-xl border overflow-hidden backdrop-blur-md transition-all duration-200"
        :class="containerClasses(toast.type)"
        :role="toast.type === 'error' ? 'alert' : 'status'"
      >
        <div class="p-4 flex items-start gap-3.5">
          <!-- Ícono de Estado -->
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm"
            :class="iconContainerClasses(toast.type)"
          >
            <!-- Success Icon -->
            <svg
              v-if="toast.type === 'success'"
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>

            <!-- Error Icon -->
            <svg
              v-else-if="toast.type === 'error'"
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
            </svg>

            <!-- Warning Icon -->
            <svg
              v-else-if="toast.type === 'warning'"
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>

            <!-- Info Icon -->
            <svg
              v-else
              class="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>

          <!-- Contenido de Texto -->
          <div class="flex-1 min-w-0 pt-0.5">
            <h4
              v-if="toast.title"
              class="text-xs font-extrabold uppercase tracking-wider mb-0.5"
              :class="titleClasses(toast.type)"
            >
              {{ toast.title }}
            </h4>
            <p class="text-xs font-medium leading-relaxed" :class="messageClasses(toast.type)">
              {{ toast.message }}
            </p>

            <!-- Lista de Errores de Validación (si corresponde) -->
            <ul v-if="toast.details && toast.details.length > 0" class="mt-2 space-y-1 text-xs">
              <li
                v-for="(detail, idx) in toast.details"
                :key="idx"
                class="flex items-start gap-1.5 text-rose-200"
              >
                <span class="text-rose-400 font-bold">•</span>
                <span>{{ detail }}</span>
              </li>
            </ul>
          </div>

          <!-- Botón de Cierre -->
          <button
            @click="notificationStore.removeToast(toast.id)"
            class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition flex-shrink-0"
            title="Cerrar notificación"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Barra de Progreso de Auto-cierre -->
        <div
          v-if="toast.duration && toast.duration > 0"
          class="h-1 w-full bg-black/20 overflow-hidden"
        >
          <div
            class="h-full origin-left"
            :class="progressBarClasses(toast.type)"
            :style="{
              animation: `shrinkProgress ${toast.duration}ms linear forwards`,
            }"
          ></div>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { useNotificationStore, type ToastType } from '~/stores/notification'

const notificationStore = useNotificationStore()

const containerClasses = (type: ToastType) => {
  switch (type) {
    case 'success':
      return 'bg-slate-900/95 text-slate-100 border-emerald-500/40 shadow-emerald-950/40'
    case 'error':
      return 'bg-slate-900/95 text-slate-100 border-rose-500/40 shadow-rose-950/40'
    case 'warning':
      return 'bg-slate-900/95 text-slate-100 border-amber-500/40 shadow-amber-950/40'
    case 'info':
    default:
      return 'bg-slate-900/95 text-slate-100 border-sky-500/40 shadow-sky-950/40'
  }
}

const iconContainerClasses = (type: ToastType) => {
  switch (type) {
    case 'success':
      return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
    case 'error':
      return 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
    case 'warning':
      return 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
    case 'info':
    default:
      return 'bg-sky-500/20 text-sky-400 border border-sky-500/30'
  }
}

const titleClasses = (type: ToastType) => {
  switch (type) {
    case 'success':
      return 'text-emerald-400'
    case 'error':
      return 'text-rose-400'
    case 'warning':
      return 'text-amber-400'
    case 'info':
    default:
      return 'text-sky-400'
  }
}

const messageClasses = (type: ToastType) => {
  return 'text-slate-200'
}

const progressBarClasses = (type: ToastType) => {
  switch (type) {
    case 'success':
      return 'bg-emerald-500'
    case 'error':
      return 'bg-rose-500'
    case 'warning':
      return 'bg-amber-500'
    case 'info':
    default:
      return 'bg-sky-500'
  }
}
</script>

<style scoped>
@keyframes shrinkProgress {
  from {
    width: 100%;
  }
  to {
    width: 0%;
  }
}
</style>
