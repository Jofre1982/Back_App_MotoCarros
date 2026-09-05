<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los documentos que un conductor debe subir para quedar verificado.
 *
 * `Identidad` cubre cédula, cédula de extranjería o PTP indistintamente: no
 * se distingue cuál de los tres es en esta etapa, porque para la verificación
 * lo único que importa es que la persona pueda identificarse, no el tipo de
 * documento con el que lo hace.
 *
 * Licencia de conducción y SOAT quedan fuera del alcance actual a propósito
 * (decisión de negocio): hoy solo cédula/tarjeta de propiedad son
 * obligatorias para operar. Si el negocio decide exigirlas más adelante, se
 * agregan acá y a `required()`.
 *
 * Los valores viajan tal cual a `driver_documents.type`, y junto con
 * `driver_profile_id` sostienen el índice único que garantiza un solo
 * documento vigente de cada tipo por conductor (ver la migración): volver a
 * subir el mismo tipo reemplaza el anterior, no lo duplica.
 */
enum DocumentType: string
{
    case Identidad = 'identidad';
    case TarjetaPropiedad = 'tarjeta_propiedad';

    /**
     * Los documentos exigidos hoy para quedar verificado. Un conductor queda
     * `Verified` cuando cada uno de estos tiene un `DriverDocument` en estado
     * `Approved` (ver `DriverVerificationStatus`).
     *
     * @return array<int, self>
     */
    public static function required(): array
    {
        return [self::Identidad, self::TarjetaPropiedad];
    }
}
