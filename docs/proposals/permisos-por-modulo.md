# Propuesta: permisos de acceso por módulo

Estado: **implementada** (2026-10-27, rama `feat/permisos-por-modulo`; D-34, D-35, D-36). Las secciones 1–10
son el diagnóstico y el diseño originales; lo que cambió al implementar está en la sección 11.

## 0. Respuestas del responsable (2026-10-27)

- **P1**: los 6 módulos por ahora, pero el sistema debe admitir módulos nuevos → catálogo único en
  `UserModulo` publicado por la API, más el test de cobertura de rutas (sección 11).
- **P4**: los usuarios actuales son de prueba → equivalencia (opción A) por migración y combinaciones de
  prueba en `UserSeeder` hasta producción.
- **P5**: un operador da de alta y modifica personas, y las borra si son una carga errónea sin vínculos (D-35).
- **P6**: se corrigen las rutas públicas (D-36, reemplaza a D-17).
- P2, P3, P7, P8 y P9 no se respondieron: se aplicó la recomendación de la sección 10.

Convención: **[Hecho]** = comprobado en el código o ejecutado; **[Inferencia]** = deducido de la lectura,
sin prueba ejecutada; **[Propuesta]** = diseño a aprobar.

## 1. Diagnóstico del estado actual

### 1.1 Lo que ya existe (no partir de cero)

El sistema **ya tiene un modelo de permisos por módulo** (D-26, 2026-10-23), pero aplicado a un solo módulo.

| Pieza | Ubicación | Qué hace |
| :--- | :--- | :--- |
| Perfil global | `users.perfil_acceso` (ADMINISTRADOR, EDITOR, LECTOR; default `LECTOR`) | Controla todos los módulos de gobernanza TI |
| Middleware `perfil:` | `app/Http/Middleware/CheckPerfilAcceso.php` | Lista de perfiles permitidos; ADMINISTRADOR siempre pasa; 401 si inactivo, 403 con `message` |
| Rol por módulo | tabla `user_modulos` (`user_id`, `modulo` varchar 50, `rol` varchar 30, único por usuario+módulo), modelo `UserModulo` | `UserModulo::ROLES` define los módulos y sus roles ordenados; hoy solo `EXPEDIENTES => [CONSULTA, OPERADOR, SUPERVISOR]` |
| Middleware `modulo:` | `app/Http/Middleware/CheckModuloAcceso.php` | `modulo:EXPEDIENTES,OPERADOR`: exige ese rol o superior; el ADMINISTRADOR global tiene el rol máximo de cada módulo (`User::rolEnModulo`) |
| Rol efectivo para la UI | `User::modulosAcceso()`, devuelto por `POST /auth/login` y `GET /auth/user` | Mapa `{ MODULO: ROL }` |
| Gestión de usuarios | `UsuarioController` (`GET/POST/PUT /usuarios`, solo `perfil:ADMINISTRADOR`, D-27); página `pages/usuarios/index.vue` | Alta, edición de perfil y roles por módulo, activar/desactivar. Sin borrado. Auditoría con estado anterior y nuevo (incluye `modulos`) |
| Tests | `tests/Feature/Expedientes/PermisosModuloTest.php` (6), `UsuariosTest.php` (4) | Cubren el módulo de expedientes y el ABM de usuarios |

**[Hecho]** Suite completa: 70 tests, todos verdes (`wslc exec erp_backend php artisan test`, 2026-10-27).
**[Hecho]** Base de desarrollo: 3 usuarios demo activos (uno por perfil), **ninguna** fila en
`user_modulos`, 1 token. No hay producción.

### 1.2 Ciclo de vida del usuario

- **Registro**: no hay autorregistro. Alta solo por un administrador (`UsuarioController@store`), con
  contraseña inicial de mínimo 8 caracteres. `UserSeeder` crea 3 usuarios demo (SEC-05).
- **Modificación**: `update` reemplaza los roles por módulo (`sincronizarModulos`: borra y recrea) dentro de
  una transacción.
- **Activación/desactivación**: campo `activo`. Desactivar borra todos los tokens del usuario. Ambos
  middlewares rechazan a un usuario inactivo.
- **Eliminación**: no existe (D-27). `users` no usa `SoftDeletes`.
- **Protección del administrador**: un administrador no puede desactivarse ni quitarse el perfil a sí mismo.
  **No** hay control de «último administrador activo» como tal (ver 1.5, I-07).

### 1.3 Autenticación y sesión

- Tokens personales de Sanctum (Bearer) sin expiración (`config/sanctum.php`, SEC-04), guardados en
  `localStorage` junto con una copia del usuario (`dpsit_user`).
- El login crea el token con la *ability* `[perfil_acceso]`, que **no se consulta en ningún lado**.
- **[Inferencia]** Sanctum resuelve el usuario desde la base en cada request y `rolEnModulo` lee la relación
  `modulos` sin caché: un cambio de perfil o de roles rige en el backend **desde el request siguiente**, sin
  invalidar tokens. Falta un test que lo demuestre (lo incluye el plan).
