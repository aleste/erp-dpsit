<?php

use App\Http\Controllers\Api\AreaTiController;
use App\Http\Controllers\Api\AsignacionRolController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutoridadOrganismoController;
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

// Única ruta pública: el login. Las descargas que eran públicas (SEC-01) se movieron dentro de auth:sanctum.
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas Protegidas por Autenticación Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Sesión de Usuario
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Panel de inicio: cada bloque se calcula solo para los módulos habilitados del usuario
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // =========================================================================
    // PERMISOS POR MÓDULO (D-26, D-34). Escala común CONSULTA < OPERADOR < SUPERVISOR; con «|» basta
    // tener el rol en uno de los módulos. Los catálogos de referencia se leen desde los módulos que
    // los necesitan. Las bajas quedan para el ADMINISTRADOR (bloque final), salvo personas (D-35).
    // =========================================================================

    // Organismos: ministerios, autoridades, áreas TI, tipos de rol y asignaciones
    Route::middleware('modulo:ORGANISMOS|SERVICIOS|PADRON|FIRMA_DIGITAL|EXPEDIENTES|EXPORTADOR,CONSULTA')->group(function () {
        Route::get('/ministerios', [MinisterioController::class, 'index']);
        Route::get('/ministerios/{id}', [MinisterioController::class, 'show']);
    });

    Route::middleware('modulo:ORGANISMOS|SERVICIOS|PADRON,CONSULTA')->group(function () {
        Route::get('/areas-ti', [AreaTiController::class, 'index']);
        Route::get('/areas-ti/{id}', [AreaTiController::class, 'show']);
    });

    Route::middleware('modulo:ORGANISMOS|PADRON|EXPORTADOR,CONSULTA')->group(function () {
        Route::get('/tipos-rol', [TipoRolController::class, 'index']);
        Route::get('/tipos-rol/{id}', [TipoRolController::class, 'show']);
    });

    Route::middleware('modulo:ORGANISMOS|PADRON,CONSULTA')->group(function () {
        Route::get('/asignaciones-rol', [AsignacionRolController::class, 'index']);
        Route::get('/asignaciones-rol/{id}', [AsignacionRolController::class, 'show']);
    });

    Route::middleware('modulo:ORGANISMOS,OPERADOR')->group(function () {
        Route::post('/ministerios', [MinisterioController::class, 'store']);
        Route::put('/ministerios/{id}', [MinisterioController::class, 'update']);
        Route::post('/ministerios/{id}/autoridad', [AutoridadOrganismoController::class, 'designar']);
        Route::put('/autoridades-organismo/{id}', [AutoridadOrganismoController::class, 'update']);

        Route::post('/areas-ti', [AreaTiController::class, 'store']);
        Route::put('/areas-ti/{id}', [AreaTiController::class, 'update']);
        Route::post('/areas-ti/{id}/maxima-autoridad', [AreaTiController::class, 'designarAutoridad']);

        Route::post('/asignaciones-rol', [AsignacionRolController::class, 'store']);
        Route::put('/asignaciones-rol/{id}', [AsignacionRolController::class, 'update']);
        Route::post('/asignaciones-rol/{id}/finalizar-vigencia', [AsignacionRolController::class, 'finalizarVigencia']);
    });

    // Padrón de personas: lo consultan los módulos que eligen personas; lo modifican el padrón y firma
    // digital (alta de titulares). Un operador solo borra personas sin vínculos (D-35).
    Route::middleware('modulo:PADRON|ORGANISMOS|SERVICIOS|FIRMA_DIGITAL,CONSULTA')->group(function () {
        Route::get('/personas', [PersonaController::class, 'index']);
        Route::get('/personas/{id}', [PersonaController::class, 'show']);
    });

    Route::middleware('modulo:PADRON|FIRMA_DIGITAL,OPERADOR')->group(function () {
        Route::post('/personas', [PersonaController::class, 'store']);
        Route::put('/personas/{id}', [PersonaController::class, 'update']);
        Route::delete('/personas/{id}', [PersonaController::class, 'destroy']);
    });

    // Servicios tecnológicos: catálogo y provisión a áreas
    Route::middleware('modulo:SERVICIOS|ORGANISMOS,CONSULTA')->group(function () {
        Route::get('/catalogo-servicios', [CatalogoServicioController::class, 'index']);
        Route::get('/catalogo-servicios/{id}', [CatalogoServicioController::class, 'show']);
        Route::get('/servicios-areas', [ServicioAreaController::class, 'index']);
        Route::get('/servicios-areas/{id}', [ServicioAreaController::class, 'show']);
    });

    Route::middleware('modulo:SERVICIOS,OPERADOR')->group(function () {
        Route::post('/catalogo-servicios', [CatalogoServicioController::class, 'store']);
        Route::put('/catalogo-servicios/{id}', [CatalogoServicioController::class, 'update']);
        Route::post('/servicios-areas', [ServicioAreaController::class, 'store']);
        Route::put('/servicios-areas/{id}', [ServicioAreaController::class, 'update']);
        Route::post('/servicios-areas/{id}/referentes', [ServicioAreaController::class, 'syncReferente']);
        Route::delete('/servicios-areas/{id}/referentes/{referenteId}', [ServicioAreaController::class, 'removeReferente']);
    });

    // Documentos adjuntos: además, el controlador exige el módulo de la entidad a la que pertenecen
    Route::get('/documentos/{id}/descargar', [DocumentoAdjuntoController::class, 'download'])
        ->middleware('modulo:ORGANISMOS|SERVICIOS|PADRON,CONSULTA');
    Route::post('/documentos/upload', [DocumentoAdjuntoController::class, 'upload'])
        ->middleware('modulo:ORGANISMOS|SERVICIOS|PADRON,OPERADOR');

    // Firma digital: entregas de tokens, dispositivos y seguimiento de certificados
    Route::middleware('modulo:FIRMA_DIGITAL,CONSULTA')->group(function () {
        Route::get('/firma-digital/parametros', [CertificadoFirmaController::class, 'parametros']);
        Route::get('/firma-digital/entregas', [EntregaTokenController::class, 'index']);
        Route::get('/firma-digital/entregas/{id}', [EntregaTokenController::class, 'show']);
        Route::get('/firma-digital/certificados', [CertificadoFirmaController::class, 'index']);
        Route::get('/firma-digital/dispositivos', [DispositivoFirmaController::class, 'index']);
        Route::get('/firma-digital/dispositivos/{id}', [DispositivoFirmaController::class, 'show']);
    });

    Route::middleware('modulo:FIRMA_DIGITAL,OPERADOR')->group(function () {
        Route::post('/firma-digital/entregas', [EntregaTokenController::class, 'store']);
        Route::put('/firma-digital/entregas/{id}', [EntregaTokenController::class, 'update']);
        Route::post('/firma-digital/entregas/{id}/finalizar', [EntregaTokenController::class, 'finalizar']);
        Route::post('/firma-digital/entregas/{id}/certificados', [CertificadoFirmaController::class, 'store']);
        Route::put('/firma-digital/certificados/{id}', [CertificadoFirmaController::class, 'update']);
        Route::post('/firma-digital/certificados/{id}/reprogramar-recordatorio', [CertificadoFirmaController::class, 'reprogramarRecordatorio']);
        Route::post('/firma-digital/dispositivos', [DispositivoFirmaController::class, 'store']);
        Route::put('/firma-digital/dispositivos/{id}', [DispositivoFirmaController::class, 'update']);
    });

    // Exportador de contactos
    Route::middleware('modulo:EXPORTADOR,CONSULTA')->group(function () {
        Route::post('/export/preview', [ExportController::class, 'preview']);
        Route::get('/export/download', [ExportController::class, 'downloadCsv']);
    });

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
    // OPERACIONES CRÍTICAS DE ELIMINACIÓN Y GESTIÓN (Solo Administrador)
    // =========================================================================
    Route::middleware('perfil:ADMINISTRADOR')->group(function () {
        Route::delete('/ministerios/{id}', [MinisterioController::class, 'destroy']);
        Route::delete('/autoridades-organismo/{id}', [AutoridadOrganismoController::class, 'destroy']);
        Route::delete('/areas-ti/{id}', [AreaTiController::class, 'destroy']);
        Route::post('/tipos-rol', [TipoRolController::class, 'store']);
        Route::put('/tipos-rol/{id}', [TipoRolController::class, 'update']);
        Route::delete('/tipos-rol/{id}', [TipoRolController::class, 'destroy']);
        Route::delete('/asignaciones-rol/{id}', [AsignacionRolController::class, 'destroy']);
        Route::delete('/catalogo-servicios/{id}', [CatalogoServicioController::class, 'destroy']);
        Route::delete('/servicios-areas/{id}', [ServicioAreaController::class, 'destroy']);
        Route::delete('/documentos/{id}', [DocumentoAdjuntoController::class, 'destroy']);
        Route::delete('/firma-digital/entregas/{id}', [EntregaTokenController::class, 'destroy']);
        Route::delete('/firma-digital/dispositivos/{id}', [DispositivoFirmaController::class, 'destroy']);

        // Administración de usuarios y permisos (D-27, D-34)
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::post('/usuarios', [UsuarioController::class, 'store']);
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    });
});
