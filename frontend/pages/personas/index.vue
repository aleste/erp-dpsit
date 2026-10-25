<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado y Barra de Acción Superior -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Directorio de Personas y Perfiles TI</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Gestión transversal del padrón de profesionales, autoridades, agentes y roles tecnológicos en el Estado Provincial.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="authStore.isEditor"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition"
        >
          <span>+</span> Nueva Persona
        </button>

        <button
          v-if="authStore.isEditor"
          @click="openNewAsignacionModalDirect"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition"
        >
          <span>⭐+</span> Asignar Cargo / Rol
        </button>
      </div>
    </div>

    <!-- Indicadores Rápidos del Padrón -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total en Padrón</p>
          <h3 class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ stats.total }}</h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-base">
          👥
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Con Cargo Vigente</p>
          <h3 class="text-2xl font-extrabold text-emerald-600 mt-0.5">{{ stats.con_cargo_vigente }}</h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
          💼
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-amber-600">Máximas Autoridades</p>
          <h3 class="text-2xl font-extrabold text-amber-600 mt-0.5">{{ stats.maximas_autoridades }}</h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-base">
          ⭐
        </div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Referentes Servicios</p>
          <h3 class="text-2xl font-extrabold text-indigo-600 mt-0.5">{{ stats.referentes_servicios }}</h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base">
          ⚡
        </div>
      </div>
    </div>

    <!-- Pestañas del Módulo -->
    <div class="flex border-b border-slate-200 space-x-6">
      <button
        @click="activeTab = 'padron'"
        class="pb-3 text-xs font-extrabold border-b-2 transition flex items-center gap-2"
        :class="activeTab === 'padron' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        <span>👤</span> Padrón General de Personas
      </button>

      <button
        @click="activeTab = 'roles'"
        class="pb-3 text-xs font-extrabold border-b-2 transition flex items-center gap-2"
        :class="activeTab === 'roles' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        <span>🏷️</span> Mapa por Perfiles y Roles TI
      </button>
    </div>

    <!-- ===================================================================== -->
    <!-- PESTAÑA 1: PADRÓN GENERAL DE PERSONAS                                 -->
    <!-- ===================================================================== -->
    <div v-if="activeTab === 'padron'" class="space-y-4">
      <!-- Filtros de Búsqueda Avanzada -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <!-- Búsqueda rápida de texto -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Buscar Persona</label>
            <input
              v-model="filters.q"
              @input="debounceSearch"
              type="text"
              placeholder="Buscar por Nombre, Apellido, CUIL, Email o Título..."
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition"
            />
          </div>

          <!-- Filtro por Ministerio -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Ministerio</label>
            <select
              v-model="filters.ministerio_id"
              @change="fetchPersonas"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todos los Ministerios</option>
              <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.sigla }} - {{ m.nombre }}</option>
            </select>
          </div>

          <!-- Filtro por Tipo de Rol -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Rol / Función</label>
            <select
              v-model="filters.tipo_rol_id"
              @change="fetchPersonas"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todos los Roles</option>
              <option v-for="r in tiposRol" :key="r.id" :value="r.id">{{ r.nombre }}</option>
            </select>
          </div>

          <!-- Filtro por Estado de Cargo -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Estado de Cargo</label>
            <select
              v-model="filters.estado_cargo"
              @change="fetchPersonas"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todos los Estados</option>
              <option value="VIGENTE">Con Cargo Vigente</option>
              <option value="SIN_CARGO">Sin Cargo Activo</option>
            </select>
          </div>
        </div>

        <!-- Filtros Rápidos (Toggles) -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
          <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
            <input
              v-model="filters.solo_autoridades"
              @change="fetchPersonas"
              type="checkbox"
              class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
            />
            <span>⭐ Solo Máximas Autoridades TI Vigentes</span>
          </label>

          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="text-xs font-bold text-sky-600 hover:text-sky-800 hover:underline"
          >
            Limpiar Filtros
          </button>
        </div>
      </div>

      <!-- Tabla de Personas -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div v-if="loading" class="p-12 text-center text-xs text-slate-400">
          Cargando directorio de personas...
        </div>

        <div v-else-if="personas.length === 0" class="p-12 text-center text-xs text-slate-400">
          No se encontraron personas con los criterios de búsqueda especificados.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4">Persona / Profesional</th>
                <th class="py-3.5 px-4">Contacto Institucional</th>
                <th class="py-3.5 px-4">Cargos / Funciones Vigentes</th>
                <th class="py-3.5 px-4 text-center">Referente de Servicios</th>
                <th class="py-3.5 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="p in personas" :key="p.id" class="hover:bg-slate-50/80 transition">
                <!-- Datos de la Persona -->
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-100 to-sky-200 text-sky-800 font-extrabold flex items-center justify-center text-xs flex-shrink-0 shadow-sm">
                      {{ p.apellido.charAt(0) }}{{ p.nombre.charAt(0) }}
                    </div>
                    <div>
                      <button @click="openHistoryModal(p.id)" class="font-extrabold text-slate-900 hover:text-sky-600 transition text-left text-xs">
                        {{ p.apellido }}, {{ p.nombre }}
                      </button>
                      <p class="text-[11px] text-slate-400 font-mono">{{ identificacion(p) }}</p>
                      <p v-if="p.titulo_profesional" class="text-[10px] text-indigo-600 font-bold">
                        🎓 {{ p.titulo_profesional }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Contacto -->
                <td class="py-3.5 px-4 space-y-0.5">
                  <div v-if="p.email_principal" class="flex items-center gap-1.5 text-slate-800">
                    <span class="text-slate-400 text-[10px]">✉️</span>
                    <a :href="`mailto:${p.email_principal}`" class="hover:text-sky-600 font-medium">{{ p.email_principal }}</a>
                  </div>
                  <div v-else-if="!p.email_alternativo" class="text-[11px] text-orange-600">Sin email registrado</div>
                  <div v-if="p.email_alternativo" class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                    <span class="text-slate-400 text-[10px]">↳</span>
                    <span>{{ p.email_alternativo }}</span>
                  </div>
                  <div v-if="p.telefono_principal" class="flex items-center gap-1.5 text-slate-600 text-[11px]">
                    <span class="text-slate-400 text-[10px]">📞</span>
                    <span>{{ p.telefono_principal }}</span>
                  </div>
                </td>

                <!-- Asignaciones Vigentes -->
                <td class="py-3.5 px-4">
                  <div v-if="p.asignaciones_vigentes && p.asignaciones_vigentes.length > 0" class="space-y-1.5">
                    <div
                      v-for="asig in p.asignaciones_vigentes"
                      :key="asig.id"
                      class="inline-flex flex-col bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200/80 mr-1.5 mb-1 shadow-2xs"
                    >
                      <div class="flex items-center gap-1.5">
                        <span class="font-bold text-sky-900">{{ asig.tipo_rol?.nombre }}</span>
                        <span v-if="asig.es_maxima_autoridad" class="text-amber-500 text-[11px]" title="Máxima Autoridad TI">⭐</span>
                      </div>
                      <span class="text-[10px] text-slate-500 font-medium">
                        {{ asig.area_ti?.ministerio?.sigla }} - {{ asig.area_ti?.nombre }}
                      </span>
                    </div>
                  </div>
                  <span v-else class="text-slate-400 italic text-[11px] bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">
                    Sin asignación vigente
                  </span>
                </td>

                <!-- Referente de Servicios -->
                <td class="py-3.5 px-4 text-center">
                  <span
                    v-if="p.servicios_como_referente_count > 0"
                    class="px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg font-bold text-[11px] inline-flex items-center gap-1"
                  >
                    <span>⚡</span> {{ p.servicios_como_referente_count }} servicios
                  </span>
                  <span v-else class="text-slate-300">-</span>
                </td>

                <!-- Acciones -->
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openHistoryModal(p.id)"
                      class="px-2.5 py-1.5 bg-slate-100 hover:bg-sky-50 text-slate-700 hover:text-sky-700 rounded-lg text-xs font-bold transition flex items-center gap-1"
                      title="Ver expediente y trayectoria completa"
                    >
                      <span>🔍</span> Ver Ficha
                    </button>

                    <button
                      v-if="authStore.isEditor"
                      @click="openEditModal(p)"
                      class="p-1.5 text-slate-500 hover:text-sky-600 hover:bg-slate-100 rounded-lg transition"
                      title="Editar datos de la persona"
                    >
                      ✏️
                    </button>

                    <button
                      v-if="authStore.isEditor"
                      @click="openAsignarModalParaPersona(p)"
                      class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                      title="Asignar nuevo cargo a esta persona"
                    >
                      ➕
                    </button>

                    <button
                      v-if="authStore.isAdmin"
                      @click="confirmDeletePersona(p)"
                      class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                      title="Eliminar del padrón"
                    >
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <div v-if="pagination.total > 0" class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500">
          <div>
            Mostrando <strong>{{ pagination.from || 0 }}</strong> a <strong>{{ pagination.to || 0 }}</strong> de <strong>{{ pagination.total }}</strong> personas
          </div>

          <div class="flex items-center gap-2">
            <button
              :disabled="pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold hover:bg-slate-100 disabled:opacity-40 transition"
            >
              ← Anterior
            </button>

            <span class="font-bold text-slate-700 px-2">
              Página {{ pagination.current_page }} de {{ pagination.last_page }}
            </span>

            <button
              :disabled="pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold hover:bg-slate-100 disabled:opacity-40 transition"
            >
              Siguiente →
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- PESTAÑA 2: MAPA / MATRIZ DE ROLES Y PERFILES TI                       -->
    <!-- ===================================================================== -->
    <div v-if="activeTab === 'roles'" class="space-y-6">
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <p class="text-xs text-slate-500">
          Exploración de la estructura de capital humano clasificada por nivel de responsabilidad y especialidad tecnológica.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="rol in tiposRol"
          :key="rol.id"
          class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-3 flex flex-col justify-between"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="px-2.5 py-1 bg-slate-100 text-slate-700 font-extrabold rounded-lg text-[10px] uppercase tracking-wider">
                {{ rol.nivel_responsabilidad }}
              </span>
              <span class="font-mono text-xs font-bold text-sky-600 bg-sky-50 px-2 py-0.5 rounded">
                {{ rol.codigo }}
              </span>
            </div>

            <h3 class="text-base font-bold text-slate-900">{{ rol.nombre }}</h3>
            <p class="text-xs text-slate-500 leading-relaxed">{{ rol.descripcion || 'Sin descripción detallada.' }}</p>
          </div>

          <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <NuxtLink
              :to="`/roles?tipo_rol_id=${rol.id}`"
              class="font-bold text-sky-600 hover:text-sky-800 transition"
            >
              Ver Agentes con este Rol →
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: CREAR / EDITAR PERSONA                                         -->
    <!-- ===================================================================== -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">{{ isEditing ? 'Editar Datos de la Persona' : 'Registrar Nueva Persona' }}</h2>
            <p class="text-xs text-slate-400">Padrón de Capital Humano TI Provincial</p>
          </div>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="savePersona" class="p-6 space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">CUIL / CUIT</label>
              <input
                v-model="formPersona.cuil_cuit"
                type="text"
                placeholder="20-12345678-9"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Título Profesional</label>
              <input
                v-model="formPersona.titulo_profesional"
                type="text"
                placeholder="Ej: Lic. en Sistemas, Ing. TI"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Tipo de Documento</label>
              <select
                v-model="formPersona.tipo_documento"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              >
                <option value="">Sin documento</option>
                <option v-for="t in tiposDocumento" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Número de Documento</label>
              <input
                v-model="formPersona.numero_documento"
                type="text"
                :required="!!formPersona.tipo_documento"
                placeholder="Ej: 30123456"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Nombre *</label>
              <input
                v-model="formPersona.nombre"
                type="text"
                required
                placeholder="Ej: Juan Martín"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Apellido *</label>
              <input
                v-model="formPersona.apellido"
                type="text"
                required
                placeholder="Ej: Pérez"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Email Principal</label>
              <input
                v-model="formPersona.email_principal"
                type="email"
                placeholder="jperez@organismo.gob.ar"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Email Alternativo</label>
              <input
                v-model="formPersona.email_alternativo"
                type="email"
                placeholder="jperez@gmail.com"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Teléfono Principal</label>
              <input
                v-model="formPersona.telefono_principal"
                type="text"
                placeholder="0221-429-0000"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Teléfono Alternativo</label>
              <input
                v-model="formPersona.telefono_alternativo"
                type="text"
                placeholder="0221-15-123456"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Observaciones</label>
            <textarea
              v-model="formPersona.observaciones"
              rows="2"
              placeholder="Notas sobre especialidad, capacitaciones o perfil..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
            >
              {{ saving ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Registrar Persona') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: FICHA INTEGRAL DE LA PERSONA (EXPEDIENTE)                      -->
    <!-- ===================================================================== -->
    <div v-if="selectedPersona" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden">
        <!-- Header Modal -->
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-sky-500 text-white font-extrabold flex items-center justify-center text-base shadow-inner">
              {{ selectedPersona.apellido.charAt(0) }}{{ selectedPersona.nombre.charAt(0) }}
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-sky-400 tracking-wider">Ficha Integral de Capital Humano</span>
              <h2 class="text-xl font-bold mt-0.5">{{ selectedPersona.apellido }}, {{ selectedPersona.nombre }}</h2>
              <p class="text-xs text-slate-300">{{ identificacion(selectedPersona) }} | {{ selectedPersona.titulo_profesional || 'Profesional TI' }}</p>
            </div>
          </div>
          <button @click="selectedPersona = null" class="p-2 text-slate-400 hover:text-white rounded-lg">✕</button>
        </div>

        <!-- Contenido Modal -->
        <div class="p-6 overflow-y-auto space-y-6 flex-1 custom-scrollbar text-xs">
          <!-- Datos de Contacto -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div>
              <p class="text-slate-400 font-bold uppercase text-[10px]">Correos Electrónicos</p>
              <p class="font-semibold text-slate-800 mt-0.5">✉️ {{ selectedPersona.email_principal || 'Sin email registrado' }} (Principal)</p>
              <p v-if="selectedPersona.email_alternativo" class="text-slate-600 mt-0.5">↳ {{ selectedPersona.email_alternativo }} (Alternativo)</p>
            </div>
            <div>
              <p class="text-slate-400 font-bold uppercase text-[10px]">Teléfonos de Contacto</p>
              <p class="font-semibold text-slate-800 mt-0.5">📞 {{ selectedPersona.telefono_principal || 'Sin teléfono registrado' }} (Principal)</p>
              <p v-if="selectedPersona.telefono_alternativo" class="text-slate-600 mt-0.5">↳ {{ selectedPersona.telefono_alternativo }} (Alternativo)</p>
            </div>
          </div>

          <!-- Trayectoria y Roles -->
          <div>
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                Trayectoria Institucional y Roles Designados
              </h3>
              <button
                v-if="authStore.isEditor"
                @click="openAsignarModalParaPersona(selectedPersona)"
                class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg font-bold text-[11px] transition"
              >
                + Asignar Nuevo Cargo
              </button>
            </div>

            <div v-if="!selectedPersona.asignaciones || selectedPersona.asignaciones.length === 0" class="p-4 bg-slate-50 rounded-xl text-slate-400 italic text-center">
              No registra asignaciones de rol en la administración pública.
            </div>

            <div v-else class="relative border-l-2 border-slate-200 ml-3 space-y-4 pl-5">
              <div
                v-for="asig in selectedPersona.asignaciones"
                :key="asig.id"
                class="relative group"
              >
                <!-- Punto en el timeline -->
                <div
                  class="absolute -left-[27px] top-0.5 w-3.5 h-3.5 rounded-full border-2 border-white"
                  :class="asig.estado_vigencia === 'VIGENTE' ? 'bg-emerald-500 ring-4 ring-emerald-100' : 'bg-slate-400'"
                ></div>

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 space-y-2">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <span class="font-extrabold text-sm text-slate-900">{{ asig.tipo_rol?.nombre }}</span>
                      <span v-if="asig.es_maxima_autoridad" class="text-amber-500 font-bold" title="Máxima Autoridad TI">⭐ Autoridad</span>
                    </div>
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                      :class="asig.estado_vigencia === 'VIGENTE' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                    >
                      {{ asig.estado_vigencia }}
                    </span>
                  </div>

                  <p class="text-xs text-slate-700 font-semibold">
                    {{ asig.area_ti?.ministerio?.sigla }} - {{ asig.area_ti?.nombre }}
                  </p>

                  <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-500">
                    <span>🗓️ Desde: <strong>{{ asig.fecha_inicio }}</strong></span>
                    <span>🗓️ Hasta: <strong>{{ asig.fecha_fin || 'Presente (Indefinido)' }}</strong></span>
                    <span v-if="asig.acto_administrativo" class="font-mono text-slate-600">Acto: {{ asig.acto_administrativo }}</span>
                  </div>

                  <div v-if="authStore.isEditor && asig.estado_vigencia === 'VIGENTE'" class="pt-2 border-t border-slate-200/60 flex justify-end">
                    <button
                      @click="finalizarVigenciaDesdeFicha(asig)"
                      class="text-[11px] font-bold text-amber-700 hover:text-amber-900 hover:underline"
                    >
                      🛑 Finalizar Vigencia / Registrar Cese
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Servicios Tecnológicos de los que es Referente -->
          <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
              Servicios Tecnológicos Provincialmente Designados
            </h3>

            <div v-if="!selectedPersona.servicios_como_referente || selectedPersona.servicios_como_referente.length === 0" class="p-4 bg-slate-50 rounded-xl text-slate-400 italic text-center">
              No se encuentra registrado como referente específico en servicios provinciales.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div
                v-for="srvRef in selectedPersona.servicios_como_referente"
                :key="srvRef.id"
                class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1"
              >
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[10px] font-bold bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-700">
                    {{ srvRef.servicio_area?.codigo_instancia }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-sky-100 text-sky-800">
                    {{ srvRef.tipo_referente }}
                  </span>
                </div>
                <h4 class="font-bold text-slate-800 mt-1">{{ srvRef.servicio_area?.catalogo_servicio?.nombre }}</h4>
                <p class="text-[11px] text-slate-500">{{ srvRef.servicio_area?.area_ti?.ministerio?.sigla }} - {{ srvRef.servicio_area?.area_ti?.nombre }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Modal -->
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
          <button @click="selectedPersona = null" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold">
            Cerrar Ficha
          </button>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: ASIGNAR CARGO / ROL                                            -->
    <!-- ===================================================================== -->
    <div v-if="showModalAsignar" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-indigo-950 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">Asignar Cargo / Rol Institucional</h2>
            <p class="text-xs text-indigo-300">Vinculación formal a un Organismo / Área TI</p>
          </div>
          <button @click="showModalAsignar = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveAsignacion" class="p-6 space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Persona / Agente *</label>
            <PersonaSearchSelect
              v-model="formAsig.persona_id"
              placeholder="Buscar agente por apellido, nombre o CUIL..."
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Área TI de Destino *</label>
            <select
              v-model="formAsig.area_ti_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="" disabled>Seleccione Área TI...</option>
              <option v-for="a in areasList" :key="a.id" :value="a.id">
                {{ a.codigo }} - {{ a.nombre }} ({{ a.ministerio?.sigla }})
              </option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Cargo / Tipo de Rol *</label>
            <select
              v-model="formAsig.tipo_rol_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="" disabled>Seleccione Rol...</option>
              <option v-for="r in tiposRol" :key="r.id" :value="r.id">
                {{ r.nombre }} ({{ r.nivel_responsabilidad }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Fecha de Inicio *</label>
              <input
                v-model="formAsig.fecha_inicio"
                type="date"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Acto de Designación</label>
              <input
                v-model="formAsig.acto_administrativo"
                type="text"
                placeholder="Ej: Decreto 1250/2024"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
            <label class="flex items-center gap-2 cursor-pointer font-bold text-slate-800">
              <input
                type="checkbox"
                v-model="formAsig.es_maxima_autoridad"
                class="w-4 h-4 text-amber-500 rounded border-slate-300 focus:ring-amber-500"
              />
              ⭐ Designar formalmente como Máxima Autoridad del Área
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalAsignar = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingAsignacion"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold shadow-md shadow-indigo-600/20 disabled:opacity-50"
            >
              {{ savingAsignacion ? 'Guardando...' : 'Asignar Cargo' }}
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

const { get, post, put, delete: del } = useApi()
const authStore = useAuthStore()
const { toast, confirmDialog } = useToast()

const activeTab = ref('padron')

const personas = ref([])
const ministerios = ref([])
const tiposRol = ref([])
const areasList = ref([])

const loading = ref(false)
const saving = ref(false)
const savingAsignacion = ref(false)

const stats = reactive({
  total: 0,
  con_cargo_vigente: 0,
  maximas_autoridades: 0,
  referentes_servicios: 0,
})

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  from: 0,
  to: 0,
  per_page: 20,
})

const filters = reactive({
  q: '',
  ministerio_id: '',
  tipo_rol_id: '',
  estado_cargo: '',
  solo_autoridades: false,
})

// Modales
const selectedPersona = ref(null)
const showCreateModal = ref(false)
const isEditing = ref(false)

const tiposDocumento = ['DNI', 'PASAPORTE', 'CI', 'LC', 'LE', 'OTRO']

// CUIL o, si no tiene, documento de identidad (personas externas al ERP)
const identificacion = (p) => {
  if (p?.cuil_cuit) return `CUIL: ${p.cuil_cuit}`
  if (p?.numero_documento) return `${p.tipo_documento}: ${p.numero_documento}`
  return 'Sin CUIL / documento'
}

const formPersona = reactive({
  id: null,
  cuil_cuit: '',
  tipo_documento: '',
  numero_documento: '',
  apellido: '',
  nombre: '',
  email_principal: '',
  email_alternativo: '',
  telefono_principal: '',
  telefono_alternativo: '',
  titulo_profesional: '',
  observaciones: '',
  activo: true,
})

const showModalAsignar = ref(false)
const formAsig = reactive({
  persona_id: '',
  area_ti_id: '',
  tipo_rol_id: '',
  fecha_inicio: new Date().toISOString().split('T')[0],
  acto_administrativo: '',
  es_maxima_autoridad: false,
  observaciones: '',
})

const hasActiveFilters = computed(() => {
  return !!(filters.q || filters.ministerio_id || filters.tipo_rol_id || filters.estado_cargo || filters.solo_autoridades)
})

const resetFilters = () => {
  filters.q = ''
  filters.ministerio_id = ''
  filters.tipo_rol_id = ''
  filters.estado_cargo = ''
  filters.solo_autoridades = false
  pagination.current_page = 1
  fetchPersonas()
}

let searchTimeout = null
const debounceSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1
    fetchPersonas()
  }, 350)
}

const changePage = (page) => {
  pagination.current_page = page
  fetchPersonas()
}

const fetchPersonas = async () => {
  loading.value = true
  try {
    const params = {
      ...filters,
      page: pagination.current_page,
      per_page: pagination.per_page,
    }
    const res = await get('/personas', params)
    if (res.success) {
      personas.value = res.data.data
      pagination.current_page = res.data.current_page
      pagination.last_page = res.data.last_page
      pagination.total = res.data.total
      pagination.from = res.data.from
      pagination.to = res.data.to

      if (res.stats) {
        Object.assign(stats, res.stats)
      }
    }
  } catch (err) {
    console.error('Error fetching personas:', err)
    toast.error(err, { title: 'Error al cargar directorio' })
  } finally {
    loading.value = false
  }
}

const fetchCatalogos = async () => {
  try {
    const [resMin, resRol] = await Promise.all([
      get('/ministerios'),
      get('/tipos-rol'),
    ])
    if (resMin.success) ministerios.value = resMin.data
    if (resRol.success) tiposRol.value = resRol.data
  } catch (err) {
    console.error('Error fetching catalogos:', err)
  }
}

const fetchAuxiliares = async () => {
  try {
    const resA = await get('/areas-ti')
    if (resA.success) areasList.value = resA.data
  } catch (err) {
    console.error('Error fetching auxiliares:', err)
  }
}

// -------------------------------------------------------------
// CREAR / EDITAR PERSONA
// -------------------------------------------------------------
const openCreateModal = () => {
  isEditing.value = false
  Object.assign(formPersona, {
    id: null,
    cuil_cuit: '',
    tipo_documento: '',
    numero_documento: '',
    apellido: '',
    nombre: '',
    email_principal: '',
    email_alternativo: '',
    telefono_principal: '',
    telefono_alternativo: '',
    titulo_profesional: '',
    observaciones: '',
    activo: true,
  })
  showCreateModal.value = true
}

const openEditModal = (p) => {
  isEditing.value = true
  Object.assign(formPersona, {
    id: p.id,
    cuil_cuit: p.cuil_cuit || '',
    tipo_documento: p.tipo_documento || '',
    numero_documento: p.numero_documento || '',
    apellido: p.apellido,
    nombre: p.nombre,
    email_principal: p.email_principal || '',
    email_alternativo: p.email_alternativo || '',
    telefono_principal: p.telefono_principal || '',
    telefono_alternativo: p.telefono_alternativo || '',
    titulo_profesional: p.titulo_profesional || '',
    observaciones: p.observaciones || '',
    activo: p.activo,
  })
  showCreateModal.value = true
}

const savePersona = async () => {
  saving.value = true
  try {
    if (isEditing.value) {
      const res = await put(`/personas/${formPersona.id}`, formPersona)
      if (res.success) {
        toast.success('Datos de la persona actualizados exitosamente.')
        showCreateModal.value = false
        await fetchPersonas()
      }
    } else {
      const res = await post('/personas', formPersona)
      if (res.success) {
        toast.success('Persona registrada exitosamente en el padrón.')
        showCreateModal.value = false
        await fetchPersonas()
      }
    }
  } catch (err) {
    toast.error(err)
  } finally {
    saving.value = false
  }
}

const confirmDeletePersona = async (p) => {
  const confirmed = await confirmDialog({
    title: 'Eliminar Persona del Padrón',
    message: `¿Está seguro de eliminar a ${p.apellido}, ${p.nombre} (${identificacion(p)}) del padrón provincial?`,
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/personas/${p.id}`)
      if (res.success) {
        toast.success('Persona eliminada del padrón con éxito.')
        await fetchPersonas()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

// -------------------------------------------------------------
// FICHA Y TRAYECTORIA
// -------------------------------------------------------------
const openHistoryModal = async (personaId) => {
  try {
    const res = await get(`/personas/${personaId}`)
    if (res.success) {
      selectedPersona.value = res.data
    }
  } catch (err) {
    console.error('Error loading persona history:', err)
    toast.error(err, { title: 'Error al cargar ficha' })
  }
}

const finalizarVigenciaDesdeFicha = async (asig) => {
  const confirmed = await confirmDialog({
    title: 'Finalizar Vigencia del Cargo',
    message: `¿Desea registrar el cese o baja del cargo "${asig.tipo_rol?.nombre}" en ${asig.area_ti?.nombre}?`,
    confirmText: 'Sí, finalizar',
    cancelText: 'Cancelar',
    type: 'warning',
  })

  if (confirmed) {
    try {
      const today = new Date().toISOString().split('T')[0]
      const res = await post(`/asignaciones-rol/${asig.id}/finalizar-vigencia`, {
        fecha_fin: today,
        motivo: 'Baja formal registrada desde la ficha de la persona',
      })
      if (res.success) {
        toast.success('Vigencia finalizada correctamente.')
        await fetchPersonas()
        if (selectedPersona.value) {
          await openHistoryModal(selectedPersona.value.id)
        }
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

// -------------------------------------------------------------
// ASIGNACIÓN DE CARGOS / ROLES
// -------------------------------------------------------------
const openNewAsignacionModalDirect = async () => {
  await fetchAuxiliares()
  Object.assign(formAsig, {
    persona_id: '',
    area_ti_id: areasList.value[0]?.id || '',
    tipo_rol_id: tiposRol.value[0]?.id || '',
    fecha_inicio: new Date().toISOString().split('T')[0],
    acto_administrativo: '',
    es_maxima_autoridad: false,
    observaciones: '',
  })
  showModalAsignar.value = true
}

const openAsignarModalParaPersona = async (p) => {
  await fetchAuxiliares()
  Object.assign(formAsig, {
    persona_id: p.id,
    area_ti_id: areasList.value[0]?.id || '',
    tipo_rol_id: tiposRol.value[0]?.id || '',
    fecha_inicio: new Date().toISOString().split('T')[0],
    acto_administrativo: '',
    es_maxima_autoridad: false,
    observaciones: '',
  })
  showModalAsignar.value = true
}

const saveAsignacion = async () => {
  if (!formAsig.persona_id) {
    toast.warning('Por favor seleccione un agente del padrón.')
    return
  }
  savingAsignacion.value = true
  try {
    const res = await post('/asignaciones-rol', formAsig)
    if (res.success) {
      toast.success('Designación y asignación de rol registrada exitosamente.')
      showModalAsignar.value = false
      await fetchPersonas()
      if (selectedPersona.value && selectedPersona.value.id === formAsig.persona_id) {
        await openHistoryModal(selectedPersona.value.id)
      }
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingAsignacion.value = false
  }
}

onMounted(() => {
  fetchPersonas()
  fetchCatalogos()
})
</script>
