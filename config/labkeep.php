<?php
return [
    'institution' => 'ITMA 2',
    'name' => 'LabKeep',
    'inventories' => ['inventario' => 'Laboratorio', 'oficina' => 'Oficina', 'sistemas' => 'Sistemas'],
    'categories' => ['substances' => 'Sustancias', 'chemical_materials' => 'Materiales de laboratorio', 'didactic_materials' => 'Material didáctico', 'equipos' => 'Equipos y bienes'],
    'trusted_proxies' => array_filter(explode(',', env('TRUSTED_PROXIES', ''))),
];

