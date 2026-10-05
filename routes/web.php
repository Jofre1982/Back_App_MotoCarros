<?php

// Backend API-only: no hay rutas web. Ver routes/api.php.
// El health check de la raiz (/) se registra como ruta sin sesion en
// bootstrap/app.php (withRouting: then), para que no dependa de la BD.
