<?php

namespace Tests\Feature\Permisos;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ninguna ruta de la API puede quedar sin autenticación ni permiso (D-34). Una ruta nueva sin `modulo:` o
 * `perfil:` hace fallar este test.
 */
class CoberturaRutasTest extends TestCase
{
    private const PUBLICAS = ['api/auth/login'];

    /** Autenticadas sin permiso de módulo: sesión propia y panel de inicio (filtra por módulo adentro) */
    private const SOLO_SESION = ['api/auth/user', 'api/auth/logout', 'api/dashboard/stats'];

    public function test_toda_ruta_de_la_api_exige_sesion_y_permiso(): void
    {
        $sinProteger = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! Str::startsWith($uri, 'api/') || in_array($uri, self::PUBLICAS, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $autenticada = in_array('auth:sanctum', $middleware, true);
            $conPermiso = collect($middleware)->contains(fn ($m) => is_string($m) && (Str::startsWith($m, 'modulo:') || Str::startsWith($m, 'perfil:')));

            if (! $autenticada || (! $conPermiso && ! in_array($uri, self::SOLO_SESION, true))) {
                $sinProteger[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $sinProteger, 'Rutas sin autenticación o sin permiso de módulo');
    }

    public function test_las_descargas_ya_no_son_publicas(): void
    {
        $this->getJson('/api/documentos/1/descargar')->assertUnauthorized();
        $this->getJson('/api/export/download')->assertUnauthorized();
    }
}
