<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Matrices de Ponderación de Progreso Global (CU-008)
    |--------------------------------------------------------------------------
    |
    | Aquí se definen los pesos porcentuales para cada hito del ciclo de vida
    | del requerimiento. Si el negocio altera los pesos en el futuro, 
    | solo se debe modificar este archivo de configuración.
    |
    */

    'matrices' => [
        'Roles' => [
            'RC' => 2.0, 'ES-R' => 3.0, 'ATF-I' => 3.0, 'ATF-C' => 7.0,
            'DT-I' => 3.0, 'DT-C' => 7.0, 'COR-I' => 5.0, 'COR-C' => 20.0,
            'PI-I' => 5.0, 'PI-C' => 10.0, 'CER-I' => 5.0, 'CER-C' => 10.0,
            'PAP-I' => 3.0, 'PAP-C' => 7.0, 'AU-C' => 5.0, 'RF' => 5.0,
        ],
        
        'Entregable' => [
            'RC' => 2.0, 'ES-R' => 3.0, 'ATF-I' => 3.0, 'ATF-C' => 7.0,
            'COE-I' => 15.0, 'COE-C' => 30.0, 'CEE-I' => 10.0, 'CEE-C' => 30.0,
            'RF' => 10.0,
        ],
        
        'Mixto' => [
            'RC' => 2.0, 'ES-R' => 3.0, 'ATF-I' => 3.0, 'ATF-C' => 7.0,
            'DT-I' => 3.0, 'DT-C' => 7.0, 'COR-I' => 3.0, 'COR-C' => 10.0,
            'COE-I' => 3.0, 'COE-C' => 10.0, 'PI-I' => 5.0, 'PI-C' => 7.0,
            'CER-I' => 5.0, 'CER-C' => 7.0, 'CEE-I' => 3.0, 'CEE-C' => 5.0,
            'PAP-I' => 3.0, 'PAP-C' => 6.0, 'AU-C' => 3.0, 'RF' => 5.0,
        ]
    ]
];