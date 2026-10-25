<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <NuxtLink to="/expedientes" class="text-xs font-bold text-sky-600 hover:underline">← Volver al seguimiento</NuxtLink>
    <div>
      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Catálogos de Expedientes</h1>
      <p class="text-sm text-slate-500 mt-0.5">
        Estados, tipos de requerimiento y dependencias de destino. No se eliminan: se desactivan para conservar el historial.
      </p>
    </div>

    <div v-if="!authStore.isAdmin" class="p-6 bg-white rounded-2xl border border-slate-200 text-sm text-slate-500">
      Solo el perfil Administrador puede modificar los catálogos.
    </div>

    <template v-else>
      <div class="flex border-b border-slate-200 space-x-6">
        <button v-for="(t, id) in CATALOGOS" :key="id" @click="catalogo = id" class="pb-3 text-xs font-extrabold border-b-2 transition"
                :class="catalogo === id ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'">
          {{ t.titulo }} ({{ registros(id).length }})
        </button>
      </div>

      <div class="flex justify-between items-center">
        <input v-model="busqueda" type="text" placeholder="Filtrar por nombre..." class="w-72 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs" />
        <button @click="abrir()" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold">+ Agregar</button>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
              <th class="py-3 px-4">Nombre</th>
              <th v-if="catalogo === 'estados'" class="py-3 px-4">Orden</th>
              <th v-if="catalogo === 'estados'" class="py-3 px-4">Tipo</th>
              <th v-if="catalogo === 'requerimientos'" class="py-3 px-4">Grupo</th>
              <th class="py-3 px-4">Activo</th>
              <th class="py-3 px-4 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-for="r in filtrados" :key="r.id" :class="!r.activo && 'opacity-50'">
              <td class="py-2.5 px-4">
                <ExpedientesEstadoChip v-if="catalogo === 'estados'" :estado="r" />
                <span v-else>{{ r.nombre }}</span>
              </td>
              <td v-if="catalogo === 'estados'" class="py-2.5 px-4">{{ r.orden }}</td>
              <td v-if="catalogo === 'estados'" class="py-2.5 px-4 text-[11px]">
                {{ r.es_final ? 'Egreso' : 'De trabajo' }}
                <span v-if="r.es_final && r.requiere_documento" class="text-slate-400">· exige documento</span>
                <span v-if="r.exime_documento" class="text-slate-400">· egreso sin documento</span>
              </td>
              <td v-if="catalogo === 'requerimientos'" class="py-2.5 px-4">{{ GRUPOS_REQUERIMIENTO[r.grupo] || '—' }}</td>
              <td class="py-2.5 px-4">{{ r.activo ? 'Sí' : 'No' }}</td>
              <td class="py-2.5 px-4 text-right">
                <button @click="abrir(r)" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[11px] font-bold">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-if="form.visible" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <form v-dialogo="() => (form.visible = false)" @submit.prevent="guardar" class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 p-6 space-y-3 text-xs">
        <h2 class="text-base font-bold text-slate-900">{{ form.id ? 'Editar' : 'Agregar' }} · {{ CATALOGOS[catalogo].titulo }}</h2>
        <label class="block space-y-1"><span class="font-bold text-slate-700">Nombre *</span><input v-model="form.nombre" required maxlength="255" class="campo" /></label>
        <template v-if="catalogo === 'estados'">
          <div class="grid grid-cols-2 gap-3">
            <label class="space-y-1"><span class="font-bold text-slate-700">Orden</span><input v-model.number="form.orden" type="number" min="0" class="campo" /></label>
            <label class="space-y-1"><span class="font-bold text-slate-700">Color</span>
              <select v-model="form.color" class="campo"><option v-for="c in COLORES_DISPONIBLES" :key="c" :value="c">{{ c }}</option></select>
            </label>
          </div>
          <label class="flex items-center gap-2"><input v-model="form.es_final" type="checkbox" class="rounded" /> Estado de egreso (la intervención sale del área)</label>
          <label v-if="form.es_final" class="flex items-center gap-2"><input v-model="form.requiere_documento" type="checkbox" class="rounded" /> Exige documento de respuesta al egresar</label>
          <label v-if="!form.es_final" class="flex items-center gap-2"><input v-model="form.exime_documento" type="checkbox" class="rounded" /> Desde este estado se puede egresar sin documento</label>
        </template>
        <label v-if="catalogo === 'requerimientos'" class="block space-y-1"><span class="font-bold text-slate-700">Grupo</span>
          <select v-model="form.grupo" class="campo"><option :value="null">—</option><option v-for="(l, v) in GRUPOS_REQUERIMIENTO" :key="v" :value="v">{{ l }}</option></select>
        </label>
        <label class="flex items-center gap-2"><input v-model="form.activo" type="checkbox" class="rounded" /> Activo</label>
        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" @click="form.visible = false" class="px-4 py-2 bg-slate-100 rounded-xl font-semibold">Cancelar</button>
          <button type="submit" :disabled="guardando" class="px-5 py-2 bg-sky-600 text-white rounded-xl font-bold disabled:opacity-50">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useExpedientes, GRUPOS_REQUERIMIENTO, COLORES_DISPONIBLES } from '~/composables/useExpedientes'

const { post, put } = useApi()
const authStore = useAuthStore()
const { toast } = useToast()
const { opciones, cargarOpciones } = useExpedientes()

const CATALOGOS = {
  estados: { titulo: 'Estados', clave: 'estados' },
  requerimientos: { titulo: 'Tipos de requerimiento', clave: 'tipos_requerimiento' },
  destinos: { titulo: 'Dependencias de destino', clave: 'destinos' },
}

const catalogo = ref('estados')
const busqueda = ref('')
const guardando = ref(false)
const form = reactive({ visible: false })

const registros = (id) => opciones.value[CATALOGOS[id].clave] || []
const filtrados = computed(() => registros(catalogo.value).filter(r => r.nombre.toLowerCase().includes(busqueda.value.toLowerCase())))

const abrir = (r = null) => {
  for (const k of Object.keys(form)) delete form[k]
  Object.assign(form, {
    visible: true,
    id: r?.id || null,
    nombre: r?.nombre || '',
    activo: r ? r.activo !== false : true,
    ...(catalogo.value === 'estados' ? {
      orden: r?.orden ?? 0, color: r?.color || 'slate', es_final: !!r?.es_final,
      requiere_documento: r ? !!r.requiere_documento : false, exime_documento: !!r?.exime_documento,
    } : {}),
    ...(catalogo.value === 'requerimientos' ? { grupo: r?.grupo || null } : {}),
  })
}

const guardar = async () => {
  guardando.value = true
  try {
    const { visible, id, ...datos } = form
    const res = id ? await put(`/expedientes/catalogos/${catalogo.value}/${id}`, datos) : await post(`/expedientes/catalogos/${catalogo.value}`, datos)
    if (res.success) {
      toast.success(res.message)
      form.visible = false
      await cargarOpciones(true)
    }
  } catch (err) {
    toast.error(err)
  } finally {
    guardando.value = false
  }
}

onMounted(() => cargarOpciones(true))
</script>

<style scoped>
.campo {
  @apply w-full p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500;
}
</style>
