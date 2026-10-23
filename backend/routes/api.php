<?php

use App\Http\Controllers\Api\AreaTiController;
use App\Http\Controllers\Api\AsignacionRolController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoServicioController;
use App\Http\Controllers\Api\CertificadoFirmaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DispositivoFirmaController;
use App\Http\Controllers\Api\DocumentoAdjuntoController;
use App\Http\Controllers\Api\EntregaTokenController;
use App\Http\Controllers\Api\ExpedienteCatalogoController;
use App\Http\Controllers\Api\ExpedienteController;
use App\Http\Controllers\Api\ExpedienteIntervencionController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MinisterioController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\ServicioAreaController;
use App\Http\Controllers\Api\TipoRolController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - ERP DPSIT
|--------------------------------------------------------------------------
*/

// Rutas Públicas
Route::post('/auth/login', [AuthController::class, 'login']);

// Descarga de archivos (requiere token por query o header auth)
Route::get('/documentos/{id}/descargar', [DocumentoAdjuntoController::class, 'download']);
Route::get('/export/download', [ExportController::class, 'downloadCsv']);

// Rutas Protegidas por Autenticación Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Sesión de Usuario
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard & Estadísticas (Todos los perfiles autenticados)
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Módulo de Exportación y Consultas (Lector, Editor, Administrador)
    Route::post('/export/preview', [ExportController::class, 'preview']);

    // =========================================================================
    // LECTURA DE CATÁLOGOS Y REGISTROS (Lector, Editor, Administrador)
    // =========================================================================
    Route::get('/ministerios', [MinisterioController::class, 'index']);
    Route::get('/ministerios/{id}', [MinisterioController::class, 'show']);

    Route::get('/areas-ti', [AreaTiController::class, 'index']);
    Route::get('/areas-ti/{id}', [AreaTiController::class, 'show']);

    Route::get('/personas', [PersonaController::class, 'index']);
    Route::get('/personas/{id}', [PersonaController::class, 'show']);

    Route::get('/tipos-rol', [TipoRolController::class, 'index']);
    Route::get('/tipos-rol/{id}', [TipoRolController::class, 'show']);

    Route::get('/asignaciones-rol', [AsignacionRolController::class, 'index']);
    Route::get('/asignaciones-rol/{id}', [AsignacionRolController::class, 'show']);

    Route::get('/catalogo-servicios', [CatalogoServicioController::class, 'index']);
    Route::get('/catalogo-servicios/{id}', [CatalogoServicioController::class, 'show']);

    Route::get('/servicios-areas', [ServicioAreaController::class, 'index']);
    Route::get('/servicios-areas/{id}', [ServicioAreaController::class, 'show']);

    // Firma digital: entregas de tokens, dispositivos y seguimiento de certificados
    Route::get('/firma-digital/parametros', [CertificadoFirmaController::class, 'parametros']);
    Route::get('/firma-digital/entregas', [EntregaTokenController::class, 'index']);
    Route::get('/firma-digital/entregas/{id}', [EntregaTokenController::class, 'show']);
    Route::get('/firma-digital/certificados', [CertificadoFirmaController::class, 'index']);
    Route::get('/firma-digital/dispositivos', [DispositivoFirmaController::class, 'index']);
    Route::get('/firma-digital/dispositivos/{id}', [DispositivoFirmaController::class, 'show']);

    // =========================================================================
    // EXPEDIENTES: permiso por módulo (D-26), independiente del perfil global
    // =========================================================================
    Route::prefix('expedientes')->group(function () {
        Route::middleware('modulo:EXPEDIENTES,CONSULTA')->group(function () {
            Route::get('/', [ExpedienteController::class, 'index']);
            Route::get('/tablero', [ExpedienteController::class, 'tablero']);
            Route::get('/opciones', [ExpedienteController::class, 'opciones']);
            Route::get('/buscar-numero', [ExpedienteController::class, 'buscarNumero']);
            Route::get('/exportar', [ExpedienteController::class, 'exportar']);
            Route::get('/{id}', [ExpedienteController::class, 'show'])->whereNumber('id');
        });

        Route::middleware('modulo:EXPEDIENTES,OPERADOR')->group(function () {
            Route::post('/', [ExpedienteController::class, 'store']);
            Route::put('/intervenciones/{id}', [ExpedienteIntervencionController::class, 'update']);
            Route::post('/intervenciones/{id}/asignar', [ExpedienteIntervencionController::class, 'asignar']);
            Route::post('/intervenciones/{id}/estado', [ExpedienteIntervencionController::class, 'cambiarEstado']);
            Route::post('/intervenciones/{id}/revision', [ExpedienteIntervencionController::class, 'enviarRevision']);
            Route::post('/intervenciones/{id}/firma', [ExpedienteIntervencionController::class, 'firma']);
            Route::post('/intervenciones/{id}/egreso', [ExpedienteIntervencionController::class, 'egresar']);
            Route::post('/intervenciones/{id}/notas', [ExpedienteIntervencionController::class, 'nota']);
        });

        Route::middleware('modulo:EXPEDIENTES,SUPERVISOR')->group(function () {
            Route::post('/intervenciones/{id}/reabrir', [ExpedienteIntervencionController::class, 'reabrir']);
            Route::post('/intervenciones/{id}/corregir-fechas', [ExpedienteIntervencionController::class, 'corregirFechas']);
        });

        Route::middleware('perfil:ADMINISTRADOR')->group(function () {
            Route::post('/catalogos/{catalogo}', [ExpedienteCatalogoController::class, 'store']);
            Route::put('/catalogos/{catalogo}/{id}', [ExpedienteCatalogoController::class, 'update']);
            Route::delete('/{id}', [ExpedienteController::class, 'destroy'])->whereNumber('id');
        });
    });

    // =========================================================================
    // OPERACIONES DE ESCRITURA / MODIFICACIÓN (Editor, Administrador)
    // =========================================================================
    Route::middleware('perfil:ADMINISTRADOR,EDITOR')->group(function () {
        
        // Ministerios
        Route::post('/ministerios', [MinisterioController::class, 'store']);
        Route::put('/ministerios/{id}', [MinisterioController::class, 'update']);

        // Áreas TI
        Route::post('/areas-ti', [AreaTiController::class, 'store']);
        Route::put('/areas-ti/{id}', [AreaTiController::class, 'update']);
        Route::post('/areas-ti/{id}/maxima-autoridad', [AreaTiController::class, 'designarAutoridad']);

        // Personas
        Route::post('/personas', [PersonaController::class, 'store']);
        Route::put('/personas/{id}', [PersonaController::class, 'update']);

        // Asignación de Roles & Vigencias
        Route::post('/asignaciones-rol', [AsignacionRolController::class, 'store']);
        Route::put('/asignaciones-rol/{id}', [AsignacionRolController::class, 'update']);
        Route::post('/asignaciones-rol/{id}/finalizar-vigencia', [AsignacionRolController::class, 'finalizarVigencia']);

        // Catálogo de Servicios
        Route::post('/catalogo-servicios', [CatalogoServicioController::class, 'store']);
        Route::put('/catalogo-servicios/{id}', [CatalogoServicioController::class, 'update']);

        // Provisión de Servicios a Áreas TI
        Route::post('/servicios-areas', [ServicioAreaController::class, 'store']);
        Route::put('/servicios-areas/{id}', [ServicioAreaController::class, 'update']);
        Route::post('/servicios-areas/{id}/referentes', [ServicioAreaController::class, 'syncReferente']);
        Route::delete('/servicios-areas/{id}/referentes/{referenteId}', [ServicioAreaController::class, 'removeReferente']);

        // Documentos Adjuntos
        Route::post('/documentos/upload', [DocumentoAdjuntoController::class, 'upload']);

        // Firma digital
        Route::post('/firma-digital/entregas', [EntregaTokenController::class, 'store']);
        Route::put('/firma-digital/entregas/{id}', [EntregaTokenController::class, 'update']);
        Route::post('/firma-digital/entregas/{id}/finalizar', [EntregaTokenController::class, 'finalizar']);
        Route::post('/firma-digital/entregas/{id}/certificados', [CertificadoFirmaController::class, 'store']);
        Route::put('/firma-digital/certificados/{id}', [CertificadoFirmaController::class, 'update']);
        Route::post('/firma-digital/certificados/{id}/reprogramar-recordatorio', [CertificadoFirmaController::class, 'reprogramarRecordatorio']);
        Route::post('/firma-digital/dispositivos', [DispositivoFirmaController::class, 'store']);
        Route::put('/firma-digital/dispositivos/{id}', [DispositivoFirmaController::class, 'update']);
    });

    // =========================================================================
    // OPERACIONES CRÍTICAS DE ELIMINACIÓN Y GESTIÓN (Solo Administrador)
    // =========================================================================
    Route::middleware('perfil:ADMINISTRADOR')->group(function () {
        Route::delete('/ministerios/{id}', [MinisterioController::class, 'destroy']);
        Route::delete('/areas-ti/{id}', [AreaTiController::class, 'destroy']);
        Route::delete('/personas/{id}', [PersonaController::class, 'destroy']);
        Route::post('/tipos-rol', [TipoRolController::class, 'store']);
        Route::put('/tipos-rol/{id}', [TipoRolController::class, 'update']);
        Route::delete('/tipos-rol/{id}', [TipoRolController::class, 'destroy']);
        Route::delete('/asignaciones-rol/{id}', [AsignacionRolController::class, 'destroy']);
        Route::delete('/catalogo-servicios/{id}', [CatalogoServicioController::class, 'destroy']);
        Route::delete('/servicios-areas/{id}', [ServicioAreaController::class, 'destroy']);
        Route::delete('/documentos/{id}', [DocumentoAdjuntoController::class, 'destroy']);
        Route::delete('/firma-digital/entregas/{id}', [EntregaTokenController::class, 'destroy']);
        Route::delete('/firma-digital/dispositivos/{id}', [DispositivoFirmaController::class, 'destroy']);

        // Administración de usuarios (D-27)
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::post('/usuarios', [UsuarioController::class, 'store']);
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    });
});
