<?php

namespace App\Services;

use App\Models\CertificadoFirma;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class FirmaDigitalService
{
    /**
     * Vencimiento predeterminado: fecha de inicio + vigencia configurada (meses)
     */
    public function calcularVencimiento(CarbonInterface|string $fechaInicio): Carbon
    {
        return Carbon::parse($fechaInicio)->startOfDay()
            ->addMonthsNoOverflow((int) config('firma_digital.vigencia_meses', 12));
    }

    /**
     * Recordatorio predeterminado: vencimiento - anticipación configurada (días)
     */
    public function calcularRecordatorio(CarbonInterface|string $fechaVencimiento): Carbon
    {
        return Carbon::parse($fechaVencimiento)->startOfDay()
            ->subDays(CertificadoFirma::diasAnticipacion());
    }

    /**
     * Completa las fechas del certificado a partir de los datos recibidos.
     * Con vencimiento_manual / recordatorio_manual en true se respeta la fecha informada;
     * en false (o ausente) se calcula con los parámetros configurados.
     * Devuelve los atributos listos para crear o actualizar el certificado.
     */
    public function resolverFechas(array $datos, ?CertificadoFirma $actual = null): array
    {
        $fechaInicio = $datos['fecha_inicio'] ?? $actual?->fecha_inicio;

        $vencimientoManual = (bool) ($datos['vencimiento_manual'] ?? false) && ! empty($datos['fecha_vencimiento']);
        $fechaVencimiento = $vencimientoManual
            ? Carbon::parse($datos['fecha_vencimiento'])
            : $this->calcularVencimiento($fechaInicio);

        $recordatorioManual = (bool) ($datos['recordatorio_manual'] ?? false) && ! empty($datos['fecha_recordatorio']);
        $fechaRecordatorio = $recordatorioManual
            ? Carbon::parse($datos['fecha_recordatorio'])
            : $this->calcularRecordatorio($fechaVencimiento);

        if ($fechaVencimiento->lte(Carbon::parse($fechaInicio))) {
            throw ValidationException::withMessages([
                'fecha_vencimiento' => ['La fecha de vencimiento debe ser posterior a la fecha de inicio del certificado.'],
            ]);
        }

        if ($fechaRecordatorio->gt($fechaVencimiento)) {
            throw ValidationException::withMessages([
                'fecha_recordatorio' => ['La fecha del recordatorio no puede ser posterior al vencimiento del certificado.'],
            ]);
        }

        $resultado = [
            'fecha_inicio' => Carbon::parse($fechaInicio)->toDateString(),
            'fecha_vencimiento' => $fechaVencimiento->toDateString(),
            'vencimiento_manual' => $vencimientoManual,
            'fecha_recordatorio' => $fechaRecordatorio->toDateString(),
            'recordatorio_manual' => $recordatorioManual,
        ];

        // Si cambió la fecha del recordatorio de uno ya procesado sin éxito, vuelve a quedar pendiente
        if ($actual && $actual->fecha_recordatorio?->toDateString() !== $resultado['fecha_recordatorio']
            && $actual->recordatorio_estado !== CertificadoFirma::RECORDATORIO_ENVIADO) {
            $resultado['recordatorio_estado'] = CertificadoFirma::RECORDATORIO_PENDIENTE;
            $resultado['recordatorio_intentos'] = 0;
            $resultado['recordatorio_ultimo_error'] = null;
        }

        return $resultado;
    }

    /**
     * Parámetros vigentes, para que la interfaz muestre las mismas fechas calculadas
     */
    public function parametros(): array
    {
        return [
            'vigencia_meses' => (int) config('firma_digital.vigencia_meses', 12),
            'recordatorio_dias_anticipacion' => CertificadoFirma::diasAnticipacion(),
            'recordatorio_hora' => config('firma_digital.recordatorio_hora'),
            'recordatorio_max_intentos' => (int) config('firma_digital.recordatorio_max_intentos', 3),
        ];
    }
}
