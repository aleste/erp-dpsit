<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modal.isOpen"
        class="fixed inset-0 z-[10000] overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="handleCancel"
        @keydown.esc="handleCancel"
        tabindex="-1"
      >
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden"
            role="dialog"
            aria-modal="true"
            :aria-label="modal.title"
          >
            <!-- Header con Ícono y Título -->
            <div class="p-6 pb-4 flex items-start gap-4">
              <!-- Icono dinámico según el tipo -->
              <div
                class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm"
                :class="iconBgClass"
              >
                <!-- Danger Icon (Trash / Alert) -->
                <svg
                  v-if="modal.type === 'danger'"
                  class="w-6 h-6 text-rose-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>

                <!-- Warning Icon -->
                <svg
                  v-else-if="modal.type === 'warning'"
                  class="w-6 h-6 text-amber-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </svg>

                <!-- Info Icon -->
                <svg
                  v-else
                  class="w-6 h-6 text-sky-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>

              <div class="flex-1 min-w-0">
                <h3 class="text-base font-extrabold text-slate-900 leading-tight">
                  {{ modal.title }}
                </h3>
                <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                  {{ modal.message }}
                </p>
              </div>
            </div>

            <!-- Footer con Botones de Acción -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
              <button
                type="button"
                @click="handleCancel"
                class="px-4 py-2.5 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition focus:outline-none focus:ring-2 focus:ring-slate-400"
              >
                {{ modal.cancelText || 'Cancelar' }}
              </button>

              <button
                type="button"
                @click="handleConfirm"
                class="px-5 py-2.5 text-white text-xs font-bold rounded-xl shadow-md transition focus:outline-none focus:ring-2"
                :class="confirmBtnClass"
              >
                {{ modal.confirmText || 'Confirmar' }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useNotificationStore } from '~/stores/notification'

const notificationStore = useNotificationStore()
const modal = computed(() => notificationStore.confirmModal)

const iconBgClass = computed(() => {
  switch (modal.value.type) {
    case 'danger':
      return 'bg-rose-50 border border-rose-100'
    case 'warning':
      return 'bg-amber-50 border border-amber-100'
    case 'info':
    default:
      return 'bg-sky-50 border border-sky-100'
  }
})

const confirmBtnClass = computed(() => {
  switch (modal.value.type) {
    case 'danger':
      return 'bg-rose-600 hover:bg-rose-500 shadow-rose-600/30 focus:ring-rose-500'
    case 'warning':
      return 'bg-amber-600 hover:bg-amber-500 shadow-amber-600/30 focus:ring-amber-500'
    case 'info':
    default:
      return 'bg-sky-600 hover:bg-sky-500 shadow-sky-600/30 focus:ring-sky-500'
  }
})

const handleConfirm = () => {
  notificationStore.handleConfirm()
}

const handleCancel = () => {
  notificationStore.handleCancel()
}
</script>
