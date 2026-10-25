// Directiva v-dialogo: comportamiento accesible para los paneles de los modales.
// Uso: <div class="bg-white ..." v-dialogo="() => (mostrarModal = false)">
// - role="dialog", aria-modal y aria-labelledby (primer h2/h3 del panel)
// - foco inicial en el primer campo (o en el panel) y foco atrapado con Tab
// - Esc cierra solo el modal de más arriba (pila: una confirmación sobre un formulario)
// - al cerrarse, devuelve el foco al elemento que lo abrió
import { nextTick } from 'vue'
import type { Directive } from 'vue'

type Cerrar = (() => void) | undefined

interface EstadoDialogo {
  cerrar: Cerrar
  previo: HTMLElement | null
}

const ENFOCABLES = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

const CAMPOS = 'input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])'

const pila: HTMLElement[] = []
const estados = new WeakMap<HTMLElement, EstadoDialogo>()
let contador = 0

const visibles = (el: HTMLElement) =>
  Array.from(el.querySelectorAll<HTMLElement>(ENFOCABLES)).filter((n) => n.offsetParent !== null || n === document.activeElement)

const alPresionarTecla = (e: KeyboardEvent) => {
  const actual = pila[pila.length - 1]
  if (!actual) return

  if (e.key === 'Escape') {
    const cerrar = estados.get(actual)?.cerrar
    if (cerrar) {
      e.preventDefault()
      cerrar()
    }
    return
  }

  if (e.key !== 'Tab') return

  const enfocables = visibles(actual)
  if (enfocables.length === 0) {
    e.preventDefault()
    actual.focus()
    return
  }

  const primero = enfocables[0]
  const ultimo = enfocables[enfocables.length - 1]
  const activo = document.activeElement

  if (!actual.contains(activo)) {
    e.preventDefault()
    primero.focus()
  } else if (e.shiftKey && (activo === primero || activo === actual)) {
    e.preventDefault()
    ultimo.focus()
  } else if (!e.shiftKey && activo === ultimo) {
    e.preventDefault()
    primero.focus()
  }
}

const dialogo: Directive<HTMLElement, Cerrar> = {
  mounted(el, binding) {
    el.setAttribute('role', 'dialog')
    el.setAttribute('aria-modal', 'true')
    if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1')
    el.classList.add('outline-none')

    if (!el.hasAttribute('aria-label') && !el.hasAttribute('aria-labelledby')) {
      const titulo = el.querySelector<HTMLElement>('h2, h3')
      if (titulo) {
        if (!titulo.id) titulo.id = `dialogo-titulo-${++contador}`
        el.setAttribute('aria-labelledby', titulo.id)
      }
    }

    const previo = document.activeElement instanceof HTMLElement ? document.activeElement : null
    estados.set(el, { cerrar: binding.value, previo })
    pila.push(el)
    if (pila.length === 1) document.addEventListener('keydown', alPresionarTecla)

    nextTick(() => {
      const campo = el.querySelector<HTMLElement>('[autofocus]') || el.querySelector<HTMLElement>(CAMPOS)
      ;(campo || el).focus()
    })
  },

  updated(el, binding) {
    const estado = estados.get(el)
    if (estado) estado.cerrar = binding.value
  },

  beforeUnmount(el) {
    const indice = pila.indexOf(el)
    if (indice !== -1) pila.splice(indice, 1)
    if (pila.length === 0) document.removeEventListener('keydown', alPresionarTecla)

    const previo = estados.get(el)?.previo
    estados.delete(el)
    if (previo && previo.isConnected) previo.focus()
  },
}

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('dialogo', dialogo)
})
