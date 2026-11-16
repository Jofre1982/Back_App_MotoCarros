<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cupos de anuncios
    |--------------------------------------------------------------------------
    |
    | Cuántos anuncios pueden estar saliendo el mismo día. Pocos cupos hacen
    | que cada negocio se vea seguido (y que el espacio valga más). Para
    | cambiarlo basta con ADS_MAX_ACTIVE en el .env.
    |
    | Cada cuántos segundos rota el anuncio lo decide la app (8 s).
    |
    */

    'max_active' => (int) env('ADS_MAX_ACTIVE', 5),

];