- El frontend refresca los permisos solo al montar el layout (`authStore.refrescarUsuario()` en
  `layouts/default.vue`); entre tanto, el menú usa la copia de `localStorage`.

### 1.4 Módulos, menú y control en el frontend

No hay un registro de módulos: el menú está escrito a mano en `layouts/default.vue`. Solo «Expedientes»
(`puedeModulo('EXPEDIENTES','CONSULTA')`) y «Usuarios» (`isAdmin`) se ocultan; el resto se muestra a todo
usuario autenticado. **No hay middleware de rutas en Nuxt** (no existe `frontend/middleware/`): la única
guarda es el redirect a `/login` si no hay token, también en el `onMounted` del layout. Algunas páginas
muestran un aviso propio (`usuarios`, `expedientes/catalogos`). `useApi` maneja 401 (logout) pero no 403.

Dependencias reales entre pantallas y API (relevadas de las llamadas en `pages/` y `components/`):

| Pantalla | Endpoints que consume | Dependencias de otros dominios |
| :--- | :--- | :--- |
| Ministerios | `/ministerios`, `/autoridades-organismo` | `PersonaSearchSelect` → `/personas` |
| Áreas TI (lista y ficha) | `/areas-ti`, `/asignaciones-rol`, `/servicios-areas`, `/documentos/upload` | `/personas`, `/tipos-rol`, `/catalogo-servicios`, `/ministerios` |
| Personas | `/personas` | `/asignaciones-rol`, `/areas-ti`, `/ministerios`, `/tipos-rol` |
| Roles y vigencias | `/asignaciones-rol` | `/areas-ti`, `/ministerios`, `/tipos-rol` |
| Servicios | `/catalogo-servicios`, `/servicios-areas` | `/areas-ti`, `/ministerios` |
| Firma digital | `/firma-digital/*` | **`GET` y `POST /personas`** (alta de titular en `EntregaFormModal`), `/ministerios` |
| Expedientes | `/expedientes/*` | `/ministerios` |
| Exportador | `/export/preview`, `/export/download` | `/ministerios`, `/tipos-rol` |
| Inicio | `/dashboard/stats` | Totales de gobernanza y **últimas 5 asignaciones con datos de persona** |

Esta tabla es la razón principal del diseño de la sección 3.3: los módulos comparten catálogos de referencia.

### 1.5 Inconsistencias y accesos sin autorización adecuada

| ID | Hallazgo | Evidencia | Gravedad para este requisito |
| :--- | :--- | :--- | :--- |
| I-01 | Los módulos de gobernanza y firma digital se autorizan por perfil global: **todo usuario autenticado lee todo** (incluidos titulares de tokens y el padrón con CUIL, emails y teléfonos) | `routes/api.php`, bloque «LECTURA DE CATÁLOGOS» sin middleware | Alta: es lo que el requisito pide cambiar |
| I-02 | Sin guarda de rutas en el frontend: `/firma-digital`, `/personas`, etc. se abren por URL directa | no existe `frontend/middleware/` | Media (el backend es la barrera real) |
| I-03 | `GET /dashboard/stats` expone totales y las últimas asignaciones (con persona) a cualquier usuario | `DashboardController@stats` | Media |
| I-04 | `POST /export/preview` disponible para cualquier usuario autenticado | `routes/api.php` | Media |
| I-05 | Descarga de adjuntos y CSV del exportador son **públicas** (SEC-01, pospuesto por D-17): cualquier control por módulo es eludible por esas dos rutas | bloque «Rutas Públicas» | **Alta**; ver P6 |
| I-06 | Los roles por módulo están duplicados: `UserModulo::ROLES` (backend) y `ROLES_MODULO` (`stores/auth.ts`) | código | Baja (riesgo de divergencia) |
| I-07 | Solo hay autoprotección, no un recuento de administradores activos: dos administradores que se degradan entre sí al mismo tiempo pueden dejar el sistema sin ninguno; tampoco hay bloqueo de fila | `UsuarioController@update` | Baja (carrera improbable), fácil de cerrar |
| I-08 | El alta por defecto es `LECTOR` (migración y formulario), que hoy implica lectura de todo | migración `000010`, `pages/usuarios/index.vue` | Media: contradice mínimo privilegio |
| I-09 | La *ability* del token (`[perfil]`) nunca se usa y queda desactualizada si cambia el perfil | `AuthController@login` | Info |
| I-10 | `useApi` no trata el 403: la página queda con un toast genérico y el menú sigue desactualizado | `composables/useApi.ts` | Baja |
| I-11 | Tokens sin expiración (SEC-04): un token robado vale para siempre (la revocación de permisos sí es inmediata, ver 1.3) | `config/sanctum.php` | Media, fuera del alcance estricto |

### 1.6 Auditoría

