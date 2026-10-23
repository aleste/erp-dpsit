import { defineStore } from 'pinia'

interface User {
  id: number
  name: string
  email: string
  perfil_acceso: 'ADMINISTRADOR' | 'EDITOR' | 'LECTOR'
  persona_id?: number | null
  // Rol efectivo por módulo (D-26), p. ej. { EXPEDIENTES: 'OPERADOR' }
  modulos?: Record<string, string>
}

// Roles de cada módulo, de menor a mayor (igual que UserModulo::ROLES en el backend)
const ROLES_MODULO: Record<string, string[]> = {
  EXPEDIENTES: ['CONSULTA', 'OPERADOR', 'SUPERVISOR'],
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
    rolModulo: (state) => (modulo: string): string | null => state.user?.modulos?.[modulo] ?? null,
    // Solo para mostrar u ocultar opciones: la autorización real la aplica el backend
    puedeModulo: (state) => (modulo: string, rolMinimo: string): boolean => {
      const roles = ROLES_MODULO[modulo] || []
      const actual = roles.indexOf(state.user?.modulos?.[modulo] ?? '')
      return actual >= 0 && actual >= roles.indexOf(rolMinimo)
    },
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

    // Actualiza los datos del usuario (p. ej. roles por módulo asignados después del login)
    async refrescarUsuario() {
      if (!this.token) return
      const config = useRuntimeConfig()
      try {
        const res: any = await $fetch(`${config.public.apiBase}/auth/user`, {
          headers: { Authorization: `Bearer ${this.token}`, Accept: 'application/json' },
        })
        if (res?.success && res.user) {
          const { id, name, email, perfil_acceso, persona_id, modulos } = res.user
          this.setAuth({ id, name, email, perfil_acceso, persona_id, modulos: Array.isArray(modulos) ? {} : modulos }, this.token)
        }
      } catch (e: any) {
        if (e?.status === 401) this.logout()
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
