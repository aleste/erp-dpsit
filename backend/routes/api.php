<?php

use App\Http\Controllers\Api\AreaTiController;
use App\Http\Controllers\Api\AsignacionRolController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoServicioController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentoAdjuntoController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MinisterioController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\ServicioAreaController;
use App\Http\Controllers\Api\TipoRolController;
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
    });
});
