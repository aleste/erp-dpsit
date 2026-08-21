import { defineStore } from 'pinia'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface ToastItem {
  id: string
  type: ToastType
  title?: string
  message: string
  details?: string[]
  duration?: number
  createdAt: number
}

export interface ConfirmDialogOptions {
  title?: string
  message: string
  confirmText?: string
  cancelText?: string
  type?: 'danger' | 'warning' | 'info'
}

export interface ConfirmState {
  isOpen: boolean
  title: string
  message: string
  confirmText: string
  cancelText: string
  type: 'danger' | 'warning' | 'info'
  resolve: ((value: boolean) => void) | null
}

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    toasts: [] as ToastItem[],
    confirmModal: {
      isOpen: false,
      title: 'Confirmar acción',
      message: '¿Está seguro de que desea continuar?',
      confirmText: 'Confirmar',
      cancelText: 'Cancelar',
      type: 'danger',
      resolve: null,
    } as ConfirmState,
  }),

  actions: {
    /**
     * Agrega un nuevo Toast a la cola
     */
    addToast(toast: {
      type?: ToastType
      title?: string
      message: string
      details?: string[]
      duration?: number
    }): string {
      const id = 'toast_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7)
      const duration = toast.duration !== undefined ? toast.duration : (toast.type === 'error' ? 7000 : 4500)

      const newToast: ToastItem = {
        id,
        type: toast.type || 'info',
        title: toast.title,
        message: toast.message,
        details: toast.details,
        duration,
        createdAt: Date.now(),
      }

      this.toasts.push(newToast)

      if (duration > 0) {
        setTimeout(() => {
          this.removeToast(id)
        }, duration)
      }

      return id
    },

    /**
     * Remueve un Toast específico por ID
     */
    removeToast(id: string) {
      const index = this.toasts.findIndex((t) => t.id === id)
      if (index !== -1) {
        this.toasts.splice(index, 1)
      }
    },

    /**
     * Limpia todos los Toasts activos
     */
    clearToasts() {
      this.toasts = []
    },

    /**
     * Abre un cuadro de diálogo modal para confirmar acciones destructivas o críticas, devolviendo una Promise<boolean>
     */
    openConfirm(options: string | ConfirmDialogOptions): Promise<boolean> {
      return new Promise<boolean>((resolve) => {
        if (typeof options === 'string') {
          this.confirmModal = {
            isOpen: true,
            title: 'Confirmar acción',
            message: options,
            confirmText: 'Confirmar',
            cancelText: 'Cancelar',
            type: 'danger',
            resolve,
          }
        } else {
          this.confirmModal = {
            isOpen: true,
            title: options.title || 'Confirmar acción',
            message: options.message,
            confirmText: options.confirmText || 'Confirmar',
            cancelText: options.cancelText || 'Cancelar',
            type: options.type || 'danger',
            resolve,
          }
        }
      })
    },

    /**
     * Acción de confirmación positiva en el modal
     */
    handleConfirm() {
      if (this.confirmModal.resolve) {
        this.confirmModal.resolve(true)
      }
      this.confirmModal.isOpen = false
      this.confirmModal.resolve = null
    },

    /**
     * Acción de cancelación o cierre en el modal
     */
    handleCancel() {
      if (this.confirmModal.resolve) {
        this.confirmModal.resolve(false)
      }
      this.confirmModal.isOpen = false
      this.confirmModal.resolve = null
    },
  },
})
