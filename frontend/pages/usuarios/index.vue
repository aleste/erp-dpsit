<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Usuarios y Permisos</h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Cada usuario accede solo a los módulos asignados; el administrador accede a todos y gestiona los permisos.
          Los usuarios no se eliminan, se desactivan.
        </p>
      </div>
      <button
        v-if="authStore.isAdmin"
        @click="abrirFormulario()"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-700 hover:bg-primary-600 text-white rounded-xl text-sm font-bold shadow-md shadow-primary-700/20 transition"
      >
        <span>+</span> Nuevo Usuario
      </button>
    </div>

    <div v-if="!authStore.isAdmin" class="p-6 bg-white rounded-2xl border border-slate-200 text-sm text-slate-500">
      Solo el perfil Administrador puede gestionar usuarios.
    </div>

    <template v-else>
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="sm:col-span-2">
          <label for="usuarios-filtros-q" class="filtro-label">Buscar</label>
          <input id="usuarios-filtros-q" v-model="filtros.q" @input="buscarConDemora" type="text" placeholder="Nombre o email..." class="filtro" />
        </div>
        <div>
          <label for="usuarios-filtros-perfil" class="filtro-label">Tipo</label>
          <select id="usuarios-filtros-perfil" v-model="filtros.perfil" @change="cargar" class="filtro">
            <option value="">Todos</option>
            <option value="ADMINISTRADOR">Administradores</option>
            <option value="USUARIO">Usuarios</option>
          </select>
        </div>
        <div>
          <label for="usuarios-filtros-modulo" class="filtro-label">Con acceso a</label>
          <select id="usuarios-filtros-modulo" v-model="filtros.modulo" @change="cargar" class="filtro">
            <option value="">Cualquier módulo</option>
            <option v-for="m in catalogo" :key="m.clave" :value="m.clave">{{ m.nombre }}</option>
          </select>
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
        <div v-if="cargando" class="p-12 text-center text-sm text-slate-500">Cargando usuarios...</div>
        <div v-else-if="usuarios.length === 0" class="p-12 text-center text-sm text-slate-500">No hay usuarios con esos criterios.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-xs">
                <th class="py-3.5 px-4">Usuario</th>
                <th class="py-3.5 px-4">Tipo</th>
                <th class="py-3.5 px-4">Módulos y roles</th>
                <th class="py-3.5 px-4">Estado</th>
                <th class="py-3.5 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="u in usuarios" :key="u.id" class="hover:bg-slate-50/80 transition" :class="!u.activo && 'opacity-60'">
                <td class="py-3.5 px-4">
                  <p class="font-extrabold text-slate-900">{{ u.name }}</p>
                  <p class="text-xs text-slate-500">{{ u.email }}</p>
                </td>
                <td class="py-3.5 px-4">
                  <span
                    v-if="u.perfil_acceso === 'ADMINISTRADOR'"
                    class="inline-flex px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold uppercase"
                  >
                    Administrador
                  </span>
                  <span v-else class="font-semibold">Usuario</span>
                </td>
                <td class="py-3.5 px-4">
                  <span v-if="u.perfil_acceso === 'ADMINISTRADOR'" class="text-slate-600">Todos los módulos</span>
                  <span
                    v-else-if="!u.modulos?.length"
                    class="inline-flex px-2 py-0.5 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold uppercase"
                    title="No puede usar ningún módulo hasta que se le asigne uno"
                  >
                    Sin módulos
                  </span>
                  <span
                    v-for="m in u.perfil_acceso === 'ADMINISTRADOR' ? [] : u.modulos"
                    :key="m.modulo"
                    class="inline-flex items-center px-2 py-0.5 mr-1 mb-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-bold"
                  >
                    {{ nombreModulo(m.modulo) }}: {{ NOMBRE_ROL[m.rol] || m.rol }}
                  </span>
                  <p v-if="u.ultimo_cambio_permisos" class="text-xs text-slate-500 mt-1">
                    Último cambio: {{ formatFechaHora(u.ultimo_cambio_permisos.fecha) }}
                    <template v-if="u.ultimo_cambio_permisos.autor">· {{ u.ultimo_cambio_permisos.autor }}</template>
                  </p>
                </td>
                <td class="py-3.5 px-4">
                  <span class="text-xs font-bold uppercase" :class="u.activo ? 'text-emerald-700' : 'text-rose-700'">
                    {{ u.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <button @click="abrirFormulario(u)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700">
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
      <div v-dialogo="() => (formulario.visible = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">{{ formulario.id ? 'Editar Usuario' : 'Nuevo Usuario' }}</h2>
            <p class="text-sm text-slate-400">Tipo de usuario y permisos por módulo</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="formulario.visible = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="guardar" class="p-6 space-y-4 text-sm">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
              <label for="usuarios-formulario-name" class="block font-bold text-slate-700 mb-1">Nombre y apellido *</label>
              <input id="usuarios-formulario-name" v-model="formulario.name" type="text" required class="campo" />
            </div>
            <div class="sm:col-span-2">
              <label for="usuarios-formulario-email" class="block font-bold text-slate-700 mb-1">Email (usuario de acceso) *</label>
              <input id="usuarios-formulario-email" v-model="formulario.email" type="email" required class="campo" />
            </div>
            <div>
              <label for="usuarios-formulario-perfil-acceso" class="block font-bold text-slate-700 mb-1">Tipo de usuario *</label>
              <select id="usuarios-formulario-perfil-acceso" v-model="formulario.perfil_acceso" required class="campo">
                <option value="USUARIO">Usuario (módulos asignados)</option>
                <option value="ADMINISTRADOR">Administrador (todo el sistema)</option>
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

          <div v-if="formulario.perfil_acceso === 'ADMINISTRADOR'" class="p-4 rounded-xl border border-rose-200 bg-rose-50 text-rose-800">
            <p class="font-bold">Accede a todos los módulos con el rol máximo</p>
            <p class="text-xs mt-1">Además gestiona usuarios y permisos, y es el único que puede dar de baja registros.</p>
          </div>

          <div v-else class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-3">
            <p class="font-bold text-slate-700">Módulos y roles</p>
            <p class="text-xs text-slate-500">
              Consulta: solo lectura. Operador: carga y edición. Supervisor: reasigna, reabre y corrige (Expedientes).
            </p>
            <div v-for="m in catalogo" :key="m.clave" class="grid grid-cols-1 sm:grid-cols-2 gap-1 sm:gap-3 items-center">
              <label :for="`usuarios-formulario-modulo-${m.clave}`" class="font-semibold text-slate-700">{{ m.nombre }}</label>
              <select :id="`usuarios-formulario-modulo-${m.clave}`" v-model="formulario.modulos[m.clave]" class="campo">
                <option value="">Sin acceso</option>
                <option v-for="r in m.roles" :key="r" :value="r">{{ DESCRIPCION_ROL[r] || r }}</option>
              </select>
            </div>
            <p v-if="sinModulos" role="status" class="text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
              Sin ningún módulo, el usuario podrá iniciar sesión pero no usar el sistema.
            </p>
          </div>

          <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
            <input v-model="formulario.activo" type="checkbox" class="w-4 h-4 text-primary-700 rounded border-slate-300" />
            Usuario activo (al desactivarlo se cierran sus sesiones)
          </label>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="formulario.visible = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">
              Cancelar
            </button>
            <button type="submit" :disabled="guardando" class="px-5 py-2 bg-primary-700 hover:bg-primary-600 text-white rounded-xl font-bold shadow-md shadow-primary-700/20 disabled:opacity-50">
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { formatFechaHora } from '~/composables/useFechas'

const { get, post, put } = useApi()
const authStore = useAuthStore()
const { toast, confirmDialog } = useToast()

const NOMBRE_ROL = { CONSULTA: 'Consulta', OPERADOR: 'Operador', SUPERVISOR: 'Supervisor' }
const DESCRIPCION_ROL = {
  CONSULTA: 'Consulta (solo lectura)',
  OPERADOR: 'Operador (carga y edición)',
  SUPERVISOR: 'Supervisor (reasigna, reabre, corrige)',
}

const usuarios = ref([])
// Catálogo de módulos que publica el backend (D-34): un módulo nuevo aparece aquí sin tocar esta página
const catalogo = ref(authStore.catalogo)
const cargando = ref(false)
const guardando = ref(false)
const filtros = reactive({ q: '', activo: '', perfil: '', modulo: '' })

const formulario = reactive({
  visible: false, id: null, name: '', email: '', perfil_acceso: 'USUARIO', perfilOriginal: null, password: '', activo: true, modulos: {},
})

const nombreModulo = (clave) => catalogo.value.find(m => m.clave === clave)?.nombre || clave
const sinModulos = computed(() => formulario.activo && !Object.values(formulario.modulos).some(Boolean))

const cargar = async () => {
  if (!authStore.isAdmin) return
  cargando.value = true
  try {
    const res = await get('/usuarios', filtros)
    if (res.success) {
      usuarios.value = res.data
      if (res.meta?.catalogo_modulos) catalogo.value = res.meta.catalogo_modulos
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
  const modulos = Object.fromEntries(catalogo.value.map(m => [m.clave, '']))
  for (const m of u?.modulos || []) modulos[m.modulo] = m.rol
  Object.assign(formulario, {
    visible: true,
    id: u?.id || null,
    name: u?.name || '',
    email: u?.email || '',
    perfil_acceso: u?.perfil_acceso || 'USUARIO',
    perfilOriginal: u?.perfil_acceso || null,
    password: '',
    activo: u ? u.activo : true,
    modulos,
  })
}

// Otorgar o quitar el perfil de administrador se confirma aparte
const confirmarCambioDePerfil = () => {
  const otorga = formulario.perfil_acceso === 'ADMINISTRADOR' && formulario.perfilOriginal !== 'ADMINISTRADOR'
  const quita = formulario.perfil_acceso !== 'ADMINISTRADOR' && formulario.perfilOriginal === 'ADMINISTRADOR'
  if (!otorga && !quita) return Promise.resolve(true)
  return confirmDialog({
    title: otorga ? 'Otorgar perfil de administrador' : 'Quitar perfil de administrador',
    message: otorga
      ? `${formulario.name} tendrá acceso a todos los módulos y podrá gestionar usuarios y permisos.`
      : `${formulario.name} solo accederá a los módulos que se le asignen.`,
    confirmText: otorga ? 'Sí, otorgar' : 'Sí, quitar',
    cancelText: 'Cancelar',
    type: 'warning',
  })
}

const guardar = async () => {
  if (!(await confirmarCambioDePerfil())) return
  guardando.value = true
  try {
    const esAdmin = formulario.perfil_acceso === 'ADMINISTRADOR'
    const payload = {
      name: formulario.name,
      email: formulario.email,
      perfil_acceso: formulario.perfil_acceso,
      activo: formulario.activo,
      password: formulario.password || null,
      modulos: esAdmin ? [] : Object.entries(formulario.modulos).filter(([, rol]) => rol).map(([modulo, rol]) => ({ modulo, rol })),
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
  @apply w-full p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600;
}
.filtro {
  @apply w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600 transition;
}
.filtro-label {
  @apply block text-sm font-bold text-slate-500 mb-1;
}
</style>
