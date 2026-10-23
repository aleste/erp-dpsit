import { ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

// Catálogos e integrantes del módulo, compartidos entre páginas (se recargan con recargar = true)
const opciones = ref<any>({ estados: [], tipos_requerimiento: [], destinos: [], integrantes: [], umbral_dias_alerta: 30 })
const cargadas = ref(false)

// Clases completas y literales: Tailwind solo genera las clases que encuentra escritas en el código
const COLORES: Record<string, string> = {
  sky: 'bg-sky-50 text-sky-700 border-sky-200',
  amber: 'bg-amber-50 text-amber-700 border-amber-200',
  rose: 'bg-rose-50 text-rose-700 border-rose-200',
  indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  violet: 'bg-violet-50 text-violet-700 border-violet-200',
  orange: 'bg-orange-50 text-orange-700 border-orange-200',
  emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  slate: 'bg-slate-100 text-slate-600 border-slate-200',
}
export const COLORES_DISPONIBLES = Object.keys(COLORES)
export const claseColor = (color?: string | null): string => COLORES[color || 'slate'] || COLORES.slate

export const TIPOS_MOVIMIENTO: Record<string, string> = {
  INGRESO: 'Ingreso al área',
  ASIGNACION: 'Asignación',
  CAMBIO_ESTADO: 'Cambio de estado',
  ENVIO_REVISION: 'Envío a revisión',
  FIRMA: 'Firma',
  EGRESO: 'Egreso',
  REAPERTURA: 'Reapertura',
  NOTA: 'Nota',
  ACTUALIZACION: 'Actualización de datos',
  CORRECCION: 'Corrección de fechas',
}

export const ACTO_ADMINISTRATIVO: Record<string, string> = {
  SI: 'Sí',
  NO_CORRESPONDE: 'No corresponde',
  PENDIENTE: 'Pendiente',
}

export const GRUPOS_REQUERIMIENTO: Record<string, string> = {
  PERSONAL: 'Personal',
  BIENES_SERVICIOS: 'Bienes y servicios',
  NORMATIVA: 'Normativa',
  OTROS: 'Otros',
}

export const useExpedientes = () => {
  const { get } = useApi()
  const authStore = useAuthStore()

  const cargarOpciones = async (recargar = false) => {
    if (cargadas.value && !recargar) return
    try {
      const res: any = await get('/expedientes/opciones')
      if (res.success) {
        opciones.value = res.data
        cargadas.value = true
      }
    } catch (err) {
      console.error('Error cargando opciones de expedientes:', err)
    }
  }

  const puedeOperar = () => authStore.puedeModulo('EXPEDIENTES', 'OPERADOR')
  const esSupervisor = () => authStore.puedeModulo('EXPEDIENTES', 'SUPERVISOR')

  return { opciones, cargarOpciones, puedeOperar, esSupervisor }
}
