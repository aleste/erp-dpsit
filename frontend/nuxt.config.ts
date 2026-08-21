const cleanEnv = (val: string | undefined, fallback: string): string => {
  if (!val) return fallback
  return val.replace(/^["']+|["']+$/g, '').trim()
}

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  devtools: { enabled: false },
  modules: [
    '@nuxtjs/tailwindcss',
    '@pinia/nuxt',
  ],
  runtimeConfig: {
    public: {
      apiBase: cleanEnv(process.env.NUXT_PUBLIC_API_BASE || process.env.BACKEND_PORT ? `http://localhost:${process.env.BACKEND_PORT}/api` : undefined, 'http://localhost:8000/api'),
      agencyName: cleanEnv(process.env.NUXT_PUBLIC_AGENCY_NAME || process.env.AGENCY_NAME, 'Dirección Provincial de Sistemas de Información y Tecnologías'),
      agencyShortName: cleanEnv(process.env.NUXT_PUBLIC_AGENCY_SHORT_NAME || process.env.AGENCY_SHORT_NAME, 'DPSIT'),
      agencyAcronym: cleanEnv(process.env.NUXT_PUBLIC_AGENCY_ACRONYM || process.env.AGENCY_ACRONYM, 'DPSIT'),
      agencyGovernment: cleanEnv(process.env.NUXT_PUBLIC_AGENCY_GOVERNMENT || process.env.AGENCY_GOVERNMENT, 'Subsecretaría de Gobierno Digital'),
      agencyTagline: cleanEnv(process.env.NUXT_PUBLIC_AGENCY_TAGLINE || process.env.AGENCY_TAGLINE, 'Gobernanza TI Provincial'),
    }
  },
  app: {
    head: {
      title: `${cleanEnv(process.env.NUXT_PUBLIC_AGENCY_SHORT_NAME || process.env.AGENCY_SHORT_NAME, 'DPSIT')} - Sistema de Gestión Integral`,
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: `Sistema de Gestión Integral para la ${cleanEnv(process.env.NUXT_PUBLIC_AGENCY_NAME || process.env.AGENCY_NAME, 'Dirección Provincial de Sistemas de Información y Tecnologías')}` },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' },
      ]
    }
  },
  css: ['~/assets/css/main.css'],
  typescript: {
    strict: true
  },
  ssr: false,
})
