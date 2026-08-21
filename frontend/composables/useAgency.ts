export const useAgency = () => {
  const config = useRuntimeConfig()

  const clean = (val: unknown, fallback: string): string => {
    if (typeof val !== 'string' || !val) return fallback
    return val.replace(/^["']+|["']+$/g, '').trim()
  }

  return {
    name: clean(config.public.agencyName, 'Dirección Provincial de Sistemas de Información y Tecnologías'),
    shortName: clean(config.public.agencyShortName, 'DPSIT'),
    acronym: clean(config.public.agencyAcronym, 'DPSIT'),
    government: clean(config.public.agencyGovernment, 'Subsecretaría de Gobierno Digital'),
    tagline: clean(config.public.agencyTagline, 'Gobernanza TI Provincial'),
  }
}
