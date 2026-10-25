import colors from 'tailwindcss/colors'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./components/**/*.{js,vue,ts}",
    "./composables/**/*.{js,ts}",
    "./layouts/**/*.vue",
    "./pages/**/*.vue",
    "./plugins/**/*.{js,ts}",
    "./app.vue",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
      },
      colors: {
        // Color de marca y de acción principal (botones, enlaces, foco, menú activo).
        // Sobre fondo claro: texto y botones desde el tono 700 (contraste ≥ 4,5:1).
        // Los estados siguen con la paleta de Tailwind (emerald, amber, rose): su uso no es solo semántico.
        primary: colors.sky,
      }
    },
  },
  plugins: [],
}
