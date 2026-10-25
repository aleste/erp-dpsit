<template>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead>
        <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-xs">
          <th class="py-3 px-4">Expediente</th>
          <th class="py-3 px-4">Organismo / motivo</th>
          <th class="py-3 px-4">Responsable</th>
          <th class="py-3 px-4">Estado</th>
          <th class="py-3 px-4">Ingreso</th>
          <th v-if="mostrarEgreso" class="py-3 px-4">Egreso / destino</th>
          <th class="py-3 px-4 text-right">Días</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 text-slate-700">
        <tr
          v-for="i in filas"
          :key="i.id"
          @click="$emit('abrir', i.expediente_id)"
          class="hover:bg-slate-50/80 transition cursor-pointer"
        >
          <td class="py-3 px-4">
            <p class="font-mono font-bold text-slate-900 whitespace-nowrap">{{ i.expediente?.numero }}</p>
            <p class="text-xs text-slate-500">
              {{ i.numero_intervencion }}ª intervención
              <span v-if="i.prioridad === 'ALTA'" class="ml-1 px-1.5 rounded bg-rose-100 text-rose-700 font-bold uppercase">Prioridad alta</span>
            </p>
          </td>
          <td class="py-3 px-4 max-w-sm">
            <p class="text-xs text-slate-500 truncate">{{ i.expediente?.ministerio?.nombre || 'Sin organismo' }}</p>
            <p class="truncate">{{ i.descripcion || i.expediente?.caratula || '—' }}</p>
            <p v-if="i.tipo_requerimiento" class="text-xs text-slate-500">{{ i.tipo_requerimiento.nombre }}</p>
          </td>
          <td class="py-3 px-4">
            <span v-if="i.responsable">{{ i.responsable.name }}</span>
            <span v-else class="text-amber-700 font-semibold">Sin asignar</span>
            <p v-if="i.colaboradores?.length" class="text-xs text-slate-500">+ {{ i.colaboradores.map(c => c.name).join(', ') }}</p>
          </td>
          <td class="py-3 px-4 space-y-1">
            <ExpedientesEstadoChip :estado="i.estado" />
            <p v-if="i.fecha_proxima_accion && i.en_area" class="text-xs" :class="i.fecha_proxima_accion < hoy ? 'text-rose-700 font-bold' : 'text-slate-500'">
              Próx.: {{ formatFecha(i.fecha_proxima_accion) }}
            </p>
          </td>
          <td class="py-3 px-4 whitespace-nowrap">{{ formatFecha(i.fecha_ingreso) }}</td>
          <td v-if="mostrarEgreso" class="py-3 px-4">
            <template v-if="!i.en_area">
              <p class="whitespace-nowrap">{{ i.fecha_egreso ? formatFecha(i.fecha_egreso) : 'Sin fecha' }}</p>
              <p class="text-xs text-slate-500 truncate max-w-[14rem]">{{ i.dependencia_destino?.nombre }}</p>
            </template>
            <span v-else class="text-slate-500">En el área</span>
          </td>
          <td class="py-3 px-4 text-right font-bold" :class="i.en_area && i.dias_en_area > umbral ? 'text-orange-700' : 'text-slate-600'">
            {{ i.dias_en_area ?? '—' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { formatFecha, hoyISO } from '~/composables/useFechas'

defineProps({
  filas: { type: Array, default: () => [] },
  umbral: { type: Number, default: 30 },
  mostrarEgreso: { type: Boolean, default: false },
})
defineEmits(['abrir'])

const hoy = hoyISO()
</script>
