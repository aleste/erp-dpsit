import { useAuthStore } from '~/stores/auth'

export const useApi = () => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()

  const cleanParams = (params?: Record<string, any>) => {
    if (!params) return undefined
    const cleaned: Record<string, any> = {}
    for (const [key, value] of Object.entries(params)) {
      if (value !== '' && value !== null && value !== undefined) {
        cleaned[key] = value
      }
    }
    return Object.keys(cleaned).length > 0 ? cleaned : undefined
  }

  const fetchApi = async <T = any>(endpoint: string, options: any = {}): Promise<T> => {
    const url = `${config.public.apiBase}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`
    
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers || {}),
    }

    if (authStore.token) {
      headers.Authorization = `Bearer ${authStore.token}`
    }

    try {
      return await $fetch<T>(url, {
        ...options,
        headers,
      })
    } catch (error: any) {
      if (error?.status === 401) {
        authStore.logout()
      } else if (error?.status === 403) {
        // Puede que le hayan quitado un permiso: actualiza menú y botones (el aviso lo da quien llamó)
        authStore.refrescarUsuario()
      }
      throw error
    }
  }

  // Descarga un archivo con el token de la sesión (no hay rutas de descarga públicas: SEC-01 resuelto)
  const descargar = async (endpoint: string, nombreArchivo: string, params?: Record<string, any>) => {
    const blob = await fetchApi<Blob>(endpoint, { method: 'GET', query: cleanParams(params), responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = nombreArchivo
    a.click()
    URL.revokeObjectURL(url)
  }

  return {
    fetchApi,
    descargar,
    get: <T = any>(url: string, params?: Record<string, any>) => fetchApi<T>(url, { method: 'GET', query: cleanParams(params) }),
    post: <T = any>(url: string, body?: any) => fetchApi<T>(url, { method: 'POST', body }),
    put: <T = any>(url: string, body?: any) => fetchApi<T>(url, { method: 'PUT', body }),
    delete: <T = any>(url: string) => fetchApi<T>(url, { method: 'DELETE' }),
  }
}
