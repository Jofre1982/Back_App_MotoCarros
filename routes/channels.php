<?php

declare(strict_types=1);

use App\Broadcasting\DriverChannel;
use App\Broadcasting\RideChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales de broadcasting
|--------------------------------------------------------------------------
|
| Convención de canales privados por entidad (ver .claude/STANDARDS.md,
| "Tiempo real: Laravel Reverb"). Este archivo solo declara qué canales
| existen; la regla de quién entra a cada uno vive en su clase de
| App\Broadcasting, que es donde se puede probar sin levantar HTTP.
|
| Todos los canales son privados: no hay nada en este dominio que se pueda
| escuchar de forma anónima. El cliente los pide con el prefijo `private-`
| (`private-ride.7`) y la autorización pasa por POST /api/v1/broadcasting/auth.
|
| `guards: ['api']` es explícito a propósito: la API es stateless con JWT y no
| tiene guard `web`. Sin esto, la resolución del usuario dependería de qué
| guard dejó por defecto el middleware de la ruta.
|
*/

Broadcast::channel('ride.{rideId}', RideChannel::class, ['guards' => ['api']]);

Broadcast::channel('driver.{driverId}', DriverChannel::class, ['guards' => ['api']]);
