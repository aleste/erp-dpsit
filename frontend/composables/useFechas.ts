// Utilidades de fechas compartidas por los módulos. Las fechas viajan como ISO (YYYY-MM-DD).

// Fecha ISO <-> Date en UTC, para evitar corrimientos por zona horaria
export const parseISO = (iso: string): Date => {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(Date.UTC(y, m - 1, d))
}

export const toISO = (date: Date): string => date.toISOString().split('T')[0]

export const hoyISO = (): string => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export const formatFecha = (iso?: string | null): string => {
  if (!iso) return '—'
  const [y, m, d] = iso.substring(0, 10).split('-')
  return `${d}/${m}/${y}`
}

export const formatFechaHora = (iso?: string | null): string => {
  if (!iso) return '—'
  const d = new Date(iso)
  return `${formatFecha(iso)} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
