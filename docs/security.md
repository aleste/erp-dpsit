# Seguridad

Alcance: **revisión preliminar** de código y configuración, más dos sondeos HTTP no destructivos contra
el entorno local (2026-10-23). **No** es una auditoría de seguridad completa, ni un pentest, ni un
análisis dinámico exhaustivo. No se reproducen valores secretos: se indica dónde están y de qué tipo son.

## Hallazgos

| ID | Severidad | Hallazgo | Evidencia | Recomendación |
| :--- | :--- | :--- | :--- | :--- |
| ~~SEC-01~~ | **Resuelto 2026-10-27** (D-36) | `GET /api/documentos/{id}/descargar` y `GET /api/export/download` están fuera de `auth:sanctum` y los controladores no verifican identidad. Cualquiera que alcance el puerto 8000 puede descargar cualquier adjunto (IDs secuenciales) y exportar en CSV el padrón con CUIL, emails y teléfonos | `routes/api.php` (bloque «Rutas Públicas»). **Comprobado:** sin token, la descarga de un ID inexistente devuelve 404 en lugar de 401. La exportación no se invocó para no generar escrituras en la auditoría | Moverlas dentro de `auth:sanctum` (exportación: cualquier perfil autenticado; documentos: según política) y cambiar el frontend para descargar con `fetch` + `Authorization` y un `Blob` (o URLs firmadas temporales). **Resuelto el 2026-10-27 (D-36):** ambas rutas dentro de `auth:sanctum` con permiso de módulo (EXPORTADOR; el del documento según su entidad) y descarga con token desde el frontend. Comprobado: sin token, 401; con un usuario sin el módulo, 403 (`CoberturaRutasTest`, `AccesoPorModuloTest` y sondeos HTTP) |
| SEC-02 | Media (crítica si llega a producción) | `APP_KEY` fija y versionada: el mismo valor está en `.env.example`, como valor por defecto en `docker-compose.yml` y en `wslc-setup.sh`. El `.env` local es idéntico a `.env.example`, así que la clave en uso es pública | comparación de valores sin imprimirlos | Quitarla de los archivos versionados; generar una por entorno (`php artisan key:generate`) |
| SEC-03 | Media (desarrollo) | Contraseña de PostgreSQL en texto plano en `wslc-setup.ps1`, `wslc-setup.sh`, el valor por defecto de `docker-compose.yml` y `README.md` | — | Leerla de `.env`; no documentarla |
| SEC-04 | Media | Login sin límite de intentos; tokens Sanctum sin expiración (`config/sanctum.php` `expiration: null`); el token se guarda en `localStorage` (expuesto ante cualquier XSS) | `AuthController`, `stores/auth.ts` | Aplicar `throttle` a `/auth/login`, fijar una expiración y evaluar cookies `HttpOnly` (modo SPA de Sanctum) |
| SEC-05 | Media | Credenciales demo triviales (patrón `<perfil>` + `123`) en `UserSeeder`, `README.md`, `setup.sh` y `wslc-setup.ps1`. `DatabaseSeeder` corre en cualquier entorno | — | Seeder de usuarios solo en `local`; contraseña inicial por variable de entorno |
| SEC-06 | Baja (dev) / Alta (prod) | `APP_DEBUG=true` y `display_errors=On`: las respuestas de error incluyen traza, rutas internas y clases | **Comprobado** en el 404 de SEC-01 | Configuración de producción separada |
| SEC-07 | Media | Subida de archivos: sin lista blanca de MIME/extensión (solo `file\|max:20480`); `documentable_id` no se valida contra la entidad; el borrado es lógico y el archivo queda en disco | `DocumentoAdjuntoController@upload` | Validar `mimes:` y la existencia de la entidad; definir la retención |
| SEC-08 | Baja | `per_page` sin tope en `personas`, `asignaciones-rol` y `servicios-areas`: un usuario autenticado puede volcar todo de una vez | controladores | Limitar, por ejemplo con `min((int)$per_page, 100)` |
| SEC-09 | Baja | PHP-FPM corre como root (`php-fpm -R`) por compatibilidad con NTFS | `docker/php/Dockerfile` | Aceptable solo en desarrollo |
| SEC-10 | Media | Dependencias con avisos. `composer audit`: `laravel/framework` (4, incluido «high»), `league/commonmark` (2), `league/flysystem` (1), `symfony/http-foundation` (1). `npm audit --omit=dev`: 25 (3 críticos, 20 altos) | ejecutado el 2026-10-23 | Revisión dirigida y actualización autorizada; **no** se actualizó nada |
| SEC-11 | Info | Datos personales en seeders (CUIL, emails, teléfonos). **El responsable confirmó que son ficticios** (2026-10-23) | `PersonaSeeder` | Mantenerlos ficticios; nunca cargar datos reales en seeders |
| SEC-13 | Media | El módulo de firma digital agrega datos personales (tipo y número de documento) y, en producción, enviará correos a personas reales. En desarrollo Mailpit captura todo; el riesgo aparece si alguien configura un SMTP real en el `.env` de desarrollo | `config/firma_digital.php`, `.env` | Mantener `MAIL_HOST=mailpit` en desarrollo; en producción, remitente institucional y SMTP con TLS. Los correos no incluyen documento ni CUIL |
| SEC-14 | Media | El módulo de expedientes guarda datos personales de agentes en carátulas y observaciones (vienen de la planilla). Acceso restringido por rol de módulo; la exportación CSV exige token. Los archivos de importación con datos reales quedan en `storage/app/importaciones/` | `.gitignore`, rutas `modulo:` | No copiar esos archivos fuera del entorno; borrarlos tras validar la importación |
| SEC-12 | Info | Las transcripciones del agente anterior en `~/.gemini/` (fuera del repo) contienen credenciales demo y salidas de consola | — | No versionarlas ni compartirlas |

Aspectos revisados sin hallazgos relevantes:

- consultas mediante Eloquent y bindings, sin SQL armado con entradas;
- `DB::raw` solo con literales;
- sin `v-html` en el frontend;
- CORS restringido a `localhost:3000` y `FRONTEND_URL`;
- adjuntos en disco privado con hash SHA-256;
- auditoría de escrituras, descargas y exportaciones (con IP y user agent);
- módulo de firma digital: todas sus rutas dentro de `auth:sanctum` con perfiles, `per_page` acotado a 100,
  validación de enumerados y fechas, y auditoría de altas, cambios, envíos y errores de recordatorios.

## Controles obligatorios para cualquier cambio

1. Toda ruta nueva va dentro de `auth:sanctum` y con el `modulo:` o `perfil:` que corresponda (D-34); el
   test `CoberturaRutasTest` lo verifica. No agregar rutas públicas.
2. Validar todas las entradas en el controlador (tipos, `exists:`, enumerados con `in:`, tamaños).
3. Registrar las escrituras con `AuditService::log` sin incluir contraseñas ni tokens.
4. No exponer datos personales en logs, mensajes de error, ejemplos ni documentación.
5. No versionar `.env`, claves, dumps de base de datos ni archivos de `storage/app/documents`.
6. Antes de cerrar una tarea que toque autenticación, archivos o exportación, aplicar la skill
   `validar-cambios` y revisar esta tabla.

## Antes de cualquier despliegue productivo (pendiente)

Resolver SEC-02 a SEC-07. Además hace falta:

- `APP_ENV=production` y `APP_DEBUG=false`;
- HTTPS;
- build de producción de Nuxt (hoy solo existe `nuxi dev`);
- no ejecutar `migrate:fresh`;
- backups de `postgres_data` y `storage/app/documents`.
