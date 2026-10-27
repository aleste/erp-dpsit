import { useAuthStore } from '~/stores/auth'
import { useModulos } from '~/composables/useModulos'
import { useToast } from '~/composables/useToast'

/**
 * Guarda de navegación (D-34): sin sesión, al login; sin el módulo de la ruta, al inicio con un aviso.
 * Es solo de experiencia de uso: el backend rechaza igualmente toda llamada sin permiso.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore()

  if (to.path === '/login') return

  if (!authStore.isAuthenticated) {
    return navigateTo('/login')
  }

  // Permisos vigentes antes de decidir (como máximo una consulta por minuto)
  await authStore.refrescarUsuario(true)
  if (!authStore.isAuthenticated) return navigateTo('/login')

  const { puedeVerRuta, moduloDeRuta } = useModulos()
  if (!puedeVerRuta(to.path)) {
    const modulo = moduloDeRuta(to.path)
    useToast().toast.error(
      modulo === 'ADMINISTRADOR'
        ? 'Esa sección está reservada al administrador.'
        : `Su usuario no tiene acceso al módulo ${authStore.nombreModulo(modulo || '')}.`,
      { title: 'Acceso denegado' },
    )
    return navigateTo('/')
  }
})
