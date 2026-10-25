<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Usuarios y Permisos</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Cuentas de acceso al ERP: perfil general y rol en cada módulo. Los usuarios no se eliminan, se desactivan.
        </p>
      </div>
      <button
        v-if="authStore.isAdmin"
        @click="abrirFormulario()"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition"
      >
        <span>+</span> Nuevo Usuario
      </button>
    </div>

    <div v-if="!authStore.isAdmin" class="p-6 bg-white rounded-2xl border border-slate-200 text-sm text-slate-500">
      Solo el perfil Administrador puede gestionar usuarios.
    </div>

    <template v-else>
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
          <label for="usuarios-filtros-q" class="filtro-label">Buscar</label>
          <input id="usuarios-filtros-q" v-model="filtros.q" @input="buscarConDemora" type="text" placeholder="Nombre o email..." class="filtro" />
        </div>
        <div>
          <label for="usuarios-filtros-activo" class="filtro-label">Estado</label>
          <select id="usuarios-filtros-activo" v-model="filtros.activo" @change="cargar" class="filtro">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
          </select>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div v-if="cargando" class="p-12 text-center text-xs text-slate-400">Cargando usuarios...</div>
        <div v-else-if="usuarios.length === 0" class="p-12 text-center text-xs text-slate-400">No hay usuarios con esos criterios.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4">Usuario</th>
                <th class="py-3.5 px-4">Perfil general</th>
                <th class="py-3.5 px-4">Roles por módulo</th>
                <th class="py-3.5 px-4">Estado</th>
                <th class="py-3.5 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="u in usuarios" :key="u.id" class="hover:bg-slate-50/80 transition" :class="!u.activo && 'opacity-60'">
                <td class="py-3.5 px-4">
                  <p class="font-extrabold text-slate-900">{{ u.name }}</p>
                  <p class="text-[11px] text-slate-400">{{ u.email }}</p>
                </td>
                <td class="py-3.5 px-4 font-semibold">{{ PERFILES[u.perfil_acceso] || u.perfil_acceso }}</td>
                <td class="py-3.5 px-4">
                  <span v-if="!u.modulos?.length" class="text-slate-400">—</span>
                  <span
                    v-for="m in u.modulos"
                    :key="m.modulo"
                    class="inline-flex items-center px-2 py-0.5 mr-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold uppercase"
                  >
                    {{ NOMBRES_MODULO[m.modulo] || m.modulo }}: {{ m.rol }}
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="text-[10px] font-bold uppercase" :class="u.activo ? 'text-emerald-600' : 'text-rose-600'">
                    {{ u.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <button @click="abrirFormulario(u)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-[11px] font-bold text-slate-700">
                    ✏️ Editar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Modal alta / edición -->
    <div v-if="formulario.visible" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (formulario.visible = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">{{ formulario.id ? 'Editar Usuario' : 'Nuevo Usuario' }}</h2>
            <p class="text-xs text-slate-400">Perfil general y permisos por módulo</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="formulario.visible = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="guardar" class="p-6 space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label for="usuarios-formulario-name" class="block font-bold text-slate-700 mb-1">Nombre y apellido *</label>
              <input id="usuarios-formulario-name" v-model="formulario.name" type="text" required class="campo" />
            </div>
            <div class="col-span-2">
              <label for="usuarios-formulario-email" class="block font-bold text-slate-700 mb-1">Email (usuario de acceso) *</label>
              <input id="usuarios-formulario-email" v-model="formulario.email" type="email" required class="campo" />
            </div>
            <div>
              <label for="usuarios-formulario-perfil-acceso" class="block font-bold text-slate-700 mb-1">Perfil general *</label>
              <select id="usuarios-formulario-perfil-acceso" v-model="formulario.perfil_acceso" required class="campo">
                <option v-for="(label, valor) in PERFILES" :key="valor" :value="valor">{{ label }}</option>
              </select>
            </div>
            <div>
              <label for="usuarios-formulario-password" class="block font-bold text-slate-700 mb-1">{{ formulario.id ? 'Nueva contraseña' : 'Contraseña inicial *' }}</label>
              <input id="usuarios-formulario-password"
                v-model="formulario.password"
                type="password"
                :required="!formulario.id"
                minlength="8"
                autocomplete="new-password"
                :placeholder="formulario.id ? 'Dejar vacío para no cambiarla' : 'Mínimo 8 caracteres'"
                class="campo"
              />
            </div>
          </div>

          <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-3">
            <p class="font-bold text-slate-700">Roles por módulo</p>
            <p class="text-[11px] text-slate-500">
              El perfil general habilita los módulos de gobernanza TI. Los módulos de áreas internas requieren un rol propio.
              Un Administrador accede a todos con el rol máximo.
            </p>
            <div v-for="(roles, modulo) in rolesPorModulo" :key="modulo" class="grid grid-cols-2 gap-3 items-center">
              <span class="font-semibold text-slate-700">{{ NOMBRES_MODULO[modulo] || modulo }}</span>
              <select v-model="formulario.modulos[modulo]" class="campo">
                <option value="">Sin acceso</option>
                <option v-for="r in roles" :key="r" :value="r">{{ DESCRIPCION_ROL[r] || r }}</option>
              </select>
            </div>
          </div>

          <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
            <input v-model="formulario.activo" type="checkbox" class="w-4 h-4 text-sky-600 rounded border-slate-300" />
            Usuario activo (al desactivarlo se cierran sus sesiones)
          </label>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="formulario.visible = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">
              Cancelar
            </button>
            <button type="submit" :disabled="guardando" class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50">
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

const { get, post, put } = useApi()
const authStore = useAuthStore()
const { toast } = useToast()

const PERFILES = { ADMINISTRADOR: 'Administrador', EDITOR: 'Editor', LECTOR: 'Lector' }
const NOMBRES_MODULO = { EXPEDIENTES: 'Seguimiento de Expedientes' }
const DESCRIPCION_ROL = {
  CONSULTA: 'Consulta (solo lectura)',
  OPERADOR: 'Operador (registra y tramita)',
  SUPERVISOR: 'Supervisor (reasigna, reabre, corrige)',
}

const usuarios = ref([])
const rolesPorModulo = ref({ EXPEDIENTES: ['CONSULTA', 'OPERADOR', 'SUPERVISOR'] })
const cargando = ref(false)
const guardando = ref(false)
const filtros = reactive({ q: '', activo: '' })

const formulario = reactive({
  visible: false, id: null, name: '', email: '', perfil_acceso: 'LECTOR', password: '', activo: true, modulos: {},
})

const cargar = async () => {
  if (!authStore.isAdmin) return
  cargando.value = true
  try {
    const res = await get('/usuarios', filtros)
    if (res.success) {
      usuarios.value = res.data
      if (res.meta?.modulos) rolesPorModulo.value = res.meta.modulos
    }
  } catch (err) {
    toast.error(err, { title: 'Error al cargar usuarios' })
  } finally {
    cargando.value = false
  }
}

let temporizador = null
const buscarConDemora = () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(cargar, 350)
}

const abrirFormulario = (u = null) => {
  const modulos = Object.fromEntries(Object.keys(rolesPorModulo.value).map(m => [m, '']))
  for (const m of u?.modulos || []) modulos[m.modulo] = m.rol
  Object.assign(formulario, {
    visible: true,
    id: u?.id || null,
    name: u?.name || '',
    email: u?.email || '',
    perfil_acceso: u?.perfil_acceso || 'LECTOR',
    password: '',
    activo: u ? u.activo : true,
    modulos,
  })
}

const guardar = async () => {
  guardando.value = true
  try {
    const payload = {
      name: formulario.name,
      email: formulario.email,
      perfil_acceso: formulario.perfil_acceso,
      activo: formulario.activo,
      password: formulario.password || null,
      modulos: Object.entries(formulario.modulos).filter(([, rol]) => rol).map(([modulo, rol]) => ({ modulo, rol })),
    }
    const res = formulario.id ? await put(`/usuarios/${formulario.id}`, payload) : await post('/usuarios', payload)
    if (res.success) {
      toast.success(res.message)
      formulario.visible = false
      await cargar()
      if (formulario.id === authStore.user?.id) authStore.refrescarUsuario()
    }
  } catch (err) {
    toast.error(err)
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>

<style scoped>
.campo {
  @apply w-full p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500;
}
.filtro {
  @apply w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition;
}
.filtro-label {
  @apply block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1;
}
</style>
