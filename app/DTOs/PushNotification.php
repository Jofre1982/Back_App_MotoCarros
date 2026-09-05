<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * El contenido de una notificación push, independiente de a quién se le
 * envía o por qué proveedor (historia #67).
 */
final readonly class PushNotification
{
    public function __construct(
        public string $title,
        public string $body,
    ) {}
}
