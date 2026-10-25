<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Botón Volver y Breadcrumb -->
    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
      <NuxtLink to="/areas-ti" class="hover:text-sky-600">← Volver a Áreas TI</NuxtLink>
      <span>/</span>
      <span class="text-slate-800">{{ area?.codigo || 'Cargando...' }}</span>
    </div>

    <div v-if="loading" class="p-12 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
      Cargando expediente del Área TI...
    </div>

    <div v-else-if="area" class="space-y-6">
      <!-- Cabecera Institucional del Expediente -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="px-2.5 py-1 bg-sky-600 text-white font-extrabold rounded-lg text-xs tracking-wider">
                {{ area.ministerio?.sigla }}
              </span>
              <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                {{ area.codigo }}
              </span>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                :class="area.es_homologada ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ area.es_homologada ? `Área Homologada por ${agency.shortName}` : 'En Trámite de Homologación' }}
              </span>
            </div>

            <h1 class="text-2xl font-extrabold text-slate-900">{{ area.nombre }}</h1>
            <p class="text-xs text-slate-500 font-semibold">{{ area.ministerio?.nombre }} | Nivel: {{ area.nivel_jerarquico }}</p>
          </div>

          <!-- Acciones de Cabecera -->
          <div v-if="authStore.isEditor" class="flex flex-wrap gap-2">
            <button
              @click="openModalAsociarServicio"
              class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5"
            >
              <span>⚡+</span> Asociar Servicio Tecnológico
            </button>
            <button
              @click="openModalAsociar"
              class="px-3.5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition flex items-center gap-1.5"
            >
              <span>👤+</span> Asociar Agente
            </button>
            <button
              @click="openModalDoc"
              class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5"
            >
              <span>📄</span> Adjuntar Documento
            </button>
          </div>
        </div>

        <!-- Barra de Datos Rápidos -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-100 text-xs text-slate-600">
          <div>
            <span class="text-slate-400 block text-[10px] font-bold uppercase">Resolución {{ agency.shortName }}</span>
            <strong class="text-slate-800">{{ area.resolucion_homologacion || 'Sin resolución registrada' }}</strong>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] font-bold uppercase">Email Institucional</span>
            <strong class="text-slate-800">{{ area.email_institucional || 'No registrado' }}</strong>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] font-bold uppercase">Contacto Telefónico</span>
            <strong class="text-slate-800">{{ area.telefono_contacto || 'No registrado' }}</strong>
          </div>
        </div>
      </div>

      <!-- Ficha de la Máxima Autoridad TI -->
      <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-6 shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-amber-400/20 border border-amber-400/30 flex items-center justify-center text-2xl font-bold text-amber-300">
            ⭐
          </div>
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400">Máxima Autoridad de TI Designada</span>
            <div v-if="area.maxima_autoridad_vigente">
              <h2 class="text-lg font-bold text-white mt-0.5">
                {{ area.maxima_autoridad_vigente.persona?.apellido }}, {{ area.maxima_autoridad_vigente.persona?.nombre }}
              </h2>
              <p class="text-xs text-slate-300 font-medium">
                {{ area.maxima_autoridad_vigente.tipo_rol?.nombre }} (Desde {{ area.maxima_autoridad_vigente.fecha_inicio }})
              </p>
              <div class="flex items-center gap-3 text-[11px] text-slate-400 mt-1">
                <span>✉️ {{ area.maxima_autoridad_vigente.persona?.email_principal }}</span>
                <span>📞 {{ area.maxima_autoridad_vigente.persona?.telefono_principal }}</span>
              </div>
            </div>
            <div v-else class="text-xs text-slate-400 italic mt-1">
              Actualmente no se encuentra registrada una máxima autoridad formal en este organismo.
            </div>
          </div>
        </div>

        <button
          v-if="authStore.isEditor"
          @click="openModalAsociarAutoridad"
          class="px-4 py-2 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 rounded-xl text-xs font-bold transition flex items-center gap-1.5 self-start md:self-auto"
        >
          ⭐ Designar / Reasignar Autoridad
        </button>
      </div>

      <!-- Selector de Pestañas (Tabs) -->
      <div class="flex border-b border-slate-200 space-x-4">
        <button
          @click="activeTab = 'equipo'"
          class="pb-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5"
          :class="activeTab === 'equipo' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
        >
          <span>👥</span> Equipo y Roles Asignados ({{ area.asignaciones?.length || 0 }})
        </button>

        <button
          @click="activeTab = 'servicios'"
          class="pb-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5"
          :class="activeTab === 'servicios' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
        >
          <span>⚡</span> Servicios Tecnológicos Activos ({{ area.servicios?.length || 0 }})
        </button>

        <button
          @click="activeTab = 'documentos'"
          class="pb-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5"
          :class="activeTab === 'documentos' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
        >
          <span>📄</span> Bóveda Documental y Actas ({{ area.documentos?.length || 0 }})
        </button>
      </div>

      <!-- TAB 1: EQUIPO Y ASIGNACIONES -->
      <div v-if="activeTab === 'equipo'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs text-slate-500">
            Nómina histórica y vigente de agentes y autoridades asociadas a <strong>{{ area.nombre }}</strong>.
          </p>

          <button
            v-if="authStore.isEditor"
            @click="openModalAsociar"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-xs font-bold shadow-sm transition"
          >
            <span>+</span> Asociar Nuevo Agente
          </button>
        </div>

        <div v-if="!area.asignaciones || area.asignaciones.length === 0" class="p-8 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
          No hay agentes o autoridades asociadas a esta área TI.
        </div>

        <div v-else class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase">
                <th class="py-3 px-4">Agente</th>
                <th class="py-3 px-4">Rol / Cargo Formal</th>
                <th class="py-3 px-4">Vigencia Temporal</th>
                <th class="py-3 px-4">Acto Designación</th>
                <th class="py-3 px-4 text-center">Autoridad</th>
                <th class="py-3 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr
                v-for="asig in area.asignaciones"
                :key="asig.id"
                class="hover:bg-slate-50/80 transition"
                :class="asig.estado_vigencia === 'HISTORICO' ? 'opacity-65 bg-slate-50/40' : ''"
              >
                <td class="py-3.5 px-4 font-bold text-slate-900">
                  <div class="flex items-center gap-2">
                    <span
                      class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-[10px]"
                      :class="asig.estado_vigencia === 'VIGENTE' ? 'bg-sky-100 text-sky-800' : 'bg-slate-200 text-slate-600'"
                    >
                      {{ asig.persona?.apellido?.charAt(0) || 'P' }}
                    </span>
                    <div>
                      <span>{{ asig.persona?.apellido }}, {{ asig.persona?.nombre }}</span>
                      <span class="block text-[11px] text-slate-400 font-normal">CUIL: {{ asig.persona?.cuil_cuit }} | {{ asig.persona?.email_principal }}</span>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <span class="font-bold text-sky-800">{{ asig.tipo_rol?.nombre }}</span>
                  <span class="block text-[10px] text-slate-400 font-semibold">{{ asig.tipo_rol?.nivel_responsabilidad }}</span>
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase mr-1.5 inline-block"
                    :class="asig.estado_vigencia === 'VIGENTE' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ asig.estado_vigencia }}
                  </span>
                  <span class="text-[11px] text-slate-600 block mt-0.5 font-medium">
                    {{ asig.fecha_inicio }} {{ asig.fecha_fin ? `al ${asig.fecha_fin}` : '(Actual)' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-slate-600">
                  <span class="font-mono text-[11px] font-semibold">{{ asig.acto_administrativo || '-' }}</span>
                  <span v-if="asig.observaciones" class="block text-[10px] text-slate-400 italic truncate max-w-xs" :title="asig.observaciones">
                    {{ asig.observaciones }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span v-if="asig.es_maxima_autoridad" class="text-amber-500 font-bold text-base" title="Máxima Autoridad TI">⭐</span>
                  <span v-else class="text-slate-300">-</span>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      v-if="authStore.isEditor"
                      @click="openModalEditAsignacion(asig)"
                      class="p-1.5 text-slate-500 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition"
                      title="Editar Asignación"
                    >
                      ✏️
                    </button>

                    <!-- Botón de Desasociar / Finalizar Vigencia -->
                    <button
                      v-if="authStore.isEditor && asig.estado_vigencia === 'VIGENTE'"
                      @click="openModalFinalizar(asig)"
                      class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg text-[11px] font-bold transition flex items-center gap-1"
                      title="Finalizar vigencia del agente en esta área"
                    >
                      🛑 Desasociar
                    </button>

                    <!-- Eliminar asignación errónea -->
                    <button
                      v-if="authStore.isAdmin"
                      @click="handleDeleteAsignacion(asig)"
                      class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                      title="Eliminar registro"
                    >
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: SERVICIOS TECNOLÓGICOS PROVISTOS -->
      <div v-if="activeTab === 'servicios'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs text-slate-500">
            Servicios tecnológicos provinciales provistos y administrados para <strong>{{ area.nombre }}</strong>.
          </p>

          <button
            v-if="authStore.isEditor"
            @click="openModalAsociarServicio"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow-sm transition"
          >
            <span>+</span> Asociar Nuevo Servicio
          </button>
        </div>

        <div v-if="!area.servicios || area.servicios.length === 0" class="p-8 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
          No hay servicios tecnológicos provistos a esta área TI.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="srv in area.servicios"
            :key="srv.id"
            class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-3 flex flex-col justify-between"
          >
            <div class="space-y-3">
              <div class="flex items-start justify-between gap-2">
                <span
                  class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wide"
                  :class="{
                    'bg-emerald-100 text-emerald-800': srv.estado_servicio === 'ACTIVO',
                    'bg-amber-100 text-amber-800': srv.estado_servicio === 'EN_PROCESO' || srv.estado_servicio === 'SOLICITADO',
                    'bg-rose-100 text-rose-800': srv.estado_servicio === 'SUSPENDIDO',
                    'bg-slate-100 text-slate-700': srv.estado_servicio === 'DADO_DE_BAJA'
                  }"
                >
                  {{ srv.estado_servicio }}
                </span>

                <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                  {{ srv.codigo_instancia }}
                </span>
              </div>

              <div>
                <h3 class="text-base font-bold text-slate-900">{{ srv.catalogo_servicio?.nombre }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">SLA: <strong>{{ srv.sla_nivel || 'Estándar' }}</strong> | Alta: {{ srv.fecha_alta }}</p>
                <p v-if="srv.observaciones" class="text-xs text-slate-600 mt-1 italic">{{ srv.observaciones }}</p>
              </div>

              <!-- Referentes Asignados con Opción de Quitar Directamente -->
              <div class="text-xs pt-1">
                <div class="flex items-center justify-between mb-1.5">
                  <p class="text-[10px] font-bold uppercase text-slate-400">Referentes Técnicos / Administrativos</p>
                  <button
                    v-if="authStore.isEditor"
                    @click="openModalReferente(srv)"
                    class="text-[10px] font-bold text-sky-600 hover:text-sky-800"
                  >
                    + Asignar Referente
                  </button>
                </div>

                <div v-if="!srv.referentes || srv.referentes.length === 0" class="text-slate-400 italic text-[11px]">
                  Sin referentes específicos designados.
                </div>
                <div v-else class="flex flex-wrap gap-1.5">
                  <span
                    v-for="ref in srv.referentes"
                    :key="ref.id"
                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-[11px] text-slate-700 group/ref"
                  >
                    <span><strong>{{ ref.persona?.apellido }}, {{ ref.persona?.nombre }}</strong> ({{ ref.tipo_referente }})</span>
                    <button
                      v-if="authStore.isEditor"
                      @click="handleRemoveReferente(srv, ref)"
                      class="ml-1 text-slate-400 hover:text-rose-600 font-bold p-0.5 hover:bg-rose-50 rounded transition"
                      title="Desvincular y quitar referente"
                    >
                      ✕
                    </button>
                  </span>
                </div>
              </div>
            </div>

            <!-- Acciones del Servicio -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5">
                <button
                  v-if="authStore.isEditor"
                  @click="openModalEditServicio(srv)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[11px] transition"
                >
                  ✏️ Editar Datos
                </button>
                <button
                  v-if="authStore.isEditor"
                  @click="openModalReferente(srv)"
                  class="px-2.5 py-1 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-lg font-bold text-[11px] transition"
                >
                  👤 Gestionar Referentes
                </button>
              </div>

              <button
                v-if="authStore.isAdmin"
                @click="handleDeleteServicioArea(srv)"
                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                title="Desasociar y eliminar servicio del área"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: DOCUMENTACIÓN DIGITAL -->
      <div v-if="activeTab === 'documentos'" class="space-y-4">
        <div v-if="!area.documentos || area.documentos.length === 0" class="p-8 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
          No hay documentos digitales adjuntos a esta área.
        </div>

        <div v-else class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase">
                <th class="py-3 px-4">Documento</th>
                <th class="py-3 px-4">Tipo</th>
                <th class="py-3 px-4">Tamaño</th>
                <th class="py-3 px-4">Integridad SHA-256</th>
                <th class="py-3 px-4 text-right">Descarga</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="doc in area.documentos" :key="doc.id" class="hover:bg-slate-50/80">
                <td class="py-3.5 px-4 font-bold text-slate-900">
                  {{ doc.titulo }}
                  <span class="block text-[11px] text-slate-400 font-normal">{{ doc.archivo_nombre_original }}</span>
                </td>
                <td class="py-3.5 px-4 font-semibold text-slate-700">
                  {{ doc.tipo_documento }}
                </td>
                <td class="py-3.5 px-4 text-slate-500">
                  {{ doc.tamanio_formateado }}
                </td>
                <td class="py-3.5 px-4 font-mono text-[10px] text-slate-400 truncate max-w-xs" :title="doc.sha256">
                  {{ doc.sha256 || '-' }}
                </td>
                <td class="py-3.5 px-4 text-right">
                  <a
                    :href="`${config.public.apiBase}/documentos/${doc.id}/descargar`"
                    target="_blank"
                    class="px-3 py-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg font-bold text-xs inline-block transition"
                  >
                    ⬇ Descargar
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: ASOCIAR / EDITAR SERVICIO TECNOLÓGICO AL ÁREA                  -->
    <!-- ===================================================================== -->
    <div v-if="showModalAsociarServicio" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalAsociarServicio = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-emerald-950 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">
              {{ isEditingServicioArea ? 'Editar Servicio Tecnológico' : 'Asociar Servicio Tecnológico al Área' }}
            </h2>
            <p class="text-xs text-emerald-300">{{ area?.nombre }}</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="showModalAsociarServicio = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveServicioAsociacion" class="p-6 space-y-4 text-xs">
          <!-- Selector de Tipo de Servicio -->
          <div>
            <label for="area-formservicioarea-catalogo-servicio-id" class="block font-bold text-slate-700 mb-1">Tipo de Servicio del Catálogo *</label>
            <select id="area-formservicioarea-catalogo-servicio-id"
              v-model="formServicioArea.catalogo_servicio_id"
              @change="onTipoServicioChange"
              :disabled="isEditingServicioArea"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-60"
            >
              <option value="" disabled>Seleccione Servicio...</option>
              <option v-for="c in catalogoServicios.filter(x => x.activo)" :key="c.id" :value="c.id">
                {{ c.nombre }} ({{ c.codigo }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="area-formservicioarea-codigo-instancia" class="block font-bold text-slate-700 mb-1">Código Único de Instancia *</label>
              <input id="area-formservicioarea-codigo-instancia"
                v-model="formServicioArea.codigo_instancia"
                type="text"
                required
                placeholder="Ej: CLOUD-MS-01"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label for="area-formservicioarea-sla-nivel" class="block font-bold text-slate-700 mb-1">Nivel de SLA *</label>
              <select id="area-formservicioarea-sla-nivel"
                v-model="formServicioArea.sla_nivel"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="99.9% Disponibilidad 24x7">Platinum (99.9% 24x7)</option>
                <option value="99.5% Disponibilidad Lun-Vie">Gold (99.5% 12x5)</option>
                <option value="99.0% Horario Hábil">Silver (99.0% Horario Hábil)</option>
                <option value="Mejor Esfuerzo">Estándar (Mejor Esfuerzo)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="area-formservicioarea-estado-servicio" class="block font-bold text-slate-700 mb-1">Estado de Servicio *</label>
              <select id="area-formservicioarea-estado-servicio"
                v-model="formServicioArea.estado_servicio"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="ACTIVO">Activo</option>
                <option value="EN_PROCESO">En Proceso / Configuración</option>
                <option value="SOLICITADO">Solicitado</option>
                <option value="SUSPENDIDO">Suspendido</option>
              </select>
            </div>
            <div>
              <label for="area-formservicioarea-fecha-alta" class="block font-bold text-slate-700 mb-1">Fecha de Alta *</label>
              <input id="area-formservicioarea-fecha-alta"
                v-model="formServicioArea.fecha_alta"
                type="date"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>

          <div>
            <label for="area-formservicioarea-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones Técnicas</label>
            <textarea id="area-formservicioarea-observaciones"
              v-model="formServicioArea.observaciones"
              rows="3"
              placeholder="Detalles sobre alcance, subredes o condiciones de provisión del servicio..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalAsociarServicio = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingServicioArea"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold shadow-md shadow-emerald-600/20 disabled:opacity-50"
            >
              {{ savingServicioArea ? 'Guardando...' : (isEditingServicioArea ? 'Guardar Cambios' : 'Asociar Servicio al Área') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: ASIGNAR Y GESTIONAR REFERENTES DEL SERVICIO                    -->
    <!-- ===================================================================== -->
    <div v-if="showModalReferente" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalReferente = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-sky-950 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">Referentes del Servicio Tecnológico</h2>
            <p class="text-xs text-sky-300">{{ selectedServicio?.codigo_instancia }} - {{ selectedServicio?.catalogo_servicio?.nombre }}</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="showModalReferente = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <div class="p-6 space-y-5 text-xs">
          <!-- Nómina Actual de Referentes con botón de Quitar -->
          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-2">Referentes Asignados Actualmente</label>
            <div v-if="!selectedServicio?.referentes || selectedServicio?.referentes.length === 0" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-400 italic text-center">
              No hay referentes designados para esta instancia de servicio.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="r in selectedServicio?.referentes"
                :key="r.id"
                class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl"
              >
                <div>
                  <p class="font-bold text-slate-800">{{ r.persona?.apellido }}, {{ r.persona?.nombre }}</p>
                  <p class="text-[11px] text-slate-500">{{ r.tipo_referente }} | CUIL: {{ r.persona?.cuil_cuit }}</p>
                </div>
                <button
                  type="button"
                  @click="handleRemoveReferente(selectedServicio, r)"
                  class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-bold transition flex items-center gap-1"
                >
                  <span>🗑️</span> Quitar
                </button>
              </div>
            </div>
          </div>

          <!-- Formulario para Agregar Nuevo Referente -->
          <form @submit.prevent="saveReferenteServicio" class="pt-4 border-t border-slate-100 space-y-3">
            <label class="block font-bold text-slate-800 uppercase tracking-wider text-[10px]">+ Asignar Nuevo Referente</label>

            <div>
              <label for="area-formreferente-persona-id" class="block font-bold text-slate-700 mb-1">Persona / Agente *</label>
              <PersonaSearchSelect input-id="area-formreferente-persona-id"
                v-model="formReferente.persona_id"
                placeholder="Buscar agente por apellido, nombre o CUIL..."
              />
            </div>

            <div>
              <label for="area-formreferente-tipo-referente" class="block font-bold text-slate-700 mb-1">Tipo de Responsabilidad / Referente *</label>
              <select id="area-formreferente-tipo-referente"
                v-model="formReferente.tipo_referente"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              >
                <option value="TECNICO_PRINCIPAL">Referente Técnico Principal</option>
                <option value="TECNICO_ALTERNO">Referente Técnico Alterno</option>
                <option value="ADMINISTRATIVO">Referente Administrativo / Gestor</option>
                <option value="SEGURIDAD">Referente de Ciberseguridad y Enlace</option>
              </select>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
              <label class="flex items-center gap-2 cursor-pointer font-bold text-slate-800">
                <input
                  type="checkbox"
                  v-model="formReferente.es_principal"
                  class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
                />
                Designar como Contacto Técnico Primario
              </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="showModalReferente = false"
                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
              >
                Cerrar
              </button>
              <button
                type="submit"
                :disabled="savingReferente"
                class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
              >
                {{ savingReferente ? 'Guardando...' : 'Asignar Referente' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: ASOCIAR AGENTE / ASIGNAR ROL                                   -->
    <!-- ===================================================================== -->
    <div v-if="showModalAsociar" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalAsociar = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">
              {{ isEditingAsignacion ? 'Editar Asignación de Rol' : 'Asociar Agente al Área TI' }}
            </h2>
            <p class="text-xs text-slate-400">{{ area?.nombre }}</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="showModalAsociar = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveAsignacion" class="p-6 space-y-4 text-xs">
          <div>
            <label for="area-formasignacion-persona-id" class="block font-bold text-slate-700 mb-1">Agente / Persona del Padrón *</label>
            <PersonaSearchSelect input-id="area-formasignacion-persona-id"
              v-model="formAsignacion.persona_id"
              :disabled="isEditingAsignacion"
              placeholder="Buscar agente por apellido, nombre o CUIL..."
            />
          </div>

          <div>
            <label for="area-formasignacion-tipo-rol-id" class="block font-bold text-slate-700 mb-1">Rol / Función Asignada *</label>
            <select id="area-formasignacion-tipo-rol-id"
              v-model="formAsignacion.tipo_rol_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              <option value="" disabled>Seleccione Rol...</option>
              <option v-for="r in tiposRol" :key="r.id" :value="r.id">
                {{ r.nombre }} ({{ r.nivel_responsabilidad }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="area-formasignacion-fecha-inicio" class="block font-bold text-slate-700 mb-1">Fecha de Inicio *</label>
              <input id="area-formasignacion-fecha-inicio"
                v-model="formAsignacion.fecha_inicio"
                type="date"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
            <div>
              <label for="area-formasignacion-fecha-fin" class="block font-bold text-slate-700 mb-1">Fecha de Fin (Opcional)</label>
              <input id="area-formasignacion-fecha-fin"
                v-model="formAsignacion.fecha_fin"
                type="date"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          <div>
            <label for="area-formasignacion-acto-administrativo" class="block font-bold text-slate-700 mb-1">Acto Administrativo / Norma de Designación</label>
            <input id="area-formasignacion-acto-administrativo"
              v-model="formAsignacion.acto_administrativo"
              type="text"
              placeholder="Ej: Decreto 1250/2024, Resolución 45/24"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>

          <div>
            <label for="area-formasignacion-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones</label>
            <textarea id="area-formasignacion-observaciones"
              v-model="formAsignacion.observaciones"
              rows="2"
              placeholder="Detalles sobre funciones, pases o responsabilidades..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            ></textarea>
          </div>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="formAsignacion.es_maxima_autoridad"
                class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-500"
              />
              <span class="font-bold text-slate-800">⭐ Designar como Máxima Autoridad del Área TI</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalAsociar = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingAsignacion"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
            >
              {{ savingAsignacion ? 'Guardando...' : (isEditingAsignacion ? 'Actualizar Asignación' : 'Asociar Agente') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: DESASOCIAR / FINALIZAR VIGENCIA DE AGENTE                      -->
    <!-- ===================================================================== -->
    <div v-if="showModalFinalizar" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalFinalizar = false)" class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-amber-950 text-white flex items-center justify-between">
          <div>
            <span class="text-[10px] font-bold uppercase text-amber-400">Desasociación / Cese de Cargo</span>
            <h2 class="text-base font-bold">Finalizar Vigencia de Agente</h2>
          </div>
          <button type="button" aria-label="Cerrar" @click="showModalFinalizar = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitFinalizarVigencia" class="p-6 space-y-4 text-xs">
          <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-slate-800">
            <p class="font-bold">{{ selectedAsignacion?.persona?.apellido }}, {{ selectedAsignacion?.persona?.nombre }}</p>
            <p class="text-[11px] text-amber-900">{{ selectedAsignacion?.tipo_rol?.nombre }} (Desde {{ selectedAsignacion?.fecha_inicio }})</p>
          </div>

          <div>
            <label for="area-formfinalizar-fecha-fin" class="block font-bold text-slate-700 mb-1">Fecha de Finalización / Cese *</label>
            <input id="area-formfinalizar-fecha-fin"
              v-model="formFinalizar.fecha_fin"
              type="date"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>

          <div>
            <label for="area-formfinalizar-motivo" class="block font-bold text-slate-700 mb-1">Motivo del Cese / Acto de Baja</label>
            <textarea id="area-formfinalizar-motivo"
              v-model="formFinalizar.motivo"
              rows="2"
              placeholder="Ej: Pase a Ministerio de Economía, Renuncia formal, Conclusión de mandato..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalFinalizar = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingFinalizar"
              class="px-5 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl font-bold shadow-md shadow-amber-600/20 disabled:opacity-50"
            >
              {{ savingFinalizar ? 'Finalizando...' : 'Confirmar Desasociación' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: ADJUNTAR DOCUMENTO DIGITAL                                     -->
    <!-- ===================================================================== -->
    <div v-if="showModalDoc" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalDoc = false)" class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <h2 class="text-base font-bold">Adjuntar Documento Digital</h2>
          <button type="button" aria-label="Cerrar" @click="showModalDoc = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="uploadDoc" class="p-6 space-y-4 text-xs">
          <div>
            <label for="area-formdoc-titulo" class="block font-bold text-slate-700 mb-1">Título del Documento *</label>
            <input id="area-formdoc-titulo"
              v-model="formDoc.titulo"
              type="text"
              required
              placeholder="Ej: Resolución de Homologación 2024"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>

          <div>
            <label for="area-formdoc-tipo-documento" class="block font-bold text-slate-700 mb-1">Tipo de Documento *</label>
            <select id="area-formdoc-tipo-documento"
              v-model="formDoc.tipo_documento"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              <option value="RESOLUCION_HOMOLOGACION">Resolución de Homologación</option>
              <option value="ACTA_ACUERDO">Acta de Acuerdo Técnico</option>
              <option value="ANEXO_TECNICO">Anexo Técnico de Infraestructura</option>
              <option value="CONVENIO">Convenio Interinstitucional</option>
            </select>
          </div>

          <div>
            <label for="area-campo" class="block font-bold text-slate-700 mb-1">Archivo Digital (PDF, DOCX hasta 20MB) *</label>
            <input id="area-campo"
              type="file"
              required
              @change="handleFileChange"
              class="w-full p-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none"
            />
          </div>

          <div>
            <label for="area-formdoc-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones</label>
            <textarea id="area-formdoc-observaciones"
              v-model="formDoc.observaciones"
              rows="2"
              placeholder="Notas aclaratorias sobre el documento..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalDoc = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="uploading"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
            >
              {{ uploading ? 'Subiendo y calculando Hash...' : 'Subir Archivo' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useAgency } from '~/composables/useAgency'
import { useToast } from '~/composables/useToast'

const route = useRoute()
const { get, post, put, delete: del } = useApi()
const authStore = useAuthStore()
const agency = useAgency()
const config = useRuntimeConfig()
const { toast, confirmDialog } = useToast()

const area = ref(null)
const loading = ref(true)
const activeTab = ref('equipo')

const personas = ref([])
const tiposRol = ref([])
const catalogoServicios = ref([])

// Modales de Servicios Tecnológicos
const showModalAsociarServicio = ref(false)
const isEditingServicioArea = ref(false)
const savingServicioArea = ref(false)

const formServicioArea = reactive({
  id: null,
  area_ti_id: null,
  catalogo_servicio_id: '',
  codigo_instancia: '',
  estado_servicio: 'ACTIVO',
  sla_nivel: '99.9% Disponibilidad 24x7',
  fecha_alta: new Date().toISOString().split('T')[0],
  observaciones: '',
})

// Modal Referente de Servicio
const showModalReferente = ref(false)
const selectedServicio = ref(null)
const savingReferente = ref(false)
const formReferente = reactive({
  persona_id: '',
  tipo_referente: 'TECNICO_PRINCIPAL',
  es_principal: true,
})

// Modales de Asignación / Desasociación de Agentes
const showModalAsociar = ref(false)
const isEditingAsignacion = ref(false)
const savingAsignacion = ref(false)

const formAsignacion = reactive({
  id: null,
  persona_id: '',
  area_ti_id: null,
  tipo_rol_id: '',
  fecha_inicio: new Date().toISOString().split('T')[0],
  fecha_fin: '',
  es_maxima_autoridad: false,
  acto_administrativo: '',
  observaciones: '',
})

const showModalFinalizar = ref(false)
const selectedAsignacion = ref(null)
const savingFinalizar = ref(false)
const formFinalizar = reactive({
  fecha_fin: new Date().toISOString().split('T')[0],
  motivo: '',
})

// Documentos
const showModalDoc = ref(false)
const uploading = ref(false)
const selectedFile = ref(null)

const formDoc = reactive({
  titulo: '',
  tipo_documento: 'RESOLUCION_HOMOLOGACION',
  observaciones: '',
})

const handleFileChange = (e) => {
  selectedFile.value = e.target.files[0] || null
}

const fetchCatalogos = async () => {
  try {
    const [resPer, resRol, resCat] = await Promise.all([
      get('/personas', { per_page: 200 }),
      get('/tipos-rol'),
      get('/catalogo-servicios'),
    ])
    if (resPer.success) personas.value = resPer.data?.data || []
    if (resRol.success) tiposRol.value = resRol.data || []
    if (resCat.success) catalogoServicios.value = resCat.data || []
  } catch (err) {
    console.error('Error fetching catalogos:', err)
  }
}

const fetchArea = async () => {
  loading.value = true
  try {
    const res = await get(`/areas-ti/${route.params.id}`)
    if (res.success) {
      area.value = res.data
    }
  } catch (err) {
    console.error('Error fetching area:', err)
    toast.error(err, { title: 'Error al cargar expediente' })
  } finally {
    loading.value = false
  }
}

// -------------------------------------------------------------
// SERVICIOS TECNOLÓGICOS: ASOCIACIÓN Y EDICIÓN
// -------------------------------------------------------------
const openModalAsociarServicio = () => {
  isEditingServicioArea.value = false
  const primerCat = catalogoServicios.value[0]
  Object.assign(formServicioArea, {
    id: null,
    area_ti_id: area.value?.id,
    catalogo_servicio_id: primerCat?.id || '',
    codigo_instancia: '',
    estado_servicio: 'ACTIVO',
    sla_nivel: '99.9% Disponibilidad 24x7',
    fecha_alta: new Date().toISOString().split('T')[0],
    observaciones: '',
  })
  onTipoServicioChange()
  showModalAsociarServicio.value = true
}

const onTipoServicioChange = () => {
  const selected = catalogoServicios.value.find(c => c.id === formServicioArea.catalogo_servicio_id)
  if (selected && (!formServicioArea.codigo_instancia || !isEditingServicioArea.value)) {
    const areaCodigo = area.value?.codigo?.replace('TI-', '') || 'AREA'
    const srvCodigo = selected.codigo?.replace('SRV_', '').replace('SRV-', '') || 'SRV'
    formServicioArea.codigo_instancia = `${srvCodigo}-${areaCodigo}-01`
  }
}

const openModalEditServicio = (srv) => {
  isEditingServicioArea.value = true
  Object.assign(formServicioArea, {
    id: srv.id,
    area_ti_id: area.value?.id,
    catalogo_servicio_id: srv.catalogo_servicio_id,
    codigo_instancia: srv.codigo_instancia,
    estado_servicio: srv.estado_servicio,
    sla_nivel: srv.sla_nivel || '99.9% Disponibilidad 24x7',
    fecha_alta: srv.fecha_alta,
    observaciones: srv.observaciones || '',
  })
  showModalAsociarServicio.value = true
}

const saveServicioAsociacion = async () => {
  savingServicioArea.value = true
  const payload = {
    ...formServicioArea,
    area_ti_id: area.value?.id,
  }

  try {
    if (isEditingServicioArea.value) {
      const res = await put(`/servicios-areas/${formServicioArea.id}`, payload)
      if (res.success) {
        toast.success('Servicio tecnológico actualizado correctamente.')
        showModalAsociarServicio.value = false
        await fetchArea()
      }
    } else {
      const res = await post('/servicios-areas', payload)
      if (res.success) {
        toast.success('Servicio tecnológico asociado y registrado exitosamente.')
        showModalAsociarServicio.value = false
        await fetchArea()
      }
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingServicioArea.value = false
  }
}

const handleDeleteServicioArea = async (srv) => {
  const confirmed = await confirmDialog({
    title: 'Desasociar Servicio Tecnológico',
    message: `¿Está seguro de desasociar y eliminar la instancia "${srv.codigo_instancia}" (${srv.catalogo_servicio?.nombre}) de esta área TI?`,
    confirmText: 'Sí, desasociar',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/servicios-areas/${srv.id}`)
      if (res.success) {
        toast.success('Servicio desasociado del área.')
        await fetchArea()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

// -------------------------------------------------------------
// GESTIÓN DE REFERENTES DE SERVICIO (ASIGNAR / QUITAR)
// -------------------------------------------------------------
const openModalReferente = (srv) => {
  selectedServicio.value = srv
  formReferente.persona_id = ''
  formReferente.tipo_referente = 'TECNICO_PRINCIPAL'
  formReferente.es_principal = true
  showModalReferente.value = true
}

const saveReferenteServicio = async () => {
  if (!selectedServicio.value) return
  if (!formReferente.persona_id) {
    toast.warning('Por favor seleccione un agente del padrón.')
    return
  }
  savingReferente.value = true
  try {
    const res = await post(`/servicios-areas/${selectedServicio.value.id}/referentes`, formReferente)
    if (res.success) {
      toast.success('Referente asignado al servicio tecnológico.')
      await fetchArea()
      // Actualizar la instancia seleccionada con los nuevos referentes
      const updatedSrv = area.value?.servicios?.find(s => s.id === selectedServicio.value.id)
      if (updatedSrv) selectedServicio.value = updatedSrv
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingReferente.value = false
  }
}

const handleRemoveReferente = async (srv, referente) => {
  const personaNombre = referente.persona ? `${referente.persona.apellido}, ${referente.persona.nombre}` : 'este referente'
  
  const confirmed = await confirmDialog({
    title: 'Quitar Referente del Servicio',
    message: `¿Está seguro de desvincular a ${personaNombre} (${referente.tipo_referente}) de la instancia "${srv.codigo_instancia}"?`,
    confirmText: 'Sí, desvincular',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/servicios-areas/${srv.id}/referentes/${referente.id}`)
      if (res.success) {
        toast.success('Referente desvinculado del servicio.')
        await fetchArea()
        const updatedSrv = area.value?.servicios?.find(s => s.id === srv.id)
        if (updatedSrv) selectedServicio.value = updatedSrv
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

// -------------------------------------------------------------
// ASIGNACIÓN Y DESASOCIACIÓN DE AGENTES
// -------------------------------------------------------------
const openModalAsociar = () => {
  isEditingAsignacion.value = false
  Object.assign(formAsignacion, {
    id: null,
    persona_id: '',
    area_ti_id: area.value?.id,
    tipo_rol_id: tiposRol.value[0]?.id || '',
    fecha_inicio: new Date().toISOString().split('T')[0],
    fecha_fin: '',
    es_maxima_autoridad: false,
    acto_administrativo: '',
    observaciones: '',
  })
  showModalAsociar.value = true
}

const openModalAsociarAutoridad = () => {
  isEditingAsignacion.value = false
  Object.assign(formAsignacion, {
    id: null,
    persona_id: area.value?.maxima_autoridad_vigente?.persona_id || '',
    area_ti_id: area.value?.id,
    tipo_rol_id: area.value?.maxima_autoridad_vigente?.tipo_rol_id || tiposRol.value[0]?.id || '',
    fecha_inicio: new Date().toISOString().split('T')[0],
    fecha_fin: '',
    es_maxima_autoridad: true,
    acto_administrativo: '',
    observaciones: 'Designación de Máxima Autoridad TI',
  })
  showModalAsociar.value = true
}

const openModalEditAsignacion = (asig) => {
  isEditingAsignacion.value = true
  Object.assign(formAsignacion, {
    id: asig.id,
    persona_id: asig.persona_id,
    area_ti_id: asig.area_ti_id,
    tipo_rol_id: asig.tipo_rol_id,
    fecha_inicio: asig.fecha_inicio,
    fecha_fin: asig.fecha_fin || '',
    es_maxima_autoridad: asig.es_maxima_autoridad,
    acto_administrativo: asig.acto_administrativo || '',
    observaciones: asig.observaciones || '',
  })
  showModalAsociar.value = true
}

const saveAsignacion = async () => {
  if (!formAsignacion.persona_id) {
    toast.warning('Por favor seleccione un agente del padrón.')
    return
  }
  savingAsignacion.value = true
  formAsignacion.area_ti_id = area.value?.id

  try {
    if (isEditingAsignacion.value) {
      const res = await put(`/asignaciones-rol/${formAsignacion.id}`, formAsignacion)
      if (res.success) {
        toast.success('Asignación de rol actualizada correctamente.')
        showModalAsociar.value = false
        await fetchArea()
      }
    } else {
      const res = await post('/asignaciones-rol', formAsignacion)
      if (res.success) {
        toast.success('Agente asociado y rol asignado exitosamente.')
        showModalAsociar.value = false
        await fetchArea()
      }
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingAsignacion.value = false
  }
}

const openModalFinalizar = (asig) => {
  selectedAsignacion.value = asig
  formFinalizar.fecha_fin = new Date().toISOString().split('T')[0]
  formFinalizar.motivo = ''
  showModalFinalizar.value = true
}

const submitFinalizarVigencia = async () => {
  if (!selectedAsignacion.value) return
  savingFinalizar.value = true
  try {
    const res = await post(`/asignaciones-rol/${selectedAsignacion.value.id}/finalizar-vigencia`, formFinalizar)
    if (res.success) {
      toast.success(`Vigencia de ${selectedAsignacion.value.persona?.apellido} finalizada correctamente.`)
      showModalFinalizar.value = false
      await fetchArea()
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingFinalizar.value = false
  }
}

const handleDeleteAsignacion = async (asig) => {
  const confirmed = await confirmDialog({
    title: 'Eliminar Registro de Asignación',
    message: `¿Está seguro de eliminar el registro del agente ${asig.persona?.apellido}, ${asig.persona?.nombre} como ${asig.tipo_rol?.nombre}? Esta acción solo debe realizarse si la asignación fue cargada por error.`,
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/asignaciones-rol/${asig.id}`)
      if (res.success) {
        toast.success('Registro de asignación eliminado.')
        await fetchArea()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

// -------------------------------------------------------------
// DOCUMENTOS DIGITALES
// -------------------------------------------------------------
const openModalDoc = () => {
  formDoc.titulo = ''
  formDoc.tipo_documento = 'RESOLUCION_HOMOLOGACION'
  formDoc.observaciones = ''
  selectedFile.value = null
  showModalDoc.value = true
}

const uploadDoc = async () => {
  if (!selectedFile.value || !area.value) {
    toast.warning('Por favor seleccione un archivo para subir.')
    return
  }
  uploading.value = true

  const formData = new FormData()
  formData.append('documentable_type', 'area_ti')
  formData.append('documentable_id', area.value.id.toString())
  formData.append('tipo_documento', formDoc.tipo_documento)
  formData.append('titulo', formDoc.titulo)
  formData.append('archivo', selectedFile.value)
  if (formDoc.observaciones) {
    formData.append('observaciones', formDoc.observaciones)
  }

  try {
    const res = await $fetch(`${config.public.apiBase}/documentos/upload`, {
      method: 'POST',
      body: formData,
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: 'application/json',
      }
    })

    if (res.success) {
      toast.success('Documento digital adjuntado e indexado correctamente.')
      showModalDoc.value = false
      await fetchArea()
    }
  } catch (err) {
    toast.error(err)
  } finally {
    uploading.value = false
  }
}

onMounted(() => {
  fetchArea()
  fetchCatalogos()
})
</script>
