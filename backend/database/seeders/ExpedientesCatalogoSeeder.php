<?php

namespace Database\Seeders;

use App\Models\ExpedienteEstado;
use App\Models\ExpedienteTipoRequerimiento;
use Illuminate\Database\Seeder;

/**
 * Catálogos iniciales del módulo de expedientes, tomados de la planilla del área (hojas «Estados» y
 * «BIENES 2025»). Idempotente: se puede ejecutar sobre una base con datos. Los grupos de requerimiento
 * son una propuesta editable desde la pantalla de catálogos.
 */
class ExpedientesCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['nombre' => 'EE EN ANÁLISIS', 'orden' => 1, 'color' => 'sky'],
            ['nombre' => 'EE PENDIENTE DE SER RESUELTO', 'orden' => 2, 'color' => 'amber'],
            ['nombre' => 'FRENADO', 'orden' => 3, 'color' => 'rose'],
            ['nombre' => 'GEDO EN REVISIÓN', 'orden' => 4, 'color' => 'indigo'],
            ['nombre' => 'GEDO A LA FIRMA', 'orden' => 5, 'color' => 'violet'],
            ['nombre' => 'PARA DEVOLVER CON PASE SIMPLE', 'orden' => 6, 'color' => 'orange', 'exime_documento' => true],
            ['nombre' => 'SALIDA de EE', 'orden' => 7, 'color' => 'emerald', 'es_final' => true, 'requiere_documento' => true],
        ];

        foreach ($estados as $estado) {
            ExpedienteEstado::firstOrCreate(['nombre' => $estado['nombre']], $estado + [
                'es_final' => false,
                'requiere_documento' => false,
                'exime_documento' => false,
            ]);
        }

        $grupos = [
            'Nomenclador Informático' => 'PERSONAL',
            'Proyectos Reso, Dispo, Decre o Conven' => 'NORMATIVA',
            'Oficio Judicial' => 'NORMATIVA',
            'Homologacion Areas TI' => 'OTROS',
            'Otros' => 'OTROS',
        ];

        $requerimientos = [
            'Adquisición de sistemas biometricos', 'Adquisición de sistemas criptográficos', 'Alquiler equipamiento informático',
            'Cámaras de seguridad', 'Cargadores', 'Certificación de Servicios', 'Contratación de sistema', 'Desarrollo de software',
            'Discos rígidos y/o flexibles', 'Drone/capacitación drone', 'Equipamiento fotográfico', 'Equipamiento varios',
            'Escáner', 'Fotocopiadoras', 'GPS', 'GPU o tarjetas gráficas', 'Homologacion Areas TI', 'Impresoras',
            'Infraestructura', 'Insumos Informáticos', 'Licencias (Adquisición o Renovación)', 'Mantenimiento Red Informática',
            'Monitores', 'Nomenclador Informático', 'Oficio Judicial', 'Otros', "Pc's y/o Notebooks", 'Plataformas o soluciones',
            'Proyectos Reso, Dispo, Decre o Conven', 'Robótica', 'Servicios', 'Servidores', 'Sistema de correo electrónico',
            'Software', 'Soporte y mantenimiento Hard', 'Switch', 'Tablets', 'Televisores', 'UPS',
        ];

        foreach ($requerimientos as $nombre) {
            ExpedienteTipoRequerimiento::firstOrCreate(['nombre' => $nombre], [
                'grupo' => $grupos[$nombre] ?? 'BIENES_SERVICIOS',
                'activo' => true,
            ]);
        }
    }
}