`AuditService::log(ACCION, MODULO, id, anterior, nuevo, detalles)` guarda usuario, IP, user agent y fecha.
Altas y cambios de usuarios ya se auditan con el antes y el después, **incluidos los roles por módulo**: quién,
cuándo y qué cambió queda registrado. Faltan: una pantalla de consulta de la bitácora (pendiente funcional
conocido) y el registro de accesos denegados (403).

## 2. Propuesta funcional

- Cada usuario tiene **uno de dos tipos**: **Administrador** (acceso total + gestión de usuarios y permisos) o
  **Usuario** (accede solo a los módulos que tenga asignados, con un rol en cada uno).
- En cada módulo, el rol es **Consulta** (ver), **Operador** (cargar y editar) o, donde existe, **Supervisor**
  (correcciones especiales; hoy solo Expedientes). Las bajas siguen reservadas al Administrador (convención).
- Un usuario nuevo **no tiene acceso a ningún módulo** hasta que el administrador se lo asigna.
- El menú, la pantalla de inicio y las URL directas muestran solo lo autorizado; el backend rechaza lo demás.

Escenarios del pedido, expresados en el modelo:

| Escenario | Tipo | Módulos |
| :--- | :--- | :--- |
| Usuario de firma digital | Usuario | FIRMA_DIGITAL: Operador |
| Firma digital + expedientes | Usuario | FIRMA_DIGITAL: Operador; EXPEDIENTES: Operador |
| Gestión de organismos | Usuario | ORGANISMOS: Operador |
| Administrador del ERP | Administrador | todos, implícito |

## 3. Propuesta técnica

### 3.1 Alternativas evaluadas

| Alternativa | Ventajas | Limitaciones | Veredicto |
| :--- | :--- | :--- | :--- |
| A. Asignación directa de módulos sin rol (sí/no) | La más simple | No distingue ver de editar; obliga a mantener el perfil global para eso, y Expedientes ya usa 3 niveles | Descartada |
| **B. Extender D-26: rol por módulo + Administrador global** | Reutiliza tabla, modelo, middleware, UI y tests existentes; **sin cambios de esquema**; un módulo nuevo es una línea en `UserModulo::ROLES` | Sin «plantillas» de permisos: cada usuario se configura módulo por módulo (con ≤ 7 módulos es aceptable) | **Recomendada** |
| C. Roles y permisos genéricos (p. ej. `spatie/laravel-permission`) | Estándar, granular | Dependencia nueva (requiere autorización), duplica D-26, migración de todas las rutas a *permissions*, más complejo de administrar | Descartada por ahora |
| D. B + plantillas de rol («Equipo de firma») | Altas más rápidas | Más superficie; no hace falta con la cantidad actual de usuarios | Mejora futura (sección 9) |

### 3.2 Modelo de datos y reglas

- **[Propuesta]** Catálogo de módulos (P1), en `UserModulo::ROLES`, con una **escala de roles común**
  `CONSULTA < OPERADOR < SUPERVISOR` (cada módulo declara cuáles usa):

  | Módulo | Pantallas | Roles |
  | :--- | :--- | :--- |
  | `ORGANISMOS` | Ministerios (y autoridades), Áreas TI (ficha), Roles y vigencias | CONSULTA, OPERADOR |
  | `PADRON` | Personas | CONSULTA, OPERADOR |
  | `SERVICIOS` | Catálogo de servicios y provisión a áreas (también la pestaña de la ficha del área) | CONSULTA, OPERADOR |
  | `FIRMA_DIGITAL` | Firma digital | CONSULTA, OPERADOR |
  | `EXPEDIENTES` | Expedientes (sin cambios) | CONSULTA, OPERADOR, SUPERVISOR |
  | `EXPORTADOR` | Exportador de contactos | CONSULTA |

  La escala común permite autorizar una ruta compartida con una sola expresión (3.3).
- **[Propuesta]** `users.perfil_acceso` pasa a valer `ADMINISTRADOR` o `USUARIO` (P3). EDITOR y LECTOR dejan
  de tener significado: lo que expresaban pasa a los roles por módulo. La columna es `varchar(30)`: **no
  requiere migración de esquema**, solo de datos; el default de la columna se cambia a `USUARIO` con una
  migración nueva (D-08: no se editan las existentes).
- El Administrador **no** guarda filas en `user_modulos`: su acceso es implícito (como hoy). Si se lo degrada,
  queda sin módulos y el formulario obliga a asignarlos.
- Reglas de validación en `UsuarioController`:
  1. el par módulo/rol debe existir (ya implementado);
  2. un usuario `USUARIO` activo sin ningún módulo se permite pero se advierte en la UI («no podrá usar el
     sistema»);
  3. debe quedar **al menos un administrador activo**: al degradar o desactivar a un administrador, contar los
     administradores activos dentro de la transacción con `lockForUpdate()` y rechazar con 422 si quedaría
     cero (cierra I-07 sin depender de la autoprotección);
  4. `perfil_acceso` y `modulos` solo se escriben desde `/usuarios` (hoy ya es así; un test lo fija).

