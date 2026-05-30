<?php

/*
|--------------------------------------------------------------------------
| API Routes Centrales
|--------------------------------------------------------------------------
*/

// Importacion  de forma segura las rutas encapsuladas del dominio Security
require __DIR__ . '/../app/Domains/Security/Routes/api.php';
// Rutas del dominio Core (Requerimientos, Operaciones, etc.)
require __DIR__ . '/../app/Domains/Core/Routes/api.php';
require __DIR__ . '/../app/Domains/Catalogs/Routes/api.php';
