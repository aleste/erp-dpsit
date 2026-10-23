<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Salvaguarda: RefreshDatabase (que se inicializa en setUpTraits) recrea el esquema completo.
     * Nunca debe ejecutarse sobre la base de desarrollo; se verifica antes de inicializar los traits.
     */
    protected function setUpTraits()
    {
        $base = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($base, '_test')) {
            throw new RuntimeException("Tests abortados: la base '{$base}' no es de pruebas (debe terminar en _test).");
        }

        return parent::setUpTraits();
    }
}