### 3.3 Control de acceso

**Backend (barrera real).**

- Se generaliza el middleware existente para aceptar **varios módulos alternativos**:
  `modulo:PADRON|FIRMA_DIGITAL,OPERADOR` = «Operador o más en PADRON **o** en FIRMA_DIGITAL». Cambio acotado en
  `CheckModuloAcceso` (separar por `|`) y en `User::puedeEnModulo`; las rutas actuales de Expedientes siguen
  funcionando sin cambios.
- Cada bloque de `routes/api.php` pasa de `perfil:` a `modulo:`. Las lecturas de catálogos de referencia se
  abren a los módulos que los necesitan (tabla 1.4):

  | Recurso | Lectura | Escritura | Baja |
  | :--- | :--- | :--- | :--- |
  | `/ministerios` | ORGANISMOS\|SERVICIOS\|PADRON\|FIRMA_DIGITAL\|EXPEDIENTES\|EXPORTADOR, CONSULTA | ORGANISMOS, OPERADOR | ADMINISTRADOR |
  | `/areas-ti`, `/autoridades-organismo`, `/asignaciones-rol` | ORGANISMOS\|SERVICIOS\|PADRON, CONSULTA | ORGANISMOS, OPERADOR | ADMINISTRADOR |
  | `/tipos-rol` | ORGANISMOS\|PADRON\|EXPORTADOR, CONSULTA | ADMINISTRADOR | ADMINISTRADOR |
  | `/personas` | PADRON\|ORGANISMOS\|SERVICIOS\|FIRMA_DIGITAL, CONSULTA | PADRON\|FIRMA_DIGITAL, OPERADOR (P5) | ADMINISTRADOR |
  | `/catalogo-servicios`, `/servicios-areas` | SERVICIOS\|ORGANISMOS, CONSULTA | SERVICIOS, OPERADOR | ADMINISTRADOR |
  | `/firma-digital/*` | FIRMA_DIGITAL, CONSULTA | FIRMA_DIGITAL, OPERADOR | ADMINISTRADOR |
  | `/expedientes/*` | sin cambios | sin cambios | sin cambios |
  | `/export/preview` | EXPORTADOR, CONSULTA | — | — |
  | `/documentos/upload` | — | control en el controlador según `documentable_type` (`persona` → PADRON; `servicio_area` → SERVICIOS; resto → ORGANISMOS) | ADMINISTRADOR |
  | `/usuarios` | ADMINISTRADOR | ADMINISTRADOR | (no hay) |
  | `/dashboard/stats` | cualquier usuario, pero cada bloque se calcula solo si tiene el módulo correspondiente | — | — |
- `perfil:ADMINISTRADOR` se conserva para bajas, catálogos de expedientes y usuarios. `perfil:ADMINISTRADOR,EDITOR`
  desaparece.
- **Test de cobertura de rutas** (nuevo): recorre `Route::getRoutes()` y falla si una ruta `api/*` no tiene
  `auth:sanctum` + (`perfil:` o `modulo:`), salvo una lista blanca explícita (login, `/auth/*`,
  `/dashboard/stats` y las dos públicas de SEC-01 mientras rija D-17). Evita que una ruta nueva quede abierta.
- Datos mínimos: `GET /personas` para módulos que solo *buscan* (FIRMA_DIGITAL, ORGANISMOS, SERVICIOS) podría
  devolver un subconjunto de campos. Se deja como mejora (sección 9) porque cambia el contrato de la API.

**Respuestas de denegación.** Se mantiene el formato actual: 401 si no hay sesión o el usuario está inactivo;
403 `{ success: false, message, modulo, rol_actual, rol_requerido }` si falta el permiso. Se reemplaza la
lista de perfiles en el 403 de `perfil:` por el mismo formato.

**Frontend (cosmético, pero coherente).**

- Un **registro único de módulos**: el backend lo expone (nombre, roles, descripción) en `GET /auth/user` junto
  con el rol efectivo, y se elimina la copia `ROLES_MODULO` de `stores/auth.ts` (I-06).
- Composable `useModulos()` con la tabla ruta → módulo y el menú generado desde ella (`layouts/default.vue`).
- Middleware global de Nuxt `middleware/permisos.global.ts`: sin token → `/login`; sin módulo para la ruta →
  redirige a `/` con `toast.error('No tiene acceso a ese módulo')`. Cada página declara su módulo con
  `definePageMeta({ modulo: 'FIRMA_DIGITAL' })`.
- Botones de alta y edición: `authStore.isEditor` → `puedeModulo('<MODULO>', 'OPERADOR')`.
- `useApi`: ante 403, toast con el `message` y `refrescarUsuario()` (el menú se corrige solo).
- Inicio (`pages/index.vue`): tarjetas solo de los módulos habilitados; si no tiene ninguno, aviso «Su usuario
  no tiene módulos asignados. Consulte al administrador.».

