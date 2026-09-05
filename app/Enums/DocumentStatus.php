<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de revisión de un documento subido por un conductor.
 *
 * Los valores viajan tal cual a `driver_documents.status`.
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
