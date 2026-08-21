<?php

namespace Database\Seeders;

use App\Models\CatalogoServicio;
use Illuminate\Database\Seeder;

class CatalogoServicioSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            [
                'codigo' => 'SRV_NUBE_PROV',
                'nombre' => 'Nube Provincial DPSIT (IaaS/PaaS)',
                'categoria' => 'INFRAESTRUCTURA',
                'descripcion' => 'Infraestructura de cómputo en la nube provincial con asignación de recursos dedicados, cuotas y storage.',
                'activo' => true,
            ],
            [
                'codigo' => 'SRV_SSO_PROV',
                'nombre' => 'Autenticación Única Provincial (SSO / OpenID Connect)',
                'categoria' => 'IDENTIDAD',
                'descripcion' => 'Integración con el Bus de Identidad Ciudadana y Empleado Público mediante protocolo OAuth2/OIDC.',
                'activo' => true,
            ],
            [
                'codigo' => 'SRV_INTEROP_BUS',
                'nombre' => 'Plataforma de Interoperabilidad Provincial (API Gateway)',
                'categoria' => 'INTEGRACION',
                'descripcion' => 'Bus de servicios e intercambio seguro de datos entre organismos de gobierno (Renaper, AFIP, Registro Civil, etc.).',
                'activo' => true,
            ],
            [
                'codigo' => 'SRV_VPN_GOB',
                'nombre' => 'Acceso Remoto Seguro (VPN Gubernamental)',
                'categoria' => 'SEGURIDAD',
                'descripcion' => 'Túneles cifrados IPsec/WireGuard para conexión de dependencias ministeriales a la Red Provincial (RPD).',
                'activo' => true,
            ],
        ];

        foreach ($servicios as $s) {
            CatalogoServicio::updateOrCreate(['codigo' => $s['codigo']], $s);
        }
    }
}