**Sesiones y revocación.** Plazo de revocación: **inmediato en el backend** (próximo request), porque los
permisos se leen de la base en cada request (1.3; se agrega el test). No hace falta invalidar tokens al
cambiar permisos. El frontend se corrige al recibir un 403, al navegar (refresco con un mínimo de 60 s entre
llamadas) o al recargar. Desactivar sigue borrando los tokens. La expiración de tokens (SEC-04) es una mejora
recomendada aparte.

### 3.4 Interfaz de gestión de usuarios (`pages/usuarios/index.vue`)

Se extiende la pantalla existente; no se crea otra.

- **Listado**: columna «Tipo» con insignia destacada para Administrador; columna «Módulos» con una insignia por
  módulo y rol (ya existe); filtro por módulo (`GET /usuarios?modulo=FIRMA_DIGITAL`) y por tipo; marca
  «Sin módulos» para usuarios activos que no pueden usar el sistema.
- **Formulario**: selector de tipo (Administrador / Usuario). Con Administrador, la sección de módulos se
  reemplaza por «Accede a todos los módulos»; con Usuario, una fila por módulo con `Sin acceso / Consulta /
  Operador / Supervisor` según los roles del módulo (generada desde el registro, ya funciona así para
  Expedientes). Confirmación (`confirmDialog`) al otorgar o quitar el tipo Administrador.
- **Perfil de acceso**: un panel de solo lectura dentro del mismo modal (o al expandir la fila) con lo que el
  usuario puede hacer en cada módulo y la fecha y autor del último cambio de permisos (de `audit_logs`).
- **Validaciones visibles**: los errores 422 del backend (último administrador, rol inexistente) con `toast`.

### 3.5 Seguridad y auditoría

| Riesgo | Medida |
| :--- | :--- |
| Mínimo privilegio | Alta con `USUARIO` y sin módulos (default de columna y formulario) |
| Elevación de privilegios | `/usuarios` solo ADMINISTRADOR; `perfil_acceso` y `modulos` no se aceptan en ningún otro endpoint (test); el frontend no decide nada |
| Quedar sin administración | Regla de «al menos un administrador activo» con bloqueo de fila (3.2); se mantiene la autoprotección |
| Revocación efectiva | Permisos leídos por request; test de revocación con sesión abierta |
| Accesos residuales | Tokens borrados al desactivar; caché del frontend corregida al 403. SEC-01 sigue abierto (P6) |
| Rutas olvidadas | Test de cobertura de rutas |
| Trazabilidad | Altas y cambios ya se auditan con antes/después. Se agrega `accion` específica `PERMISOS` cuando cambian `perfil_acceso` o `modulos` (para filtrarlos), y un detalle legible («FIRMA_DIGITAL: sin acceso → OPERADOR»). El registro de 403 queda como mejora (volumen) |

## 4. Impacto en el código

| Capa | Archivo | Cambio |
| :--- | :--- | :--- |
| BD | migración nueva `2026_xx_xx_alter_users_perfil_default` | default `USUARIO`; **no** toca `user_modulos` |
| BD/datos | comando nuevo `app/Console/Commands/MigrarPermisosModulo.php` | migración de usuarios existentes con `--simular` / `--aplicar` / `--revertir` (sección 6) |
| Modelo | `app/Models/UserModulo.php` | catálogo de módulos y roles; nombres y descripciones para la UI |
| Modelo | `app/Models/User.php` | `puedeEnModulo` con alternativas; `esEditor`/`esLector` se retiran o se reescriben |
| Middleware | `CheckModuloAcceso.php` | módulos alternativos con `\|`; `CheckPerfilAcceso.php`: formato de 403 |
| Rutas | `routes/api.php` | todos los bloques de gobernanza, firma y exportación (≈ 60 rutas) |
| Controladores | `UsuarioController` | perfiles, último administrador, filtros, acción `PERMISOS` |
| | `AuthController` | registro de módulos en `/auth/user` y login; quitar la *ability* sin uso |
| | `DashboardController` | bloques condicionados por módulo |
| | `DocumentoAdjuntoController@upload` | permiso según `documentable_type` |
| | `ExpedienteController` (l. 232, integrantes) | sin cambios funcionales; revisar que siga excluyendo a quien no tiene el módulo |
| Seeders | `UserSeeder` | usuarios demo por escenario (admin, firma, firma+expedientes, organismos) |
| Frontend | `stores/auth.ts` | quitar `ROLES_MODULO`, `isEditor`/`isLector`; registro desde la API |
| | `composables/useModulos.ts` (nuevo), `middleware/permisos.global.ts` (nuevo) | registro y guarda de rutas |
| | `composables/useApi.ts` | manejo de 403 |
| | `layouts/default.vue` | menú desde el registro |
| | `pages/index.vue` | inicio por módulo |
| | todas las páginas con `isEditor`/`isAdmin` (`ministerios`, `areas-ti/*`, `personas`, `roles`, `servicios`, `firma-digital`, componentes de `areas-ti` y `firma-digital`) | `puedeModulo(...)` y `definePageMeta({ modulo })` |
| | `pages/usuarios/index.vue` | sección 3.4 |
| Tests | `tests/Feature/Permisos/*` (nuevos); ajustar `UsuariosTest`, `PermisosModuloTest` y los helpers que crean usuarios EDITOR/LECTOR (`FirmaDigitalTestHelpers`, `ExpedientesTestHelpers`, tests de áreas y ministerios) | sección 8 |
| Docs | `docs/decisions.md` (nueva D-34 que amplía D-26), `architecture.md`, `api.md`, `security.md`, `product.md`, `AGENTS.md` (convención de permisos), `README.md` (perfiles) | — |

