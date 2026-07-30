# MotoYa — Backend App MotoCarros

## Qué es este proyecto

Backend en Laravel para **MotoYa**, un servicio de solicitud de transporte tipo moto-taxi
(similar en concepto a Uber/InDriver pero para viajes en moto). La app permite que
pasajeros soliciten un viaje y que conductores (motociclistas) lo acepten y lo completen.

**Este proyecto es exclusivamente un backend HTTP API REST.** No sirve vistas, no hay
frontend propio ni sesiones basadas en navegador. Los únicos consumidores son apps
móviles externas (pasajero y conductor), que se autentican vía JWT y consumen JSON
bajo `/api/v1`. No agregar vistas Blade, rutas web con sesión, ni lógica pensada para
un navegador — si en el futuro se necesita un panel administrativo, eso se decide
explícitamente como un proyecto/decision aparte, no por defecto.

Estado actual: **esqueleto inicial de Laravel**, sin dominio de negocio implementado
todavía (no hay modelos, migraciones, ni endpoints propios más allá del `User` por
defecto de Laravel). Este archivo documenta la intención funcional del proyecto para
guiar el desarrollo futuro.

## Stack técnico

- PHP ^8.3
- Laravel ^13.8
- Base de datos: SQLite en desarrollo (`DB_CONNECTION=sqlite`), ver [.env.example](../.env.example)
- Cache/colas: driver `database`. Sin sesiones de navegador (auth stateless vía JWT).
- Testing: PHPUnit (`composer test` / `php artisan test`)
- Sin frontend: no se usa Blade/Vite/Tailwind del skeleton por defecto de Laravel para
  nada orientado a negocio (ver limpieza de scaffolding en STANDARDS.md).

## Dominio funcional esperado

Aunque todavía no está implementado, el propósito del backend es soportar este dominio:

- **Usuarios**: dos roles principales, *pasajero* y *conductor*. Un mismo `User` podría
  tener ambos roles o ser modelos/tablas separadas — pendiente de decidir.
- **Vehículos (motos)**: datos del vehículo del conductor (placa, modelo, documentos).
- **Solicitudes de viaje (rides/trips)**: origen, destino, estado (solicitado, aceptado,
  en curso, completado, cancelado), conductor asignado, tarifa, tiempos.
- **Geolocalización**: ubicación en tiempo real de conductores y seguimiento del viaje.
- **Tarifas/pagos**: cálculo de costo del viaje (por distancia/tiempo), método de pago.
- **Notificaciones en tiempo real**: asignación de viaje, cambios de estado (candidatos:
  Laravel Broadcasting/WebSockets, ya que `BROADCAST_CONNECTION` está configurable).

Estos puntos son la intención de negocio, no una decisión de arquitectura cerrada:
al implementar cada pieza, seguir las convenciones estándar de Laravel (Eloquent,
migraciones, form requests, policies para autorización conductor/pasajero, API
resources para las respuestas JSON).

## Estándares, arquitectura y prácticas de código

Ver [.claude/STANDARDS.md](STANDARDS.md) para las decisiones vigentes:

- Lógica de negocio en **Actions** (una clase por caso de uso, `app/Actions/...`).
- Autenticación de API con **JWT** (`tymon/jwt-auth`), autorización de negocio vía Policies.
- Tiempo real (tracking de viaje, cambios de estado) con **Laravel Reverb**.
- Testing con **PHPUnit** (Feature + Unit), no Pest.
- Este backend es principalmente una **API**: rutas bajo `routes/api.php`, versionadas
  `/api/v1`, respuestas siempre vía API Resources.
