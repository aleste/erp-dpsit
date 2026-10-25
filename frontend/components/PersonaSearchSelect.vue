<template>
  <div class="relative w-full text-sm" ref="containerRef">
    <!-- 1. Estado: Persona Seleccionada -->
    <div
      v-if="selectedPersona"
      class="flex items-center justify-between p-2.5 bg-sky-50/80 border border-sky-200 rounded-xl transition-all shadow-sm"
    >
      <div class="flex items-center gap-3 overflow-hidden">
        <div class="w-8 h-8 rounded-lg bg-sky-600 text-white font-bold flex items-center justify-center text-sm flex-shrink-0 shadow-sm">
          {{ selectedPersona.apellido ? selectedPersona.apellido.charAt(0) : '' }}{{ selectedPersona.nombre ? selectedPersona.nombre.charAt(0) : '' }}
        </div>
        <div class="truncate">
          <p class="font-bold text-slate-800 truncate">
            {{ selectedPersona.apellido }}, {{ selectedPersona.nombre }}
          </p>
          <p class="text-xs text-slate-500 font-medium truncate">
            CUIL: <span class="font-mono text-slate-700">{{ selectedPersona.cuil_cuit }}</span>
            <span v-if="selectedPersona.titulo_profesional"> • {{ selectedPersona.titulo_profesional }}</span>
          </p>
        </div>
      </div>

      <button
        v-if="!disabled"
        type="button"
        @click="clearSelection"
        class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition ml-2 flex-shrink-0"
        title="Cambiar persona"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- 2. Estado: Buscador Activo (Sin selección) -->
    <div v-else class="relative">
      <div class="relative">
        <input
          :id="inputId"
          ref="inputRef"
          type="text"
          v-model="searchQuery"
          @focus="onFocus"
          @input="onSearchInput"
          :disabled="disabled"
          :placeholder="placeholder || 'Escriba apellido, nombre o CUIL para buscar en el padrón...'"
          class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition disabled:opacity-60"
        />

        <!-- Ícono Lupa -->
        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none">
          <svg v-if="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <svg v-else class="w-4 h-4 animate-spin text-sky-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- Botón Limpiar Texto -->
        <button
          v-if="searchQuery"
          type="button"
          @click="searchQuery = ''; results = []; isOpen = false"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-600 p-0.5 rounded-full"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Menú Desplegable Flotante de Resultados -->
      <div
        v-if="isOpen"
        class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto custom-scrollbar"
      >
        <!-- Estado: Cargando -->
        <div v-if="loading" class="p-4 text-center text-slate-500">
          <p class="font-medium text-sm">Buscando en el padrón de agentes...</p>
        </div>

        <!-- Estado: Sin Resultados -->
        <div v-else-if="results.length === 0 && searchQuery.trim().length >= 2" class="p-4 text-center text-slate-500">
          <p class="font-semibold text-sm text-slate-700">No se encontraron agentes</p>
          <p class="text-xs text-slate-500 mt-0.5">No hay coincidencias para "{{ searchQuery }}"</p>
        </div>

        <!-- Estado: Instrucción de búsqueda -->
        <div v-else-if="results.length === 0 && searchQuery.trim().length < 2" class="p-3 text-center text-slate-500 text-xs">
          Ingrese al menos 2 caracteres (apellido, nombre o CUIL)
        </div>

        <!-- Lista de Resultados -->
        <div v-else class="divide-y divide-slate-100">
          <button
            v-for="p in results"
            :key="p.id"
            type="button"
            @click="selectPersona(p)"
            class="w-full text-left p-3 hover:bg-sky-50/70 flex items-center justify-between gap-3 transition group"
          >
            <div class="flex items-center gap-2.5 overflow-hidden">
              <div class="w-7 h-7 rounded-lg bg-slate-100 group-hover:bg-sky-600 group-hover:text-white text-slate-700 font-bold flex items-center justify-center text-xs flex-shrink-0 transition-colors">
                {{ p.apellido ? p.apellido.charAt(0) : '' }}{{ p.nombre ? p.nombre.charAt(0) : '' }}
              </div>
              <div class="truncate">
                <p class="font-bold text-slate-800 group-hover:text-sky-900 truncate">
                  {{ p.apellido }}, {{ p.nombre }}
                </p>
                <p class="text-xs text-slate-500 font-mono">
                  CUIL: {{ p.cuil_cuit }}
                  <span v-if="p.titulo_profesional" class="font-sans text-slate-500"> • {{ p.titulo_profesional }}</span>
                </p>
              </div>
            </div>

            <div class="flex-shrink-0 text-right">
              <span
                v-if="p.asignaciones_vigentes && p.asignaciones_vigentes.length > 0"
                class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
              >
                Con Cargo
              </span>
              <span
                v-else
                class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600"
              >
                Disponible
              </span>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useApi } from '~/composables/useApi'

