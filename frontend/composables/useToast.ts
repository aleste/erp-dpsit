import { useNotificationStore, type ConfirmDialogOptions } from '~/stores/notification'

// Diccionario de nombres de campos para mensajes legibles
const fieldLabels: Record<string, string> = {
  codigo: 'código institucional',
  nombre: 'nombre',
  sigla: 'sigla',
  cuil_cuit: 'número de CUIL/CUIT',
  email: 'correo electrónico',
  email_principal: 'correo electrónico principal',
  email_alternativo: 'correo electrónico alternativo',
  telefono_principal: 'teléfono principal',
  telefono_contacto: 'teléfono de contacto',
  telefono_alternativo: 'teléfono alternativo',
  ministerio_id: 'ministerio u organismo',
  tipo_rol_id: 'cargo o tipo de rol',
  area_ti_id: 'área TI',
  persona_id: 'persona',
  fecha_inicio: 'fecha de inicio',
  fecha_fin: 'fecha de finalización',
  acto_administrativo: 'acto administrativo / decreto',
  password: 'contraseña',
  resolucion_homologacion: 'resolución de homologación',
  nivel_jerarquico: 'nivel jerárquico',
  titulo: 'título del documento',
  tipo_documento: 'tipo de documento',
  archivo: 'archivo adjunto',
  observaciones: 'observaciones',
  numero_documento: 'número de documento',
  organismo_externo: 'organismo externo',
  cargo_funcion: 'cargo o función',
  fecha_entrega: 'fecha de entrega',
  fecha_vencimiento: 'fecha de vencimiento',
  fecha_recordatorio: 'fecha del recordatorio',
  numero_serie: 'número de serie',
  dispositivo_firma_id: 'dispositivo',
}

// Diccionario de traducciones exactas de mensajes frecuentes de Laravel
const exactTranslations: Record<string, string> = {
  'The codigo has already been taken.': 'El código institucional ingresado ya se encuentra registrado.',
  'The email has already been taken.': 'El correo electrónico ya está en uso por otro usuario o persona.',
  'The email principal has already been taken.': 'El correo electrónico principal ya está en uso por otro usuario o persona.',
  'The cuil cuit has already been taken.': 'El número de CUIL/CUIT ya se encuentra registrado en el padrón.',
  'The given data was invalid.': 'Los datos ingresados contienen errores de validación.',
  'Invalid credentials': 'Las credenciales ingresadas son incorrectas.',
  'Unauthorized': 'No tiene autorización para realizar esta acción o su sesión ha expirado.',
  'Unauthenticated.': 'Su sesión ha expirado. Por favor, vuelva a iniciar sesión.',
  'Server Error': 'Ha ocurrido un error en el servidor. Por favor intente más tarde.',
}

/**
 * Traduce y formatea un mensaje individual de error proveniente del backend o de Laravel
 */
export function translateErrorMessage(msg: string, fieldKey?: string): string {
  if (!msg || typeof msg !== 'string') return 'Ocurrió un error inesperado.'

  const trimmed = msg.trim()

  // 1. Coincidencia exacta
  if (exactTranslations[trimmed]) {
    return exactTranslations[trimmed]
  }

  // 2. Patrones regex típicos de Laravel Validation
  // 'The {field} has already been taken.'
  const takenMatch = trimmed.match(/^The\s+([a-zA-Z0-9_\s]+)\s+has already been taken\.$/i)
  if (takenMatch) {
    const rawField = (fieldKey || takenMatch[1]).toLowerCase().replace(/\s+/g, '_')
    const label = fieldLabels[rawField] || rawField
    return `El ${label} ingresado ya se encuentra registrado en el sistema.`
  }

  // 'The {field} field is required.'
  const requiredMatch = trimmed.match(/^The\s+([a-zA-Z0-9_\s]+)\s+field is required\.$/i)
  if (requiredMatch) {
    const rawField = (fieldKey || requiredMatch[1]).toLowerCase().replace(/\s+/g, '_')
    const label = fieldLabels[rawField] || rawField
    return `El campo "${label}" es obligatorio.`
  }

  // 'The {field} must be a valid email address.'
  const emailMatch = trimmed.match(/^The\s+([a-zA-Z0-9_\s]+)\s+must be a valid email address\.$/i)
  if (emailMatch) {
    const rawField = (fieldKey || emailMatch[1]).toLowerCase().replace(/\s+/g, '_')
    const label = fieldLabels[rawField] || rawField
    return `El campo "${label}" debe ser una dirección de correo electrónico válida.`
  }

  // 'The {field} must not be greater than {n} characters.'
  const maxMatch = trimmed.match(/^The\s+([a-zA-Z0-9_\s]+)\s+must not be greater than\s+(\d+)\s+characters\.$/i)
  if (maxMatch) {
    const rawField = (fieldKey || maxMatch[1]).toLowerCase().replace(/\s+/g, '_')
    const label = fieldLabels[rawField] || rawField
    return `El campo "${label}" no debe superar los ${maxMatch[2]} caracteres.`
  }

  // 'The {field} must be an integer.'
  const intMatch = trimmed.match(/^The\s+([a-zA-Z0-9_\s]+)\s+must be an integer\.$/i)
  if (intMatch) {
    const rawField = (fieldKey || intMatch[1]).toLowerCase().replace(/\s+/g, '_')
    const label = fieldLabels[rawField] || rawField
    return `El campo "${label}" debe ser un valor numérico entero.`
  }

  // 'The selected {field} is invalid.'
  const invalidMatch = trimmed.match(/^The selected\s+([a-zA-Z0-9_\s]+)\s+is invalid\.$/i)
  if (invalidMatch) {
    const rawField = (fieldKey || invalidMatch[1]).toLowerCase().replace(/\s+/g, '_')
    const label = fieldLabels[rawField] || rawField
    return `La opción seleccionada para "${label}" no es válida.`
  }

  return trimmed
}

