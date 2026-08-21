<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;

class PersonaSeeder extends Seeder
{
    public function run(): void
    {
        $personas = [
            [
                'cuil_cuit' => '20-31845920-4',
                'apellido' => 'González',
                'nombre' => 'Martín Alejandro',
                'email_principal' => 'mgonzalez@salud.gob.ar',
                'email_alternativo' => 'martin.gonzalez.it@gmail.com',
                'telefono_principal' => '0221-15-5489201',
                'telefono_alternativo' => '0221-429-5000',
                'titulo_profesional' => 'Licenciado en Sistemas de Información',
                'observaciones' => 'Especialista en interoperabilidad HL7/FHIR y plataformas sanitarias.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '27-34981204-8',
                'apellido' => 'Fernández',
                'nombre' => 'Laura Silvina',
                'email_principal' => 'lfernandez@seguridad.salud.gob.ar',
                'email_alternativo' => 'laura.fernandez.sec@gmail.com',
                'telefono_principal' => '0221-15-6124589',
                'telefono_alternativo' => null,
                'titulo_profesional' => 'Ingeniera en Seguridad Informática',
                'observaciones' => 'CISO de Salud, referente ante el CSIRT Provincial.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '20-28741029-3',
                'apellido' => 'Rodríguez',
                'nombre' => 'Carlos Eduardo',
                'email_principal' => 'crodriguez@educacion.gob.ar',
                'email_alternativo' => 'carlos.rodriguez.edu@gmail.com',
                'telefono_principal' => '0221-15-4781290',
                'telefono_alternativo' => '0221-429-8801',
                'titulo_profesional' => 'Ingeniero en Computación',
                'observaciones' => 'Director Provincial de Tecnología Educativa.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '27-32158974-5',
                'apellido' => 'Benítez',
                'nombre' => 'María Florencia',
                'email_principal' => 'mbenitez@educacion.gob.ar',
                'email_alternativo' => 'florencia.benitez.dev@gmail.com',
                'telefono_principal' => '0221-15-5339911',
                'telefono_alternativo' => null,
                'titulo_profesional' => 'Analista Universitaria de Sistemas',
                'observaciones' => 'Referente de Interoperabilidad para el Sistema Provincial de Alumnos.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '20-30114785-9',
                'apellido' => 'Alvarez',
                'nombre' => 'Esteban Daniel',
                'email_principal' => 'ealvarez@hacienda.gob.ar',
                'email_alternativo' => 'esteban.alvarez.fin@gmail.com',
                'telefono_principal' => '0221-15-4128833',
                'telefono_alternativo' => '0221-429-4410',
                'titulo_profesional' => 'Licenciado en Informática',
                'observaciones' => 'Director General en Economía y Finanzas.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '23-35687412-9',
                'apellido' => 'Valenzuela',
                'nombre' => 'Guillermo Hernán',
                'email_principal' => 'gvalenzuela@seguridad.gob.ar',
                'email_alternativo' => 'guillermo.valenzuela@policia.gob.ar',
                'telefono_principal' => '0221-15-6889922',
                'telefono_alternativo' => '0221-429-3050',
                'titulo_profesional' => 'Ingeniero en Telecomunicaciones',
                'observaciones' => 'Superintendente de Comunicaciones.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '27-36254189-2',
                'apellido' => 'Morales',
                'nombre' => 'Mariana Belén',
                'email_principal' => 'mmorales@seguridad.gob.ar',
                'email_alternativo' => 'mariana.morales.ciso@gmail.com',
                'telefono_principal' => '0221-15-5112244',
                'telefono_alternativo' => null,
                'titulo_profesional' => 'Magíster en Ciberdefensa',
                'observaciones' => 'Referente CISO en Ministerio de Seguridad.',
                'activo' => true,
            ],
            [
                'cuil_cuit' => '20-33418529-6',
                'apellido' => 'Pérez Ruiz',
                'nombre' => 'Lucas Gastón',
                'email_principal' => 'lperez@dpsit.gob.ar',
                'email_alternativo' => 'lucas.perezruiz@gmail.com',
                'telefono_principal' => '0221-15-4998877',
                'telefono_alternativo' => '0221-429-9000',
                'titulo_profesional' => 'Ingeniero en Sistemas de Información',
                'observaciones' => 'Administrador de Nube Pública Provincial en DPSIT.',
                'activo' => true,
            ]
        ];

        foreach ($personas as $p) {
            Persona::updateOrCreate(['cuil_cuit' => $p['cuil_cuit']], $p);
        }
    }
}
