<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado Principal y Botón de Creación -->
    <div
      class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
    >
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
          Gestión de Áreas TI - Homologación
        </h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Padrón de direcciones y dependencias de tecnología por ministerio,
          homologaciones {{ agency.shortName }} y máximas autoridades.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="authStore.isEditor"
          @click="openModalCreate"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-700 hover:bg-primary-600 text-white rounded-xl text-sm font-bold shadow-md shadow-primary-700/20 transition"
        >
          <span>+</span> Nueva Área TI
        </button>
      </div>
    </div>

    <!-- Indicadores Rápidos (KPIs de Áreas TI) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div
        class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between"
      >
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
            Total de Áreas TI
          </p>
          <h3 class="text-2xl font-extrabold text-slate-800 mt-0.5">
            {{ stats.total }}
          </h3>
        </div>
        <div
          class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-base"
        >
          🏢
        </div>
      </div>

      <div
        class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between"
      >
        <div>
          <p
            class="text-xs font-bold uppercase tracking-wider text-emerald-700"
          >
            Homologadas por {{ agency.shortName }}
          </p>
          <h3 class="text-2xl font-extrabold text-emerald-700 mt-0.5">
            {{ stats.homologadas }}
          </h3>
        </div>
        <div
          class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-base"
        >
          ✓
        </div>
      </div>

      <div
        class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between"
      >
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-amber-700">
            En Proceso de Homologación
          </p>
          <h3 class="text-2xl font-extrabold text-amber-700 mt-0.5">
            {{ stats.en_tramite }}
          </h3>
        </div>
        <div
          class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-base"
        >
          ⏳
        </div>
      </div>
    </div>

    <!-- Barra de Filtros Avanzados -->
    <div
      class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4"
    >
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Buscador de texto -->
        <div class="lg:col-span-1">
          <label
            for="areas-filters-q"
            class="block text-sm font-bold text-slate-500 mb-1"
            >Buscar</label
          >
          <input
            id="areas-filters-q"
            v-model="filters.q"
            @input="debounceSearch"
            type="text"
            placeholder="Nombre, código o resolución..."
            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:bg-white transition"
          />
        </div>

        <!-- Filtro por Ministerio -->
        <div>
          <label
            for="areas-filters-ministerio-id"
            class="block text-sm font-bold text-slate-500 mb-1"
            >Ministerio</label
          >
          <select
            id="areas-filters-ministerio-id"
            v-model="filters.ministerio_id"
            @change="fetchAreas"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600 transition"
          >
            <option value="">Todos los Ministerios</option>
            <option v-for="m in ministerios" :key="m.id" :value="m.id">
              {{ m.sigla }} - {{ m.nombre }}
            </option>
          </select>
        </div>

        <!-- Filtro por Homologación -->
        <div>
          <label
            for="areas-filters-es-homologada"
            class="block text-sm font-bold text-slate-500 mb-1"
            >Estado de Homologación</label
          >
          <select
            id="areas-filters-es-homologada"
            v-model="filters.es_homologada"
            @change="fetchAreas"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600 transition"
          >
            <option value="">Todas las Áreas</option>
            <option value="true">
              Solo Homologadas {{ agency.shortName }}
            </option>
            <option value="false">En Trámite / No Homologadas</option>
          </select>
        </div>

        <!-- Filtro por Nivel Jerárquico -->
        <div>
          <label
            for="areas-filters-nivel-jerarquico"
            class="block text-sm font-bold text-slate-500 mb-1"
            >Nivel Jerárquico</label
          >
          <select
            id="areas-filters-nivel-jerarquico"
            v-model="filters.nivel_jerarquico"
            @change="fetchAreas"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600 transition"
          >
            <option value="">Todos los Niveles</option>
            <option value="DIRECCION_PROVINCIAL">Dirección Provincial</option>
            <option value="DIRECCION_GENERAL">Dirección General</option>
            <option value="DIRECCION">Dirección</option>
            <option value="SUBDIRECCION">Subdirección</option>
            <option value="DEPARTAMENTO">Departamento</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Grilla de Áreas TI -->
    <div
      v-if="loading"
      class="p-12 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200"
    >
      Cargando áreas de tecnología...
    </div>

    <div
      v-else-if="areas.length === 0"
      class="p-12 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200"
    >
      No se encontraron áreas TI que coincidan con los criterios de búsqueda.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="area in areas"
        :key="area.id"
        class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden group"
      >
        <!-- Cabecera de la Tarjeta -->
        <div class="p-5 space-y-3">
          <div class="flex items-start justify-between gap-2">
            <span
              class="px-2 py-0.5 bg-slate-100 text-slate-700 font-mono text-xs font-bold rounded"
            >
              {{ area.codigo }}
            </span>

            <span
              class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider"
              :class="
                area.es_homologada
                  ? 'bg-emerald-100 text-emerald-800'
                  : 'bg-amber-100 text-amber-800'
              "
            >
              {{
                area.es_homologada
                  ? `Homologada ${agency.shortName}`
                  : "En Trámite"
              }}
            </span>
          </div>

          <div>
            <span
              class="text-xs font-bold uppercase text-primary-700 tracking-wider"
            >
              {{ area.ministerio?.sigla }} - {{ area.ministerio?.nombre }}
            </span>
            <h2
              class="text-base font-bold text-slate-900 mt-0.5 group-hover:text-primary-700 transition"
            >
              <NuxtLink :to="`/areas-ti/${area.id}`">{{
                area.nombre
              }}</NuxtLink>
            </h2>
            <p
              v-if="area.resolucion_homologacion"
              class="text-xs text-slate-500 mt-1"
            >
              Resolución:
              <strong class="text-slate-600">{{
                area.resolucion_homologacion
              }}</strong>
            </p>
          </div>

          <!-- Máxima Autoridad del Área -->
          <div
            class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 text-sm"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-bold uppercase text-slate-500"
                >Máxima Autoridad TI</span
              >
              <button
                v-if="authStore.isEditor"
                @click="openModalAutoridad(area)"
                class="text-xs font-bold text-primary-700 hover:text-primary-800 hover:underline"
              >
                Cambiar / Designar
              </button>
            </div>

            <div
              v-if="area.maxima_autoridad_vigente"
              class="flex items-center gap-2"
            >
              <span class="text-amber-500 font-bold text-sm">⭐</span>
              <div class="truncate">
                <p class="font-bold text-slate-800 truncate">
                  {{ area.maxima_autoridad_vigente.persona?.apellido }},
                  {{ area.maxima_autoridad_vigente.persona?.nombre }}
                </p>
                <p class="text-xs text-primary-700 truncate">
                  {{ area.maxima_autoridad_vigente.tipo_rol?.nombre }}
                </p>
              </div>
            </div>
            <div v-else class="text-slate-500 italic text-xs">
              Sin máxima autoridad asignada
            </div>
          </div>

          <!-- Datos de Contacto y Métricas -->
          <div
            class="flex items-center justify-between text-xs text-slate-500 pt-1"
          >
            <span>👥 {{ area.asignaciones_vigentes_count || 0 }} agentes</span>
            <span>⚡ {{ area.servicios_count || 0 }} servicios provistos</span>
            <span>📄 {{ area.documentos_count || 0 }} actas/anexos</span>
          </div>
        </div>

        <!-- Footer con Acciones -->
        <div
          class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-sm"
        >
          <NuxtLink
            :to="`/areas-ti/${area.id}`"
            class="font-bold text-primary-700 hover:text-primary-800 transition"
          >
            Ver Expediente Completo →
          </NuxtLink>

          <div v-if="authStore.isEditor" class="flex items-center gap-2">
            <button
              @click="openModalAsociarServicio(area)"
              class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg font-bold text-xs transition flex items-center gap-1"
              title="Asociar Servicio Tecnológico a este Organismo"
            >
              <span>⚡</span> Asociar Servicio
            </button>
            <button
              @click="openModalEdit(area)"
              class="p-1.5 text-slate-500 hover:text-primary-700 hover:bg-slate-200/60 rounded-lg transition"
              title="Editar Área TI"
            >
              ✏️
            </button>
            <button
              v-if="authStore.isAdmin"
              @click="confirmDelete(area)"
              class="p-1.5 text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
              title="Eliminar Área TI"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: CREAR / EDITAR ÁREA TI                                         -->
    <!-- ===================================================================== -->
    <div
      v-if="showModalForm"
      class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
      <div
        v-dialogo="() => (showModalForm = false)"
        class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto"
      >
        <div
          class="p-5 bg-slate-900 text-white flex items-center justify-between"
        >
          <h2 class="text-base font-bold">
            {{
              isEditing
                ? "Editar Área TI Homologada"
                : "Registrar Nueva Área TI"
            }}
          </h2>
          <button
            type="button"
            aria-label="Cerrar"
            @click="showModalForm = false"
            class="text-slate-400 hover:text-white"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="saveArea" class="p-6 space-y-4 text-sm">
          <div>
            <label
              for="areas-formarea-ministerio-id"
              class="block font-bold text-slate-700 mb-1"
              >Ministerio Perteneciente *</label
            >
            <select
              id="areas-formarea-ministerio-id"
              v-model="formArea.ministerio_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
            >
              <option value="" disabled>Seleccione Ministerio...</option>
              <option v-for="m in ministerios" :key="m.id" :value="m.id">
                {{ m.sigla }} - {{ m.nombre }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label
                for="areas-formarea-codigo"
                class="block font-bold text-slate-700 mb-1"
                >Código Único Institucional *</label
              >
              <input
                id="areas-formarea-codigo"
                v-model="formArea.codigo"
                type="text"
                required
                placeholder="Ej: TI-MS-03"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
              />
            </div>
            <div>
              <label
                for="areas-formarea-nivel-jerarquico"
                class="block font-bold text-slate-700 mb-1"
                >Nivel Jerárquico *</label
              >
              <select
                id="areas-formarea-nivel-jerarquico"
                v-model="formArea.nivel_jerarquico"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
              >
                <option value="DIRECCION_PROVINCIAL">
                  Dirección Provincial
                </option>
                <option value="DIRECCION_GENERAL">Dirección General</option>
                <option value="DIRECCION">Dirección</option>
                <option value="SUBDIRECCION">Subdirección</option>
                <option value="DEPARTAMENTO">Departamento</option>
              </select>
            </div>
          </div>

          <div>
            <label
              for="areas-formarea-nombre"
              class="block font-bold text-slate-700 mb-1"
              >Nombre Oficial del Área TI *</label
            >
            <input
              id="areas-formarea-nombre"
              v-model="formArea.nombre"
              type="text"
              required
              placeholder="Ej: Dirección Provincial de Infraestructura y Servicios de TI"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
            />
          </div>

          <div
            class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2"
          >
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="formArea.es_homologada"
                class="w-4 h-4 text-emerald-700 rounded border-slate-300 focus:ring-emerald-600"
              />
              <span class="font-bold text-slate-800"
                >Área Homologada formalmente ante la DPSIT</span
              >
            </label>

            <div v-if="formArea.es_homologada">
              <label
                for="areas-formarea-resolucion-homologacion"
                class="block font-semibold text-slate-600 mb-1"
                >Número de Resolución / Acto de Homologación</label
              >
              <input
                id="areas-formarea-resolucion-homologacion"
                v-model="formArea.resolucion_homologacion"
                type="text"
                placeholder="Ej: RES-2024-150-DPSIT"
                class="w-full p-2 bg-white border border-slate-200 rounded-lg text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label
                for="areas-formarea-email-institucional"
                class="block font-bold text-slate-700 mb-1"
                >Email Institucional</label
              >
              <input
                id="areas-formarea-email-institucional"
                v-model="formArea.email_institucional"
                type="email"
                placeholder="sistemas@organismo.gob.ar"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
              />
            </div>
            <div>
              <label
                for="areas-formarea-telefono-contacto"
                class="block font-bold text-slate-700 mb-1"
                >Teléfono de Contacto</label
              >
              <input
                id="areas-formarea-telefono-contacto"
                v-model="formArea.telefono_contacto"
                type="text"
                placeholder="0221-429-0000 int 123"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-600"
              />
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalForm = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2 bg-primary-700 hover:bg-primary-600 text-white rounded-xl font-bold shadow-md shadow-primary-700/20 disabled:opacity-50"
            >
              {{
                saving
                  ? "Guardando..."
                  : isEditing
                    ? "Guardar Cambios"
                    : "Registrar Área TI"
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: DESIGNAR MÁXIMA AUTORIDAD                                      -->
    <!-- ===================================================================== -->
    <AreasTiDesignarAutoridadModal
      v-if="showModalAutoridad && selectedArea"
      :area="selectedArea"
      :tipos-rol="tiposRol"
      @close="showModalAutoridad = false"
      @saved="alDesignarAutoridad"
    />

    <!-- ===================================================================== -->
    <!-- MODAL: ASOCIAR SERVICIO TECNOLÓGICO DESDE EL LISTADO                  -->
    <!-- ===================================================================== -->
    <div
      v-if="showModalServicio"
      class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
      <div
        v-dialogo="() => (showModalServicio = false)"
        class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto"
      >
        <div
          class="p-5 bg-emerald-950 text-white flex items-center justify-between"
        >
          <div>
            <h2 class="text-base font-bold">Asociar Servicio Tecnológico</h2>
            <p class="text-sm text-emerald-300">
              {{ selectedAreaParaServicio?.nombre }} ({{
                selectedAreaParaServicio?.codigo
              }})
            </p>
          </div>
          <button
            type="button"
            aria-label="Cerrar"
            @click="showModalServicio = false"
            class="text-slate-400 hover:text-white"
          >
            ✕
          </button>
        </div>

        <form
          @submit.prevent="saveServicioAsociacion"
          class="p-6 space-y-4 text-sm"
        >
          <div>
            <label
              for="areas-formservicio-catalogo-servicio-id"
              class="block font-bold text-slate-700 mb-1"
              >Tipo de Servicio del Catálogo *</label
            >
            <select
              id="areas-formservicio-catalogo-servicio-id"
              v-model="formServicio.catalogo_servicio_id"
              @change="onTipoServicioChange"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            >
              <option value="" disabled>Seleccione Servicio...</option>
              <option
                v-for="c in catalogoServicios.filter((x) => x.activo)"
                :key="c.id"
                :value="c.id"
              >
                {{ c.nombre }} ({{ c.codigo }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label
                for="areas-formservicio-codigo-instancia"
                class="block font-bold text-slate-700 mb-1"
                >Código Único de Instancia *</label
              >
              <input
                id="areas-formservicio-codigo-instancia"
                v-model="formServicio.codigo_instancia"
                type="text"
                required
                placeholder="Ej: CLOUD-MS-01"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600"
              />
            </div>
            <div>
              <label
                for="areas-formservicio-sla-nivel"
                class="block font-bold text-slate-700 mb-1"
                >Nivel de SLA *</label
              >
              <select
                id="areas-formservicio-sla-nivel"
                v-model="formServicio.sla_nivel"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600"
              >
                <option value="99.9% Disponibilidad 24x7">
                  Platinum (99.9% 24x7)
                </option>
                <option value="99.5% Disponibilidad Lun-Vie">
                  Gold (99.5% 12x5)
                </option>
                <option value="99.0% Horario Hábil">
                  Silver (99.0% Horario Hábil)
                </option>
                <option value="Mejor Esfuerzo">
                  Estándar (Mejor Esfuerzo)
                </option>
              </select>
            </div>
          </div>

          <div>
            <label
              for="areas-formservicio-observaciones"
              class="block font-bold text-slate-700 mb-1"
              >Observaciones</label
            >
            <textarea
              id="areas-formservicio-observaciones"
              v-model="formServicio.observaciones"
              rows="2"
              placeholder="Detalles sobre provisión, requerimientos o acuerdos del servicio..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalServicio = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingServicio"
              class="px-5 py-2 bg-emerald-700 hover:bg-emerald-600 text-white rounded-xl font-bold shadow-md shadow-emerald-700/20 disabled:opacity-50"
            >
              {{ savingServicio ? "Guardando..." : "Asociar Servicio al Área" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useApi } from "~/composables/useApi";
import { useAuthStore } from "~/stores/auth";
import { useAgency } from "~/composables/useAgency";
import { useToast } from "~/composables/useToast";
import { hoyISO } from "~/composables/useFechas";

const { get, post, put, delete: del } = useApi();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const agency = useAgency();
const { toast, confirmDialog } = useToast();

const areas = ref([]);
const ministerios = ref([]);
const tiposRol = ref([]);
const catalogoServicios = ref([]);

const stats = reactive({
  total: 0,
  homologadas: 0,
  en_tramite: 0,
});

const filters = reactive({
  q: "",
  ministerio_id: "",
  es_homologada: "",
  nivel_jerarquico: "",
});

const loading = ref(true);
const saving = ref(false);

// Modales
const showModalForm = ref(false);
const isEditing = ref(false);
const selectedArea = ref(null);

const showModalAutoridad = ref(false);

// Modal Asociar Servicio
const showModalServicio = ref(false);
const selectedAreaParaServicio = ref(null);
const savingServicio = ref(false);
const formServicio = reactive({
  area_ti_id: null,
  catalogo_servicio_id: "",
  codigo_instancia: "",
  estado_servicio: "ACTIVO",
  sla_nivel: "99.9% Disponibilidad 24x7",
  fecha_alta: hoyISO(),
  observaciones: "",
});

const formArea = reactive({
  id: null,
  ministerio_id: "",
  codigo: "",
  nombre: "",
  nivel_jerarquico: "DIRECCION_GENERAL",
  es_homologada: true,
  resolucion_homologacion: "",
  domicilio_fisico: "",
  telefono_contacto: "",
  email_institucional: "",
  activo: true,
});

let debounceTimer = null;
const debounceSearch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchAreas();
  }, 350);
};

const fetchAreas = async () => {
  loading.value = true;
  try {
    const res = await get("/areas-ti", filters);
    if (res.success) {
      areas.value = res.data;
      if (res.stats) {
        stats.total = res.stats.total;
        stats.homologadas = res.stats.homologadas;
        stats.en_tramite = res.stats.en_tramite;
      }
    }
  } catch (err) {
    console.error("Error fetching areas:", err);
    toast.error(err, { title: "Error al cargar áreas TI" });
  } finally {
    loading.value = false;
  }
};

const fetchCatalogos = async () => {
  try {
    const [resMin, resRol, resCat] = await Promise.all([
      get("/ministerios"),
      get("/tipos-rol"),
      get("/catalogo-servicios"),
    ]);
    if (resMin.success) ministerios.value = resMin.data;
    if (resRol.success) tiposRol.value = resRol.data;
    if (resCat.success) catalogoServicios.value = resCat.data;
  } catch (err) {
    console.error("Error fetching catalogos:", err);
  }
};

// -------------------------------------------------------------
// ASOCIAR SERVICIO TECNOLÓGICO AL ÁREA DESDE EL LISTADO
// -------------------------------------------------------------
const openModalAsociarServicio = (area) => {
  selectedAreaParaServicio.value = area;
  const primerCat = catalogoServicios.value[0];
  Object.assign(formServicio, {
    area_ti_id: area.id,
    catalogo_servicio_id: primerCat?.id || "",
    codigo_instancia: "",
    estado_servicio: "ACTIVO",
    sla_nivel: "99.9% Disponibilidad 24x7",
    fecha_alta: hoyISO(),
    observaciones: "",
  });
  onTipoServicioChange();
  showModalServicio.value = true;
};

const onTipoServicioChange = () => {
  const selected = catalogoServicios.value.find(
    (c) => c.id === formServicio.catalogo_servicio_id,
  );
  if (selected) {
    const areaCod =
      selectedAreaParaServicio.value?.codigo?.replace("TI-", "") || "AREA";
    const srvCod =
      selected.codigo?.replace("SRV_", "").replace("SRV-", "") || "SRV";
    formServicio.codigo_instancia = `${srvCod}-${areaCod}-01`;
  }
};

const saveServicioAsociacion = async () => {
  savingServicio.value = true;
  const payload = {
    ...formServicio,
    area_ti_id: selectedAreaParaServicio.value?.id,
  };

  try {
    const res = await post("/servicios-areas", payload);
    if (res.success) {
      toast.success(
        `Servicio asociado con éxito a ${selectedAreaParaServicio.value?.nombre}.`,
      );
      showModalServicio.value = false;
      await fetchAreas();
    }
  } catch (err) {
    toast.error(err);
  } finally {
    savingServicio.value = false;
  }
};

const openModalCreate = () => {
  isEditing.value = false;
  Object.assign(formArea, {
    id: null,
    // Con la lista filtrada por un ministerio, el alta lo propone (evita cargarla en otro)
    ministerio_id: filters.ministerio_id || ministerios.value[0]?.id || "",
    codigo: "",
    nombre: "",
    nivel_jerarquico: "DIRECCION_GENERAL",
    es_homologada: true,
    resolucion_homologacion: "",
    domicilio_fisico: "",
    telefono_contacto: "",
    email_institucional: "",
    activo: true,
  });
  showModalForm.value = true;
};

const openModalEdit = (area) => {
  isEditing.value = true;
  Object.assign(formArea, {
    id: area.id,
    ministerio_id: area.ministerio_id,
    codigo: area.codigo,
    nombre: area.nombre,
    nivel_jerarquico: area.nivel_jerarquico,
    es_homologada: area.es_homologada,
    resolucion_homologacion: area.resolucion_homologacion || "",
    domicilio_fisico: area.domicilio_fisico || "",
    telefono_contacto: area.telefono_contacto || "",
    email_institucional: area.email_institucional || "",
    activo: area.activo,
  });
  showModalForm.value = true;
};

const saveArea = async () => {
  saving.value = true;
  try {
    if (isEditing.value) {
      const res = await put(`/areas-ti/${formArea.id}`, formArea);
      if (res.success) {
        toast.success("Área TI actualizada exitosamente.");
        showModalForm.value = false;
        await fetchAreas();
      }
    } else {
      const res = await post("/areas-ti", formArea);
      if (res.success) {
        toast.success("Área TI registrada exitosamente.");
        showModalForm.value = false;
        await fetchAreas();
      }
    }
  } catch (err) {
    toast.error(err);
  } finally {
    saving.value = false;
  }
};

const openModalAutoridad = (area) => {
  selectedArea.value = area;
  showModalAutoridad.value = true;
};

const alDesignarAutoridad = async () => {
  showModalAutoridad.value = false;
  await fetchAreas();
};

const confirmDelete = async (area) => {
  const confirmed = await confirmDialog({
    title: "Eliminar Área TI",
    message: `¿Está seguro de que desea eliminar el área "${area.nombre}" (${area.codigo})? Esta acción no se puede deshacer.`,
    confirmText: "Sí, eliminar",
    cancelText: "Cancelar",
    type: "danger",
  });

  if (confirmed) {
    try {
      const res = await del(`/areas-ti/${area.id}`);
      if (res.success) {
        toast.success("Área TI eliminada exitosamente.");
        await fetchAreas();
      }
    } catch (err) {
      toast.error(err);
    }
  }
};

onMounted(async () => {
  // Desde Ministerios: ?ministerio_id=X filtra la lista y &nueva=1 abre el alta con ese ministerio
  const ministerioId = Number(route.query.ministerio_id);
  if (ministerioId) filters.ministerio_id = ministerioId;

  fetchAreas();
  await fetchCatalogos();

  if (route.query.nueva === "1") {
    if (authStore.isEditor) openModalCreate();
    // Se quita de la URL para que recargar o volver atrás no reabra el formulario
    const { nueva, ...query } = route.query;
    router.replace({ query });
  }
});
</script>
