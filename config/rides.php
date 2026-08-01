<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aviso a conductores cercanos
    |--------------------------------------------------------------------------
    |
    | Radio, en metros, dentro del cual un conductor disponible recibe el
    | aviso de una solicitud de viaje nueva (historia #17). Ver
    | App\Services\Realtime\EloquentNearbyDriverFinder.
    |
    */

    'nearby_radius_meters' => (int) env('RIDES_NEARBY_RADIUS_METERS', 3000),

];
