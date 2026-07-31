<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tarifa del viaje
    |--------------------------------------------------------------------------
    |
    | Parámetros de la fórmula que calcula el costo de un viaje. La fórmula y
    | la justificación de cada parámetro están en .claude/STANDARDS.md
    | ("Cálculo de tarifas"). Acá solo viven los valores, porque el negocio los
    | ajusta con mucha más frecuencia de lo que cambia la fórmula.
    |
    | Todos los montos son ENTEROS en la unidad mínima de la moneda
    | configurada. Para COP esa unidad es el peso: el peso colombiano no tiene
    | subunidad en circulación, así que no hay centavos que representar. Nunca
    | usar float para dinero.
    |
    */

    'currency' => env('FARE_CURRENCY', 'COP'),

    // Lo que cuesta el viaje antes de moverse: cubre el desplazamiento del
    // conductor hasta el punto de recogida.
    'base' => (int) env('FARE_BASE', 1500),

    'per_kilometer' => (int) env('FARE_PER_KILOMETER', 800),

    // El tiempo en ruta se cobra además de la distancia porque en hora pico el
    // mismo trayecto ocupa al conductor mucho más tiempo (la duración viene
    // del proveedor de mapas con tráfico, ver config/maps.php).
    'per_minute' => (int) env('FARE_PER_MINUTE', 100),

    // Espera solicitada por el pasajero con el viaje ya aceptado. Se cobra más
    // barato que el minuto en ruta: el conductor está detenido, sin gastar
    // combustible.
    'per_waiting_minute' => (int) env('FARE_PER_WAITING_MINUTE', 60),

    // Piso del cobro. Un viaje de tres cuadras igual ocupa a un conductor, y
    // sin piso no compensa aceptarlo.
    'minimum' => (int) env('FARE_MINIMUM', 3000),

    // El total se redondea hacia arriba a un múltiplo de este paso. Buena
    // parte de estos viajes se pagan en efectivo y el vuelto tiene que existir
    // físicamente: nadie devuelve 13 pesos.
    'rounding_step' => (int) env('FARE_ROUNDING_STEP', 50),

];
