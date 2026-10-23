<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded-lg border text-[10px] font-bold uppercase tracking-wide whitespace-nowrap"
    :class="claseColor(estado?.color)"
  >
    {{ estado?.nombre || '—' }}
  </span>
</template>

<script setup>
import { claseColor } from '~/composables/useExpedientes'

defineProps({
  estado: { type: Object, default: null },
})
</script>
