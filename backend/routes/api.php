<?php

/*
|--------------------------------------------------------------------------
| API Routes Centrales
|--------------------------------------------------------------------------
*/

// Rutas del dominio Security (Autenticación, Roles, Permisos, etc.)
require __DIR__ . '/../app/Domains/Security/Routes/api.php';
// Rutas del dominio Core (Requerimientos, Operaciones, etc.)
require __DIR__ . '/../app/Domains/Core/Routes/api.php';
// Rutas del dominio Catalogos
require __DIR__ . '/../app/Domains/Catalogs/Routes/api.php';
// Rutas del dominio Workflow
require __DIR__ . '/../app/Domains/Workflow/Routes/api.php';
