import { useAuthStore } from '~/stores/auth'

/**
 * Módulo que gobierna cada sección del frontend (D-34). Es la única tabla de rutas a módulos: la usan el
 * menú y el middleware `permisos.global`. Para un módulo nuevo, agregar aquí sus rutas (el backend publica
 * el catálogo de módulos y roles). `ADMINISTRADOR` = solo administradores.
 */
const MODULO_POR_RUTA: Array<[prefijo: string, modulo: string]> = [
  ['/ministerios', 'ORGANISMOS'],
  ['/areas-ti', 'ORGANISMOS'],
  ['/roles', 'ORGANISMOS'],
  ['/personas', 'PADRON'],
  ['/servicios', 'SERVICIOS'],
  ['/firma-digital', 'FIRMA_DIGITAL'],
  ['/expedientes/catalogos', 'ADMINISTRADOR'],
  ['/expedientes', 'EXPEDIENTES'],
  ['/exportador', 'EXPORTADOR'],
  ['/usuarios', 'ADMINISTRADOR'],
]

export const useModulos = () => {
  const authStore = useAuthStore()

  const moduloDeRuta = (path: string): string | null =>
    MODULO_POR_RUTA.find(([prefijo]) => path === prefijo || path.startsWith(`${prefijo}/`))?.[1] ?? null

  // Rutas sin módulo (inicio, login) están disponibles para cualquier sesión
  const puedeVerRuta = (path: string): boolean => {
    const modulo = moduloDeRuta(path)
    if (!modulo) return true
    if (modulo === 'ADMINISTRADOR') return authStore.isAdmin
    return authStore.puedeModulo(modulo, 'CONSULTA')
  }

  // Ruta de entrada de un módulo (la primera declarada), para los accesos del inicio
  const rutaDeModulo = (modulo: string): string | null =>
    MODULO_POR_RUTA.find(([, m]) => m === modulo)?.[0] ?? null

  return { moduloDeRuta, puedeVerRuta, rutaDeModulo }
}
