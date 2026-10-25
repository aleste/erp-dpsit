<template>
  <div>
    <!-- Cabecera Institucional -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-700 mb-4">
        <span class="text-white font-extrabold text-2xl tracking-wider">{{ agency.acronym }}</span>
      </div>
      <h2 class="text-2xl font-extrabold text-white tracking-tight">ERP {{ agency.shortName }}</h2>
      <p class="text-xs text-slate-400 mt-1 uppercase tracking-widest font-semibold">
        {{ agency.name }}
      </p>
    </div>

    <!-- Formulario de Acceso -->
    <div class="bg-slate-900/90 backdrop-blur-md py-8 px-6 shadow-2xl rounded-2xl sm:px-10 border border-slate-800">
      <form class="space-y-5" @submit.prevent="handleLogin">
        <div v-if="errorMessage" class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-300 text-sm flex items-center gap-2">
          <span>⚠️</span>
          <span>{{ errorMessage }}</span>
        </div>

        <div>
          <label for="login-form-email" class="block text-sm font-semibold text-slate-300 mb-1.5">Correo Electrónico</label>
          <input id="login-form-email"
            v-model="form.email"
            type="email"
            required
            placeholder="ejemplo@dpsit.gob.ar"
            class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition"
          />
        </div>

        <div>
          <label for="login-form-password" class="block text-sm font-semibold text-slate-300 mb-1.5">Contraseña</label>
          <input id="login-form-password"
            v-model="form.password"
            type="password"
            required
            placeholder="••••••••"
            class="w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:border-transparent transition"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-2.5 px-4 bg-primary-700 hover:bg-primary-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-primary-700/30 hover:shadow-primary-500/40 focus:outline-none focus:ring-2 focus:ring-primary-600 transition disabled:opacity-50"
        >
          {{ loading ? 'Iniciando sesión...' : 'Ingresar a la Plataforma' }}
        </button>
      </form>

      <!-- Selector Rápido de Perfiles Demo -->
      <div class="mt-8 pt-6 border-t border-slate-800">
        <p class="text-xs font-semibold text-slate-400 text-center uppercase tracking-wider mb-3">
          Perfiles de Prueba Rápidos (Demo)
        </p>
        <div class="grid grid-cols-3 gap-2">
          <button
            type="button"
            @click="setProfile('admin@dpsit.gob.ar', 'admin123')"
            class="py-2 px-2 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-primary-400 rounded-lg border border-slate-700 transition text-center"
          >
            Admin
          </button>
          <button
            type="button"
            @click="setProfile('editor@dpsit.gob.ar', 'editor123')"
            class="py-2 px-2 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-lg border border-slate-700 transition text-center"
          >
            Editor
          </button>
          <button
            type="button"
            @click="setProfile('lector@dpsit.gob.ar', 'lector123')"
            class="py-2 px-2 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded-lg border border-slate-700 transition text-center"
          >
            Lector
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'auth'
})

import { reactive, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useAgency } from '~/composables/useAgency'
import { useToast } from '~/composables/useToast'

const authStore = useAuthStore()
const agency = useAgency()
const config = useRuntimeConfig()
const { toast } = useToast()

const form = reactive({
  email: 'admin@dpsit.gob.ar',
  password: 'admin123'
})

const loading = ref(false)
const errorMessage = ref('')

const setProfile = (email, pass) => {
  form.email = email
  form.password = pass
  errorMessage.value = ''
}

const handleLogin = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const res = await $fetch(`${config.public.apiBase}/auth/login`, {
      method: 'POST',
      body: form
    })

    if (res.success && res.token) {
      authStore.setAuth(res.user, res.token)
      toast.success(`Bienvenido/a al Sistema de Gestión, ${res.user?.name || 'Usuario'}.`, { title: 'Sesión Iniciada' })
      navigateTo('/')
    }
  } catch (err) {
    const formatted = err?.data?.message ? err.data.message : 'Error al conectar con el servidor de autenticación.'
    errorMessage.value = formatted
    toast.error(err, { title: 'Acceso Denegado' })
  } finally {
    loading.value = false
  }
}
</script>