## 5. Plan de implementación

Imprescindible: etapas 1–7. Mejoras posteriores: sección 9.

| # | Etapa | Objetivo y cambios | Depende de | Riesgos y mitigación | Pruebas | Aceptación |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Diagnóstico | Este documento | — | — | — | Hecho |
| 2 | Diseño | Respuestas a P1–P9; registrar D-34 | 1 | Granularidad mal elegida → se valida con la tabla 1.4 | — | Decisiones registradas |
| 3 | Base de datos | Migración del default de `perfil_acceso`; comando de migración de permisos (sin ejecutar) | 2 | Ninguna estructura nueva; riesgo bajo | `migrate` en `erp_dpsit_test`; tests del comando | `migrate:status` limpio; `--simular` lista el plan |
| 4 | Backend | Catálogo de módulos, middleware con alternativas, rutas, controladores, auditoría `PERMISOS`, último administrador | 3 | Romper el acceso de pantallas que leen catálogos de otro módulo → tabla 3.3 y tests por pantalla; ruta sin proteger → test de cobertura | Tests de 8 | Matriz de acceso verde; test de cobertura verde; los 70 tests actuales adaptados y verdes |
| 5 | Frontend | Registro de módulos, guarda de rutas, menú, inicio, botones, 403, pantalla de usuarios | 4 (contrato de `/auth/user`) | Botones visibles sin permiso → solo cosmético; se revisa con cada usuario demo | `nuxi build` (compilación) + recorrido manual con 4 usuarios demo | Cada usuario demo ve solo sus módulos; URL directa redirige |
| 6 | Migración | Ejecutar el comando en desarrollo con autorización | 4, 5, P4 | Usuarios sin acceso o con acceso de más → simulación revisada por el responsable antes de aplicar; respaldo previo | `--simular`, revisión, `--aplicar`, verificación | Cada usuario tiene exactamente lo aprobado; al menos un administrador activo |
| 7 | Pruebas | Funcionales, seguridad, regresión (sección 8) | 6 | Falsa sensación de cobertura: el resto del sistema no tenía tests → se agregan tests de acceso por módulo, no de lógica | `php artisan test`; sondeos HTTP con curl | Todos los escenarios de 8 verdes |
| 8 | Despliegue | **No hay producción** (D-14). Merge a `develop` con autorización; para un futuro despliegue, el procedimiento de 6.4 | 7 | — | Repetir 7 en el entorno destino | — |

## 6. Plan de migración

### 6.1 Esquema

Solo una migración nueva (default de `perfil_acceso` = `USUARIO`). `user_modulos` ya admite cualquier
módulo y rol. Reversible con `down()` (default `LECTOR`). No se ejecuta `migrate:fresh` ni se editan
migraciones existentes.

### 6.2 Usuarios existentes (P4)

Comando `php artisan usuarios:migrar-permisos` con tres modos:

- `--simular` (por defecto): muestra, por id de usuario (sin emails ni nombres en la salida), el perfil actual,
  el perfil nuevo y los módulos que recibiría. No escribe.
- `--aplicar`: en una transacción, guarda una instantánea (`storage/app/migraciones/permisos-<fecha>.json`, solo
  ids, perfiles y módulos), aplica el plan y audita cada usuario con acción `PERMISOS` y detalle «Migración
  inicial D-34». Se niega si el resultado deja cero administradores activos.
- `--revertir=<archivo>`: restaura perfiles y módulos desde la instantánea.

Estrategias posibles para EDITOR y LECTOR (decisión P4):

| Opción | EDITOR | LECTOR | Efecto |
| :--- | :--- | :--- | :--- |
| **A. Equivalencia (recomendada)** | USUARIO + OPERADOR en ORGANISMOS, PADRON, SERVICIOS, FIRMA_DIGITAL; CONSULTA en EXPORTADOR | USUARIO + CONSULTA en esos 5 módulos | Nadie gana ni pierde acceso el día del cambio; luego el administrador recorta. Los roles de EXPEDIENTES se conservan tal cual |
| B. Mínimo privilegio estricto | USUARIO sin módulos | USUARIO sin módulos | Nadie usa el sistema hasta que el administrador asigna; seguro pero interrumpe |
| C. Lista explícita | según una planilla aprobada por el responsable | ídem | La más precisa; requiere preparar la lista |

