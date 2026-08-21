import { defineStore } from 'pinia'

interface User {
  id: number
  name: string
  email: string
  perfil_acceso: 'ADMINISTRADOR' | 'EDITOR' | 'LECTOR'
  persona_id?: number | null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: (typeof window !== 'undefined' && localStorage.getItem('dpsit_user'))
      ? JSON.parse(localStorage.getItem('dpsit_user') || 'null') as User | null
      : null,
    token: (typeof window !== 'undefined' && localStorage.getItem('dpsit_token'))
      ? localStorage.getItem('dpsit_token')
      : null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.perfil_acceso === 'ADMINISTRADOR',
    isEditor: (state) => state.user?.perfil_acceso === 'ADMINISTRADOR' || state.user?.perfil_acceso === 'EDITOR',
    isLector: (state) => !!state.user,
    userProfileLabel: (state) => {
      switch (state.user?.perfil_acceso) {
        case 'ADMINISTRADOR': return 'Administrador Provincial'
        case 'EDITOR': return 'Editor / Gestor TI'
        case 'LECTOR': return 'Lector / Auditor'
        default: return 'Invitado'
      }
    }
  },

  actions: {
    setAuth(user: User, token: string) {
      this.user = user
      this.token = token
      if (typeof window !== 'undefined') {
        localStorage.setItem('dpsit_user', JSON.stringify(user))
        localStorage.setItem('dpsit_token', token)
      }
    },

    async logout() {
      const config = useRuntimeConfig()
      if (this.token) {
        try {
          await $fetch(`${config.public.apiBase}/auth/logout`, {
            method: 'POST',
            headers: {
              Authorization: `Bearer ${this.token}`,
              Accept: 'application/json',
            }
          })
        } catch (e) {
          // Ignorar error al desloguear
        }
      }

      this.user = null
      this.token = null
      if (typeof window !== 'undefined') {
        localStorage.removeItem('dpsit_user')
        localStorage.removeItem('dpsit_token')
      }
      navigateTo('/login')
    }
  }
})
