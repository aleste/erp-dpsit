import { ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { parseISO, toISO } from '~/composables/useFechas'

// Parámetros de cálculo compartidos (se cargan una vez desde la API)
const parametros = ref({
  vigencia_meses: 12,
  recordatorio_dias_anticipacion: 30,
  recordatorio_hora: '08:00',
  recordatorio_max_intentos: 3,
})
const parametrosCargados = ref(false)

export const ESTADOS_CERTIFICADO: Record<string, { label: string; clase: string }> = {
  VIGENTE: { label: 'Vigente', clase: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  PROXIMO_A_VENCER: { label: 'Próximo a vencer', clase: 'bg-amber-50 text-amber-700 border-amber-200' },
  VENCIDO: { label: 'Vencido', clase: 'bg-rose-50 text-rose-700 border-rose-200' },
  NO_VIGENTE: { label: 'No vigente', clase: 'bg-slate-100 text-slate-500 border-slate-200' },
}

export const ESTADOS_RECORDATORIO: Record<string, { label: string; clase: string }> = {
  PENDIENTE: { label: 'Pendiente', clase: 'bg-sky-50 text-sky-700 border-sky-200' },
  ENVIANDO: { label: 'Enviando', clase: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
  ENVIADO: { label: 'Enviado', clase: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  ERROR: { label: 'Con error', clase: 'bg-rose-50 text-rose-700 border-rose-200' },
  SIN_EMAIL: { label: 'Sin email', clase: 'bg-orange-50 text-orange-700 border-orange-200' },
  CANCELADO: { label: 'Cancelado', clase: 'bg-slate-100 text-slate-500 border-slate-200' },
}

export const ESTADOS_ENTREGA: Record<string, string> = {
  ASIGNADA: 'Asignada',
  DEVUELTA: 'Devuelta',
  REEMPLAZADA: 'Reemplazada',
  EXTRAVIADA: 'Extraviada',
  REVOCADA: 'Revocada',
}

export const TIPOS_DISPOSITIVO: Record<string, string> = {
  TOKEN_USB: 'Token USB',
  TARJETA_CHIP: 'Tarjeta con chip',
  OTRO: 'Otro',
}

export const TIPOS_DOCUMENTO = ['DNI', 'PASAPORTE', 'CI', 'LC', 'LE', 'OTRO']

export const useFirmaDigital = () => {
  const { get } = useApi()

  const cargarParametros = async () => {
    if (parametrosCargados.value) return
    try {
      const res: any = await get('/firma-digital/parametros')
      if (res.success) {
        parametros.value = res.data
        parametrosCargados.value = true
      }
    } catch (err) {
      console.error('Error cargando parámetros de firma digital:', err)
    }
  }

  // Misma regla que el backend (FirmaDigitalService): suma de meses sin desbordar fin de mes
  const calcularVencimiento = (fechaInicio: string): string => {
    if (!fechaInicio) return ''
    const inicio = parseISO(fechaInicio)
    const objetivo = new Date(Date.UTC(inicio.getUTCFullYear(), inicio.getUTCMonth() + parametros.value.vigencia_meses, 1))
    const ultimoDia = new Date(Date.UTC(objetivo.getUTCFullYear(), objetivo.getUTCMonth() + 1, 0)).getUTCDate()
    objetivo.setUTCDate(Math.min(inicio.getUTCDate(), ultimoDia))
    return toISO(objetivo)
  }

  const calcularRecordatorio = (fechaVencimiento: string): string => {
    if (!fechaVencimiento) return ''
    const vto = parseISO(fechaVencimiento)
    vto.setUTCDate(vto.getUTCDate() - parametros.value.recordatorio_dias_anticipacion)
    return toISO(vto)
  }

  return {
    parametros,
    cargarParametros,
    calcularVencimiento,
    calcularRecordatorio,
  }
}