Con los datos actuales (3 usuarios demo, sin producción) cualquier opción es de bajo impacto; la A se recomienda
porque es la que valida que el nuevo modelo reproduce el comportamiento anterior. Los ADMINISTRADOR se
conservan sin cambios.

### 6.3 Compatibilidad

- Las rutas de Expedientes y sus tests no cambian.
- Entre el despliegue del backend y la migración de datos, un EDITOR o LECTOR sin módulos perdería acceso: por
  eso el comando se ejecuta **inmediatamente después** del `migrate`, en la misma ventana.
- Los tokens existentes siguen siendo válidos; el usuario ve el menú nuevo al recargar o al primer 403.

### 6.4 Respaldo y reversión

1. Antes: `pg_dump` de la base (hoy **no hay** un procedimiento documentado en `docs/development.md`: se
   agrega en esta tarea) y verificar `--simular`.
2. Problema en los datos: `usuarios:migrar-permisos --revertir=<instantánea>`.
3. Problema en el código: revertir el merge de la rama (los datos con `USUARIO` y los módulos nuevos no rompen
   el código anterior **salvo** que `perfil:ADMINISTRADOR,EDITOR` rechazaría a los `USUARIO`: por eso el paso 2
   va antes que el 3).
4. Último recurso: restaurar el `pg_dump` (requiere autorización).

## 7. Estimación de esfuerzo

Supuestos: una persona con asistencia de agente, sin producción, respuestas a P1–P9 sin cambios de
granularidad, con el criterio de validación vigente (tests de feature + build + revisión manual).

| Etapa | Complejidad | Esfuerzo |
| :--- | :--- | :--- |
| 2 Diseño (decisiones) | Baja | 0,5 día (del responsable) |
| 3 Base de datos + comando | Baja | 0,5 día |
| 4 Backend | Media (≈ 60 rutas, 5 controladores) | 2 días |
| 5 Frontend | Media (≈ 12 páginas y componentes, pantalla de usuarios) | 2–2,5 días |
| 6 Migración | Baja | 0,25 día |
| 7 Pruebas (≈ 25 tests nuevos + ajuste de helpers) | Media | 1,5–2 días |
| 8 Documentación y cierre | Baja | 0,5 día |
| **Total** | | **≈ 7–8 días** |

Incertidumbres: la granularidad final (P1; separar más módulos multiplica la tabla 3.3 y los tests); si se
corrige SEC-01 en la misma tarea (P6, +1 día con cambios de frontend en descargas); si se exige recortar
campos de `/personas` por módulo (+0,5–1 día).

## 8. Plan de pruebas

Tests de feature (PHPUnit, base `erp_dpsit_test`), en `tests/Feature/Permisos/`:

| # | Escenario | Verificación |
| :--- | :--- | :--- |
| T1 | Usuario solo FIRMA_DIGITAL:OPERADOR | 200 en `GET/POST /firma-digital/entregas` y `GET /personas`; 403 en `GET /expedientes`, `POST /ministerios`, `GET /servicios-areas`, `POST /export/preview` |
| T2 | FIRMA_DIGITAL + EXPEDIENTES | 200 en ambos módulos; 403 en ORGANISMOS (escritura) y EXPORTADOR |
| T3 | ORGANISMOS:OPERADOR | 200 en ministerios, áreas, autoridades y asignaciones; 403 en firma y expedientes |
| T4 | CONSULTA en un módulo | lee, pero escritura 403 |
| T5 | Matriz completa | data provider módulo × rol × endpoint representativo |
| T6 | Usuario sin módulos | 403 en todo salvo `/auth/*` y `/dashboard/stats` (vacío) |
| T7 | Administrador | 200 en todos los módulos y en `/usuarios` |
| T8 | Elevación | un USUARIO recibe 403 en `PUT /usuarios/{propio id}` y de terceros; ningún otro endpoint acepta `perfil_acceso`/`modulos` |
| T9 | Revocación con sesión abierta | mismo token: 200 → se quita el módulo → 403 en el request siguiente |
| T10 | Desactivación | tokens borrados; 401 |
| T11 | Último administrador | 422 al degradar o desactivar al único administrador activo, incluso desde otro administrador ya degradado |
| T12 | Alta | default `USUARIO` sin módulos; rol inexistente → 422; administrador sin filas en `user_modulos` |
| T13 | Auditoría | cambio de módulos genera `PERMISOS` con autor, antes y después |
| T14 | Migración | `--simular` no escribe; `--aplicar` produce el plan A; `--revertir` restaura; rechaza dejar cero administradores |
| T15 | Cobertura de rutas | toda ruta `api/*` protegida salvo la lista blanca |
| T16 | Dashboard | solo bloques de los módulos habilitados |
| T17 | Adjuntos | `upload` con `documentable_type=persona` exige PADRON:OPERADOR |

