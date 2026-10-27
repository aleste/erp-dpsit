import { defineStore } from 'pinia'

interface User {
  id: number
  name: string
  email: string
  perfil_acceso: 'ADMINISTRADOR' | 'USUARIO'
  persona_id?: number | null
  // Rol efectivo por módulo (D-26, D-34), p. ej. { EXPEDIENTES: 'OPERADOR' }
  modulos?: Record<string, string>
}

// Módulo del catálogo que publica el backend (UserModulo::catalogo)
export interface ModuloCatalogo {
  clave: string
  nombre: string
  roles: string[]
}

// Escala común de roles, de menor a mayor (igual que UserModulo::ESCALA en el backend)
const ESCALA = ['CONSULTA', 'OPERADOR', 'SUPERVISOR']

const leer = <T>(clave: string, defecto: T): T => {
  if (typeof window === 'undefined') return defecto
  try {
    const valor = localStorage.getItem(clave)
    return valor ? JSON.parse(valor) as T : defecto
  } catch {
    return defecto
  }
}

// Intervalo mínimo entre refrescos de permisos durante la navegación
const REFRESCO_MS = 60_000

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: leer<User | null>('dpsit_user', null),
    token: (typeof window !== 'undefined' && localStorage.getItem('dpsit_token'))
      ? localStorage.getItem('dpsit_token')
      : null,
    catalogo: leer<ModuloCatalogo[]>('dpsit_modulos', []),
    ultimoRefresco: 0,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.perfil_acceso === 'ADMINISTRADOR',
    rolModulo: (state) => (modulo: string): string | null => state.user?.modulos?.[modulo] ?? null,
    // Solo para mostrar u ocultar opciones: la autorización real la aplica el backend.
    // `modulos` admite alternativas separadas por «|», como el middleware `modulo:` del backend.
    puedeModulo: (state) => (modulos: string, rolMinimo = 'CONSULTA'): boolean => {
      const requerido = ESCALA.indexOf(rolMinimo)
      return modulos.split('|').some((m) => {
        const rol = state.user?.modulos?.[m]
        return !!rol && ESCALA.indexOf(rol) >= requerido
      })
    },
    nombreModulo: (state) => (clave: string): string => state.catalogo.find(m => m.clave === clave)?.nombre || clave,
    userProfileLabel: (state) => {
      switch (state.user?.perfil_acceso) {
        case 'ADMINISTRADOR': return 'Administrador'
        case 'USUARIO': return 'Usuario'
        default: return 'Invitado'
      }
    }
  },

  actions: {
    setAuth(user: User, token: string, catalogo?: ModuloCatalogo[]) {
      this.user = { ...user, modulos: Array.isArray(user.modulos) ? {} : (user.modulos || {}) }
      this.token = token
      if (catalogo) this.catalogo = catalogo
      this.ultimoRefresco = Date.now()
      if (typeof window !== 'undefined') {
        localStorage.setItem('dpsit_user', JSON.stringify(this.user))
        localStorage.setItem('dpsit_token', token)
        localStorage.setItem('dpsit_modulos', JSON.stringify(this.catalogo))
      }
    },

    // Trae perfil y roles por módulo vigentes: un cambio de permisos se refleja sin volver a iniciar sesión.
    // Con `siVencido`, no consulta si ya lo hizo hace menos de REFRESCO_MS.
    async refrescarUsuario(siVencido = false) {
      if (!this.token) return
      if (siVencido && Date.now() - this.ultimoRefresco < REFRESCO_MS) return
      this.ultimoRefresco = Date.now()
      const config = useRuntimeConfig()
      try {
        const res: any = await $fetch(`${config.public.apiBase}/auth/user`, {
          headers: { Authorization: `Bearer ${this.token}`, Accept: 'application/json' },
        })
        if (res?.success && res.user) {
          const { id, name, email, perfil_acceso, persona_id, modulos } = res.user
          this.setAuth({ id, name, email, perfil_acceso, persona_id, modulos }, this.token, res.catalogo_modulos)
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
      this.catalogo = []
      if (typeof window !== 'undefined') {
        localStorage.removeItem('dpsit_user')
        localStorage.removeItem('dpsit_token')
        localStorage.removeItem('dpsit_modulos')
      }
      navigateTo('/login')
    }
  }
})
