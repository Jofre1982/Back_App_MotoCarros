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
 * `FotoVehiculo` existe por antifraude, no por identidad (decisión de
 * negocio, historia técnica #75): una tarjeta de propiedad por sí sola no
 * prueba que corresponda al vehículo físico que el conductor realmente
 * opera — una tarjeta de un vehículo dado de baja o en patios puede
 * reutilizarse para registrar uno distinto. Comparar la foto contra la
 * tarjeta es un paso manual del admin al revisar (`AdminDriverDocumentResource`),
 * no algo que este enum ni el backend verifiquen automáticamente. Es también
 * la base del censo real de vehículos y conductores que la app busca
 * sostener con el tiempo.
 *
 * Licencia de conducción y SOAT quedan fuera del alcance actual a propósito
 * (decisión de negocio): hoy solo cédula, tarjeta de propiedad y foto del
 * vehículo son obligatorias para operar. Si el negocio decide exigirlas más
 * adelante, se agregan acá y a `required()`.
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
    case FotoVehiculo = 'foto_vehiculo';

    /**
     * Los documentos exigidos hoy para quedar verificado. Un conductor queda
     * `Verified` cuando cada uno de estos tiene un `DriverDocument` en estado
     * `Approved` (ver `DriverVerificationStatus`).
     *
     * @return array<int, self>
     */
    public static function required(): array
    {
        return [self::Identidad, self::TarjetaPropiedad, self::FotoVehiculo];
    }
}