Regresión: los 70 tests actuales (con los helpers adaptados a roles por módulo). Manual (frontend, sin tests
automatizados): login con los 4 usuarios demo; menú; URL directa a un módulo ajeno (redirige con aviso);
botones de alta ocultos con CONSULTA; quitar un módulo con la sesión abierta y verificar que el siguiente clic
da aviso y el menú se corrige. Elusión: llamadas con curl y el token de un usuario limitado a endpoints de otros
módulos; editar `dpsit_user` en `localStorage` muestra el menú pero el backend devuelve 403.

## 9. Recomendaciones adicionales (posteriores)

1. Resolver SEC-01 (descargas con `fetch` + `Blob` dentro de `auth:sanctum` y con permiso de módulo): sin eso, el
   permiso de EXPORTADOR y de adjuntos es eludible.
2. Expiración de tokens y `throttle` en el login (SEC-04).
3. Pantalla de consulta de la bitácora, con filtro por acción `PERMISOS`.
4. Plantillas de acceso (alternativa D) si crece la cantidad de usuarios.
5. Respuesta reducida de `/personas` para módulos que solo buscan (minimización de datos personales).
6. Registro de accesos denegados con muestreo, para detectar intentos.
7. Un usuario por persona del padrón (`persona_id`) obligatorio, para trazabilidad.

## 10. Decisiones pendientes para el responsable

| ID | Pregunta | Recomendación | Impacto si cambia |
| :--- | :--- | :--- | :--- |
| P1 | ¿Granularidad de módulos? | Los 6 de 3.2 (ORGANISMOS incluye Roles y vigencias) | Más módulos = más filas en la tabla 3.3 y más tests |
| P2 | ¿Escala de roles común CONSULTA < OPERADOR < SUPERVISOR? | Sí | Con nombres distintos por módulo, las rutas compartidas necesitan otra sintaxis |
| P3 | ¿El perfil global queda en ADMINISTRADOR / USUARIO? | Sí | Mantener EDITOR/LECTOR como «techo» crea dos ejes que se contradicen |
| P4 | ¿Cómo se migran EDITOR y LECTOR? | Opción A, revisada con `--simular` | B interrumpe el uso; C requiere una lista |
| P5 | ¿Un operador de firma digital puede dar de alta personas en el padrón? | Sí (hoy el alta de titular lo necesita) | Si no, el alta de titular la hace alguien con PADRON |
| P6 | ¿Se corrige SEC-01 en la misma tarea? (hoy D-17 lo prohíbe) | Sí, al menos el CSV del exportador | Sin corrección, el permiso por módulo tiene dos rutas eludibles documentadas |
| P7 | ¿Qué ve en Inicio un usuario con un solo módulo? | Solo sus tarjetas | — |
| P8 | ¿Se fija expiración de tokens en esta tarea? | No; aparte (SEC-04) | — |
| P9 | ¿Las bajas siguen siendo solo del Administrador, o del SUPERVISOR del módulo? | Solo Administrador (convención actual) | Si cambia, SUPERVISOR se agrega a más módulos |

## 11. Implementación: diferencias con el diseño

- **Sin comando de migración de permisos** (6.2): como los usuarios eran de prueba (P4), la conversión EDITOR /
  LECTOR → USUARIO va en la migración `2026_10_27_000001` (autocontenida, con `down()` que la revierte; test en
  `MigracionPermisosTest`). Para producción conviene volver a evaluar una lista explícita (opción C).
- **Registro de rutas del frontend centralizado**: en lugar de `definePageMeta({ modulo })` en cada página, una
  tabla de prefijos en `composables/useModulos.ts` (un solo lugar para módulos nuevos; la usan el menú, el
  middleware y los accesos del inicio).
- **Personas** (P5): `POST`, `PUT` y `DELETE /personas` con `modulo:PADRON|FIRMA_DIGITAL,OPERADOR`. El
  administrador conserva la regla de borrado anterior; el operador solo borra si no hay ningún vínculo.
- **Auditoría**: acción `PERMISOS` para cambios de perfil, estado o módulos (con un detalle legible por módulo);
  `UPDATE` solo si cambian nombre, email o contraseña. `GET /usuarios` devuelve el último cambio de permisos de
  cada usuario (fecha y autor) desde la bitácora.
- **Último administrador**: el recuento con bloqueo usa `lockForUpdate()->pluck('id')`, porque PostgreSQL no
  admite `FOR UPDATE` con `count()` (lo detectó el test).
- El nombre visible de ORGANISMOS es «Organismos y roles» (incluye ministerios, áreas TI, autoridades, roles y
  vigencias).

Validación: 93 tests verdes (70 previos adaptados + 23 nuevos en `tests/Feature/Permisos`); sondeos HTTP con los
usuarios demo; recorrido en el navegador con el usuario de solo firma digital (menú, inicio, redirección por URL
directa) y con el administrador (pantalla de usuarios).

**Pendiente**: recorrido manual completo de todas las pantallas con cada combinación de módulos (solo se
probaron firma-solo y administrador); los botones ocultos por módulo de las demás pantallas se revisaron por
lectura de código.