interface Persona {
  id: number
  apellido: string
  nombre: string
  cuil_cuit: string
  email_principal?: string
  titulo_profesional?: string
  asignaciones_vigentes?: any[]
}

const props = defineProps<{
  modelValue?: number | string | null
  initialPersona?: Persona | null
  disabled?: boolean
  placeholder?: string
  inputId?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void
  (e: 'change', persona: Persona | null): void
}>()

const api = useApi()

const containerRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)

const searchQuery = ref('')
const results = ref<Persona[]>([])
const loading = ref(false)
const isOpen = ref(false)
const selectedPersona = ref<Persona | null>(props.initialPersona || null)

let debounceTimer: any = null

// Buscar agentes en la API
const fetchPersonas = async (query: string) => {
  if (!query || query.trim().length < 2) {
    results.value = []
    loading.value = false
    return
  }

  loading.value = true
  try {
    const res = await api.get('/personas', {
      q: query.trim(),
      per_page: 15
    })
    
    if (res?.data?.data) {
      results.value = res.data.data
    } else if (Array.isArray(res?.data)) {
      results.value = res.data
    } else {
      results.value = []
    }
  } catch (error) {
    console.error('Error al buscar agentes en el padrón:', error)
    results.value = []
  } finally {
    loading.value = false
  }
}

// Evento de escritura con debounce
const onSearchInput = () => {
  isOpen.value = true
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    fetchPersonas(searchQuery.value)
  }, 300)
}

const onFocus = () => {
  if (!disabledComputed.value) {
    isOpen.value = true
    if (searchQuery.value.trim().length >= 2 && results.value.length === 0) {
      fetchPersonas(searchQuery.value)
    }
  }
}

const selectPersona = (persona: Persona) => {
  selectedPersona.value = persona
  isOpen.value = false
  searchQuery.value = ''
  results.value = []
  emit('update:modelValue', persona.id)
  emit('change', persona)
}

const clearSelection = () => {
  selectedPersona.value = null
  searchQuery.value = ''
  results.value = []
  isOpen.value = false
  emit('update:modelValue', null)
  emit('change', null)
  nextTick(() => {
    inputRef.value?.focus()
  })
}

const disabledComputed = computed(() => props.disabled ?? false)

// Cargar persona por ID si cambia modelValue
const loadPersonaById = async (id: number | string) => {
  if (!id) {
    selectedPersona.value = null
    return
  }
  
  if (selectedPersona.value && selectedPersona.value.id === Number(id)) {
    return
  }

  try {
    const res = await api.get(`/personas/${id}`)
    if (res?.data) {
      selectedPersona.value = res.data
    }
  } catch (error) {
    console.error('Error al cargar persona seleccionada:', error)
  }
}

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    if (!selectedPersona.value || selectedPersona.value.id !== Number(newVal)) {
      loadPersonaById(newVal)
    }
  } else {
    selectedPersona.value = null
  }
}, { immediate: true })

watch(() => props.initialPersona, (newVal) => {
  if (newVal) {
    selectedPersona.value = newVal
  }
})

// Cerrar desplegable al hacer clic fuera del componente
const handleClickOutside = (e: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
  clearTimeout(debounceTimer)
})
</script>
