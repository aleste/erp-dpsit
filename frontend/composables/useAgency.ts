export const useAgency = () => {
  const config = useRuntimeConfig()

  return {
    name: (config.public.agencyName as string) || 'Dirección Provincial de Sistemas de Información y Tecnología',
    shortName: (config.public.agencyShortName as string) || 'DPSIT',
    acronym: (config.public.agencyAcronym as string) || 'DP',
    government: (config.public.agencyGovernment as string) || 'Poder Ejecutivo Provincial',
    tagline: (config.public.agencyTagline as string) || 'Gobernanza TI Provincial',
  }
}
