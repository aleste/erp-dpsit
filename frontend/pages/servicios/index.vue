<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Encabezado Principal y Botones de Acción -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Administración de Servicios Tecnológicos</h1>
        <p class="text-sm text-slate-600 mt-0.5">
          Gestión rectora del catálogo de servicios provinciales y aprovisionamiento a organismos del Estado.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="authStore.isEditor && activeTab === 'catalogo'"
          @click="openModalCreateCatalogo"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-sm font-bold shadow-md shadow-sky-600/20 transition"
        >
          <span>+</span> Nuevo Tipo de Servicio
        </button>

        <button
          v-if="authStore.isEditor && activeTab === 'instancias'"
          @click="openModalAprovisionar"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-md shadow-emerald-600/20 transition"
        >
          <span>⚡+</span> Aprovisionar Servicio a Organismo
        </button>
      </div>
    </div>

    <!-- Pestañas de Navegación del Módulo -->
    <div class="flex border-b border-slate-200 space-x-6">
      <button
        @click="activeTab = 'catalogo'"
        class="pb-3 text-sm font-extrabold border-b-2 transition flex items-center gap-2"
        :class="activeTab === 'catalogo' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        <span>📚</span> Catálogo de Tipos de Servicio ({{ catalogo.length }})
      </button>

      <button
        @click="activeTab = 'instancias'"
        class="pb-3 text-sm font-extrabold border-b-2 transition flex items-center gap-2"
        :class="activeTab === 'instancias' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        <span>⚡</span> Instancias Provistas a Áreas TI ({{ totalInstancias }})
      </button>
    </div>

    <!-- ===================================================================== -->
    <!-- PESTAÑA 1: CATÁLOGO DE TIPOS DE SERVICIO                              -->
    <!-- ===================================================================== -->
    <div v-if="activeTab === 'catalogo'" class="space-y-6">
      <!-- Indicadores Rápidos del Catálogo -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Tipos en Catálogo</p>
            <h3 class="text-2xl font-extrabold text-slate-800 mt-0.5">{{ catalogo.length }}</h3>
          </div>
          <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-base">
            📋
          </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Servicios Activos</p>
            <h3 class="text-2xl font-extrabold text-emerald-600 mt-0.5">
              {{ catalogo.filter(c => c.activo).length }}
            </h3>
          </div>
          <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
            ✓
          </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Áreas TI Usuarias</p>
            <h3 class="text-2xl font-extrabold text-indigo-600 mt-0.5">{{ totalAreasConServicios }}</h3>
          </div>
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base">
            🏢
          </div>
        </div>
      </div>

      <!-- Barra de Filtros del Catálogo -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2">
            <label for="servicios-filterscatalogo-q" class="block text-sm font-bold text-slate-500 mb-1">Buscar Tipo de Servicio</label>
            <input id="servicios-filterscatalogo-q"
              v-model="filtersCatalogo.q"
              type="text"
              placeholder="Buscar por código, nombre o descripción (ej. Cloud, SSO, API)..."
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition"
            />
          </div>

          <div>
            <label for="servicios-filterscatalogo-categoria" class="block text-sm font-bold text-slate-500 mb-1">Categoría Tecnológica</label>
            <select id="servicios-filterscatalogo-categoria"
              v-model="filtersCatalogo.categoria"
              @change="fetchCatalogo"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todas las Categorías</option>
              <option value="INFRAESTRUCTURA">Infraestructura y Cómputo</option>
              <option value="IDENTIDAD">Identidad y Autenticación (SSO)</option>
              <option value="INTEGRACION">Interoperabilidad y APIs</option>
              <option value="SEGURIDAD">Seguridad y Redes VPN</option>
              <option value="SOFTWARE">Software y Sistemas Transversales</option>
              <option value="OTRO">Otras Tecnologías</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Grilla de Tarjetas del Catálogo -->
      <div v-if="loadingCatalogo" class="p-12 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200">
        Cargando catálogo de servicios tecnológicos...
      </div>

      <div v-else-if="filteredCatalogo.length === 0" class="p-12 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200">
        No se encontraron tipos de servicio que coincidan con la búsqueda.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="item in filteredCatalogo"
          :key="item.id"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden group"
        >
          <div class="p-5 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <span class="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                {{ item.codigo }}
              </span>

              <span
                class="px-2 py-0.5 rounded text-xs font-extrabold uppercase tracking-wider"
                :class="{
                  'bg-sky-100 text-sky-800': item.categoria === 'INFRAESTRUCTURA',
                  'bg-emerald-100 text-emerald-800': item.categoria === 'IDENTIDAD',
                  'bg-purple-100 text-purple-800': item.categoria === 'INTEGRACION',
                  'bg-amber-100 text-amber-800': item.categoria === 'SEGURIDAD',
                  'bg-rose-100 text-rose-800': item.categoria === 'SOFTWARE',
                  'bg-slate-100 text-slate-700': !['INFRAESTRUCTURA', 'IDENTIDAD', 'INTEGRACION', 'SEGURIDAD', 'SOFTWARE'].includes(item.categoria)
                }"
              >
                {{ item.categoria }}
              </span>
            </div>

            <div>
              <h3 class="text-base font-bold text-slate-900 group-hover:text-sky-600 transition">
                {{ item.nombre }}
              </h3>
              <p class="text-sm text-slate-500 mt-1 line-clamp-3 leading-relaxed">
                {{ item.descripcion || 'Sin descripción detallada.' }}
              </p>
            </div>

            <!-- Contador de áreas asociadas -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
              <span>⚡ {{ item.servicios_areas_count || 0 }} organismos provistos</span>
              <span
                class="px-2 py-0.5 rounded text-xs font-bold uppercase"
                :class="item.activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ item.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
          </div>

          <!-- Footer con Acciones -->
          <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-sm">
            <button
              @click="openModalAprovisionarConTipo(item)"
              class="font-bold text-sky-600 hover:text-sky-800 transition"
            >
              + Aprovisionar a Área →
            </button>

            <div v-if="authStore.isEditor" class="flex items-center gap-1.5">
              <button
                @click="openModalEditCatalogo(item)"
                class="p-1.5 text-slate-500 hover:text-sky-600 hover:bg-slate-200/60 rounded-lg transition"
                title="Editar tipo de servicio"
              >
                ✏️
              </button>
              <button
                v-if="authStore.isAdmin"
                @click="handleDeleteCatalogo(item)"
                class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                title="Eliminar del catálogo"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- PESTAÑA 2: INSTANCIAS PROVISTAS A ÁREAS TI                            -->
    <!-- ===================================================================== -->
    <div v-if="activeTab === 'instancias'" class="space-y-6">
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label for="servicios-filtersinstancias-ministerio-id" class="block text-sm font-bold text-slate-500 mb-1">Filtrar por Organismo</label>
            <select id="servicios-filtersinstancias-ministerio-id"
              v-model="filtersInstancias.ministerio_id"
              @change="fetchInstancias"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todos los Ministerios</option>
              <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.sigla }} - {{ m.nombre }}</option>
            </select>
          </div>

          <div>
            <label for="servicios-filtersinstancias-catalogo-servicio-id" class="block text-sm font-bold text-slate-500 mb-1">Tipo de Servicio</label>
            <select id="servicios-filtersinstancias-catalogo-servicio-id"
              v-model="filtersInstancias.catalogo_servicio_id"
              @change="fetchInstancias"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todos los Tipos</option>
              <option v-for="c in catalogo" :key="c.id" :value="c.id">{{ c.nombre }} ({{ c.codigo }})</option>
            </select>
          </div>

          <div>
            <label for="servicios-filtersinstancias-estado-servicio" class="block text-sm font-bold text-slate-500 mb-1">Estado de Servicio</label>
            <select id="servicios-filtersinstancias-estado-servicio"
              v-model="filtersInstancias.estado_servicio"
              @change="fetchInstancias"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
            >
              <option value="">Todos los Estados</option>
              <option value="ACTIVO">Activo</option>
              <option value="EN_PROCESO">En Proceso</option>
              <option value="SOLICITADO">Solicitado</option>
              <option value="SUSPENDIDO">Suspendido</option>
              <option value="DADO_DE_BAJA">Dado de Baja</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Listado de Instancias -->
      <div v-if="loadingInstancias" class="p-12 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200">
        Cargando instancias de servicios provistos...
      </div>

      <div v-else-if="instancias.length === 0" class="p-12 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200">
        No se registran instancias de servicios provistos con los filtros seleccionados.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div
          v-for="srv in instancias"
          :key="srv.id"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition p-6 space-y-4 flex flex-col justify-between"
        >
          <div class="space-y-3">
            <div class="flex items-start justify-between gap-2">
              <span
                class="px-2.5 py-1 rounded-lg text-xs font-extrabold uppercase tracking-wide"
                :class="{
                  'bg-emerald-100 text-emerald-800': srv.estado_servicio === 'ACTIVO',
                  'bg-amber-100 text-amber-800': srv.estado_servicio === 'EN_PROCESO' || srv.estado_servicio === 'SOLICITADO',
                  'bg-rose-100 text-rose-800': srv.estado_servicio === 'SUSPENDIDO',
                  'bg-slate-100 text-slate-700': srv.estado_servicio === 'DADO_DE_BAJA'
                }"
              >
                {{ srv.estado_servicio }}
              </span>

              <span class="font-mono text-sm font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                {{ srv.codigo_instancia }}
              </span>
            </div>

            <div>
              <span class="text-xs font-bold uppercase text-sky-600 tracking-wider">
                {{ srv.area_ti?.ministerio?.sigla }} - {{ srv.area_ti?.nombre }}
              </span>
              <h3 class="text-base font-bold text-slate-900 mt-0.5">{{ srv.catalogo_servicio?.nombre }}</h3>
              <p class="text-sm text-slate-500 mt-0.5">SLA: <strong>{{ srv.sla_nivel }}</strong> | Alta: {{ srv.fecha_alta }}</p>
              <p v-if="srv.observaciones" class="text-sm text-slate-600 mt-1 italic">{{ srv.observaciones }}</p>
            </div>

            <!-- Referentes Asignados con Opción de Quitar Directamente -->
            <div class="text-sm pt-1">
              <p class="text-xs font-bold uppercase text-slate-500 mb-1.5">Referentes del Servicio</p>
              <div v-if="!srv.referentes || srv.referentes.length === 0" class="text-slate-500 italic text-xs">
                Sin referentes asignados.
              </div>
              <div v-else class="flex flex-wrap gap-1.5">
                <span
                  v-for="ref in srv.referentes"
                  :key="ref.id"
                  class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-xs text-slate-700"
                >
                  <span><strong>{{ ref.persona?.apellido }}, {{ ref.persona?.nombre }}</strong> ({{ ref.tipo_referente }})</span>
                  <button
                    v-if="authStore.isEditor"
                    @click="handleRemoveReferente(srv, ref)"
                    class="ml-1 text-slate-500 hover:text-rose-600 font-bold p-0.5 hover:bg-rose-50 rounded transition"
                    title="Desvincular referente"
                  >
                    ✕
                  </button>
                </span>
              </div>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-sm">
            <NuxtLink
              :to="`/areas-ti/${srv.area_ti_id}`"
              class="font-bold text-sky-600 hover:text-sky-800 transition"
            >
              Ver Expediente del Área →
            </NuxtLink>

            <button
              v-if="authStore.isAdmin"
              @click="handleDeleteInstancia(srv)"
              class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
              title="Eliminar instancia de servicio"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: CREAR / EDITAR TIPO DE SERVICIO (CATÁLOGO)                     -->
    <!-- ===================================================================== -->
    <div v-if="showModalCatalogo" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalCatalogo = false)" class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">
              {{ isEditingCatalogo ? 'Editar Tipo de Servicio' : 'Nuevo Tipo de Servicio Tecnológico' }}
            </h2>
            <p class="text-sm text-slate-400">Catálogo Rector de Tecnologías</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="showModalCatalogo = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveCatalogo" class="p-6 space-y-4 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="servicios-formcatalogo-codigo" class="block font-bold text-slate-700 mb-1">Código Único *</label>
              <input id="servicios-formcatalogo-codigo"
                v-model="formCatalogo.codigo"
                type="text"
                required
                placeholder="Ej: SRV-CLOUD-01"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>

            <div>
              <label for="servicios-formcatalogo-categoria" class="block font-bold text-slate-700 mb-1">Categoría Tecnológica *</label>
              <select id="servicios-formcatalogo-categoria"
                v-model="formCatalogo.categoria"
                required
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
              >
                <option value="INFRAESTRUCTURA">Infraestructura y Cómputo</option>
                <option value="IDENTIDAD">Identidad y Autenticación (SSO)</option>
                <option value="INTEGRACION">Interoperabilidad y APIs</option>
                <option value="SEGURIDAD">Seguridad y Redes VPN</option>
                <option value="SOFTWARE">Software y Sistemas Transversales</option>
                <option value="OTRO">Otras Tecnologías</option>
              </select>
            </div>
          </div>

          <div>
            <label for="servicios-formcatalogo-nombre" class="block font-bold text-slate-700 mb-1">Nombre Oficial del Servicio *</label>
            <input id="servicios-formcatalogo-nombre"
              v-model="formCatalogo.nombre"
              type="text"
              required
              placeholder="Ej: Servicio de Nube Provincial - IaaS/PaaS"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>

          <div>
            <label for="servicios-formcatalogo-descripcion" class="block font-bold text-slate-700 mb-1">Descripción y Alcance</label>
            <textarea id="servicios-formcatalogo-descripcion"
              v-model="formCatalogo.descripcion"
              rows="3"
              placeholder="Detalles sobre infraestructura provista, requisitos de adhesión y características..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
            ></textarea>
          </div>

          <div class="pt-2">
            <label class="inline-flex items-center gap-2 cursor-pointer font-bold text-slate-700">
              <input
                type="checkbox"
                v-model="formCatalogo.activo"
                class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
              />
              Tipo de Servicio Habilitado en Catálogo
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalCatalogo = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingCatalogo"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold shadow-md shadow-sky-600/20 disabled:opacity-50"
            >
              {{ savingCatalogo ? 'Guardando...' : (isEditingCatalogo ? 'Guardar Cambios' : 'Registrar en Catálogo') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: APROVISIONAR SERVICIO A UN ÁREA TI                             -->
    <!-- ===================================================================== -->
    <div v-if="showModalAprovisionar" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div v-dialogo="() => (showModalAprovisionar = false)" class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="p-5 bg-emerald-950 text-white flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold">Aprovisionar Servicio Tecnológico</h2>
            <p class="text-sm text-emerald-300">Asignación formal a un Organismo / Área TI</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="showModalAprovisionar = false" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="saveAprovisionamiento" class="p-6 space-y-4 text-sm">
          <!-- Selector de Área TI -->
          <div>
            <label for="servicios-formaprovisionar-area-ti-id" class="block font-bold text-slate-700 mb-1">Área TI Destinataria *</label>
            <select id="servicios-formaprovisionar-area-ti-id"
              v-model="formAprovisionar.area_ti_id"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="" disabled>Seleccione Área TI...</option>
              <option v-for="a in areasList" :key="a.id" :value="a.id">
                {{ a.codigo }} - {{ a.nombre }} ({{ a.ministerio?.sigla }})
              </option>
            </select>
          </div>

          <!-- Selector de Tipo de Servicio -->
          <div>
            <label for="servicios-formaprovisionar-catalogo-servicio-id" class="block font-bold text-slate-700 mb-1">Tipo de Servicio del Catálogo *</label>
            <select id="servicios-formaprovisionar-catalogo-servicio-id"
              v-model="formAprovisionar.catalogo_servicio_id"
              @change="onTipoServicioChange"
              required
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="" disabled>Seleccione Servicio del Catálogo...</option>
              <option v-for="c in catalogo.filter(x => x.activo)" :key="c.id" :value="c.id">
                {{ c.nombre }} ({{ c.codigo }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="servicios-formaprovisionar-codigo-instancia" class="block font-bold text-slate-700 mb-1">Código Único de Instancia *</label>
              <input id="servicios-formaprovisionar-codigo-instancia"
                v-model="formAprovisionar.codigo_instancia"
                type="text"
                required
                placeholder="Ej: CLOUD-MS-01"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label for="servicios-formaprovisionar-sla-nivel" class="block font-bold text-slate-700 mb-1">Nivel de SLA Garantizado *</label>
              <select id="servicios-formaprovisionar-sla-nivel"
                v-model="formAprovisionar.sla_nivel"
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

          <div>
            <label for="servicios-formaprovisionar-observaciones" class="block font-bold text-slate-700 mb-1">Observaciones</label>
            <textarea id="servicios-formaprovisionar-observaciones"
              v-model="formAprovisionar.observaciones"
              rows="2"
              placeholder="Detalles sobre provisión, requerimientos o acuerdos del servicio..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModalAprovisionar = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="savingAprovisionar"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold shadow-md shadow-emerald-600/20 disabled:opacity-50"
            >
              {{ savingAprovisionar ? 'Aprovisionando...' : 'Confirmar Aprovisionamiento' }}
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

const activeTab = ref('catalogo')

const catalogo = ref([])
const instancias = ref([])
const areasList = ref([])
const ministerios = ref([])

const loadingCatalogo = ref(true)
const loadingInstancias = ref(true)

const filtersCatalogo = reactive({
  q: '',
  categoria: '',
})

const filtersInstancias = reactive({
  ministerio_id: '',
  catalogo_servicio_id: '',
  estado_servicio: '',
})

// Modales
const showModalCatalogo = ref(false)
const isEditingCatalogo = ref(false)
const savingCatalogo = ref(false)

const formCatalogo = reactive({
  id: null,
  codigo: '',
  nombre: '',
  categoria: 'INFRAESTRUCTURA',
  descripcion: '',
  activo: true,
})

const showModalAprovisionar = ref(false)
const savingAprovisionar = ref(false)

const formAprovisionar = reactive({
  area_ti_id: '',
  catalogo_servicio_id: '',
  codigo_instancia: '',
  sla_nivel: '99.9% Disponibilidad 24x7',
  estado_servicio: 'ACTIVO',
  fecha_alta: new Date().toISOString().split('T')[0],
  observaciones: '',
})

const totalInstancias = computed(() => instancias.value.length)
const totalAreasConServicios = computed(() => {
  const areaIds = new Set(instancias.value.map(i => i.area_ti_id))
  return areaIds.size
})

const filteredCatalogo = computed(() => {
  if (!filtersCatalogo.q) return catalogo.value
  const term = filtersCatalogo.q.toLowerCase()
  return catalogo.value.filter(c => 
    c.nombre.toLowerCase().includes(term) ||
    c.codigo.toLowerCase().includes(term) ||
    (c.descripcion && c.descripcion.toLowerCase().includes(term))
  )
})

const fetchCatalogo = async () => {
  loadingCatalogo.value = true
  try {
    const params = {}
    if (filtersCatalogo.categoria) params.categoria = filtersCatalogo.categoria
    const res = await get('/catalogo-servicios', params)
    if (res.success) {
      catalogo.value = res.data
    }
  } catch (err) {
    console.error('Error cargando catalogo:', err)
    toast.error(err, { title: 'Error al cargar catálogo' })
  } finally {
    loadingCatalogo.value = false
  }
}

const fetchInstancias = async () => {
  loadingInstancias.value = true
  try {
    const res = await get('/servicios-areas', filtersInstancias)
    if (res.success) {
      instancias.value = res.data.data || res.data
    }
  } catch (err) {
    console.error('Error cargando instancias:', err)
    toast.error(err, { title: 'Error al cargar instancias' })
  } finally {
    loadingInstancias.value = false
  }
}

const fetchAuxiliares = async () => {
  try {
    const [resAreas, resMin] = await Promise.all([
      get('/areas-ti'),
      get('/ministerios'),
    ])
    if (resAreas.success) areasList.value = resAreas.data
    if (resMin.success) ministerios.value = resMin.data
  } catch (err) {
    console.error('Error cargando datos auxiliares:', err)
  }
}

// Modal Catálogo: Crear
const openModalCreateCatalogo = () => {
  isEditingCatalogo.value = false
  Object.assign(formCatalogo, {
    id: null,
    codigo: '',
    nombre: '',
    categoria: 'INFRAESTRUCTURA',
    descripcion: '',
    activo: true,
  })
  showModalCatalogo.value = true
}

// Modal Catálogo: Editar
const openModalEditCatalogo = (item) => {
  isEditingCatalogo.value = true
  Object.assign(formCatalogo, {
    id: item.id,
    codigo: item.codigo,
    nombre: item.nombre,
    categoria: item.categoria,
    descripcion: item.descripcion || '',
    activo: item.activo,
  })
  showModalCatalogo.value = true
}

const saveCatalogo = async () => {
  savingCatalogo.value = true
  try {
    if (isEditingCatalogo.value) {
      const res = await put(`/catalogo-servicios/${formCatalogo.id}`, formCatalogo)
      if (res.success) {
        toast.success('Tipo de servicio actualizado exitosamente en el catálogo.')
        showModalCatalogo.value = false
        await fetchCatalogo()
      }
    } else {
      const res = await post('/catalogo-servicios', formCatalogo)
      if (res.success) {
        toast.success('Nuevo tipo de servicio registrado con éxito.')
        showModalCatalogo.value = false
        await fetchCatalogo()
      }
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingCatalogo.value = false
  }
}

const handleDeleteCatalogo = async (item) => {
  if (item.servicios_areas_count > 0) {
    toast.warning(
      `No es posible eliminar el servicio "${item.nombre}" porque se encuentra aprovisionado en ${item.servicios_areas_count} área(s) TI.`,
      { title: 'Servicio en Uso' }
    )
    return
  }

  const confirmed = await confirmDialog({
    title: 'Eliminar Tipo de Servicio',
    message: `¿Está seguro de eliminar "${item.nombre}" (${item.codigo}) del catálogo provincial?`,
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/catalogo-servicios/${item.id}`)
      if (res.success) {
        toast.success('Tipo de servicio eliminado del catálogo.')
        await fetchCatalogo()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

// Modal Aprovisionar: Abrir
const openModalAprovisionar = () => {
  formAprovisionar.area_ti_id = areasList.value[0]?.id || ''
  formAprovisionar.catalogo_servicio_id = catalogo.value[0]?.id || ''
  formAprovisionar.codigo_instancia = ''
  formAprovisionar.sla_nivel = '99.9% Disponibilidad 24x7'
  formAprovisionar.fecha_alta = new Date().toISOString().split('T')[0]
  formAprovisionar.observaciones = ''
  onTipoServicioChange()
  showModalAprovisionar.value = true
}

const openModalAprovisionarConTipo = (item) => {
  activeTab.value = 'instancias'
  formAprovisionar.area_ti_id = areasList.value[0]?.id || ''
  formAprovisionar.catalogo_servicio_id = item.id
  formAprovisionar.codigo_instancia = `${item.codigo.replace('SRV_', '').replace('SRV-', '')}-INST-${Math.floor(100 + Math.random() * 900)}`
  formAprovisionar.sla_nivel = '99.9% Disponibilidad 24x7'
  formAprovisionar.fecha_alta = new Date().toISOString().split('T')[0]
  formAprovisionar.observaciones = ''
  showModalAprovisionar.value = true
}

const onTipoServicioChange = () => {
  const selected = catalogo.value.find(c => c.id === formAprovisionar.catalogo_servicio_id)
  if (selected && !formAprovisionar.codigo_instancia) {
    formAprovisionar.codigo_instancia = `${selected.codigo.replace('SRV_', '').replace('SRV-', '')}-INST-${Math.floor(100 + Math.random() * 900)}`
  }
}

const saveAprovisionamiento = async () => {
  savingAprovisionar.value = true
  try {
    const res = await post('/servicios-areas', formAprovisionar)
    if (res.success) {
      toast.success('Servicio tecnológico aprovisionado exitosamente al organismo.')
      showModalAprovisionar.value = false
      await Promise.all([fetchInstancias(), fetchCatalogo()])
    }
  } catch (err) {
    toast.error(err)
  } finally {
    savingAprovisionar.value = false
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
        await fetchInstancias()
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

const handleDeleteInstancia = async (srv) => {
  const confirmed = await confirmDialog({
    title: 'Eliminar Instancia de Servicio',
    message: `¿Está seguro de eliminar la instancia "${srv.codigo_instancia}" asignada a ${srv.area_ti?.nombre}?`,
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
    type: 'danger',
  })

  if (confirmed) {
    try {
      const res = await del(`/servicios-areas/${srv.id}`)
      if (res.success) {
        toast.success('Instancia de servicio eliminada.')
        await Promise.all([fetchInstancias(), fetchCatalogo()])
      }
    } catch (err) {
      toast.error(err)
    }
  }
}

onMounted(() => {
  fetchCatalogo()
  fetchInstancias()
  fetchAuxiliares()
})
</script>