/**
 * Normaliza y extrae un título, mensaje y lista de detalles a partir de cualquier tipo de error (Axios, $fetch, Error o string)
 */
export function formatBackendError(error: any): { title?: string; message: string; details?: string[] } {
  if (!error) {
    return { message: 'Ha ocurrido un error inesperado.' }
  }

  if (typeof error === 'string') {
    return { message: translateErrorMessage(error) }
  }

  // Errores de validación 422 de Laravel ({ message: '...', errors: { field: ['...'] } })
  const errorsObj = error?.data?.errors || error?.response?._data?.errors || error?.errors
  if (errorsObj && typeof errorsObj === 'object') {
    const details: string[] = []
    
    for (const [field, messages] of Object.entries(errorsObj)) {
      if (Array.isArray(messages)) {
        messages.forEach((m) => {
          details.push(translateErrorMessage(String(m), field))
        })
      } else if (typeof messages === 'string') {
        details.push(translateErrorMessage(messages, field))
      }
    }

    if (details.length === 1) {
      return {
        title: 'Error de Validación',
        message: details[0],
      }
    } else if (details.length > 1) {
      return {
        title: 'Errores de Validación',
        message: 'Por favor corrija los siguientes campos:',
        details,
      }
    }
  }

  // Mensaje directo en data.message
  const directMessage = error?.data?.message || error?.response?._data?.message || error?.message
  if (directMessage) {
    return {
      message: translateErrorMessage(directMessage),
    }
  }

  return {
    message: 'Error al procesar la solicitud en el servidor.',
  }
}

export const useToast = () => {
  const notificationStore = useNotificationStore()

  const toast = {
    /**
     * Toast de Éxito
     */
    success(message: string, options?: { title?: string; duration?: number }) {
      return notificationStore.addToast({
        type: 'success',
        title: options?.title || 'Operación Exitosa',
        message,
        duration: options?.duration,
      })
    },

    /**
     * Toast de Error con traducción automática de Laravel
     */
    error(errorOrMessage: any, options?: { title?: string; duration?: number }) {
      const formatted = formatBackendError(errorOrMessage)
      return notificationStore.addToast({
        type: 'error',
        title: options?.title || formatted.title || 'Error',
        message: formatted.message,
        details: formatted.details,
        duration: options?.duration,
      })
    },

    /**
     * Toast de Advertencia
     */
    warning(message: string, options?: { title?: string; duration?: number }) {
      return notificationStore.addToast({
        type: 'warning',
        title: options?.title || 'Advertencia',
        message,
        duration: options?.duration,
      })
    },

    /**
     * Toast Informativo
     */
    info(message: string, options?: { title?: string; duration?: number }) {
      return notificationStore.addToast({
        type: 'info',
        title: options?.title || 'Información',
        message,
        duration: options?.duration,
      })
    },
  }

  /**
   * Diálogo Modal de Confirmación basado en Promesas
   */
  const confirmDialog = (optionsOrMessage: string | ConfirmDialogOptions): Promise<boolean> => {
    return notificationStore.openConfirm(optionsOrMessage)
  }

  return {
    toast,
    confirmDialog,
    formatBackendError,
    translateErrorMessage,
  }
}
