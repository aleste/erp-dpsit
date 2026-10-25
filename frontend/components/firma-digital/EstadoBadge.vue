<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded-lg border text-xs font-bold uppercase tracking-wide whitespace-nowrap"
    :class="info.clase"
  >
    {{ info.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { ESTADOS_CERTIFICADO, ESTADOS_RECORDATORIO } from '~/composables/useFirmaDigital'

const props = defineProps({
  estado: { type: String, default: '' },
  // 'certificado' | 'recordatorio'
  tipo: { type: String, default: 'certificado' },
})

const info = computed(() => {
  const mapa = props.tipo === 'recordatorio' ? ESTADOS_RECORDATORIO : ESTADOS_CERTIFICADO
  return mapa[props.estado] || { label: props.estado || '—', clase: 'bg-slate-100 text-slate-500 border-slate-200' }
})
</script>
