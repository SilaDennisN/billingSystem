<?php
define('DEFAULT_RESET_PASSWORD', 'ChangeMe123');

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'hotspot_billing',
        'user' => 'root',
        'pass' => 'silas1256',
    ],

    'routers' => [
        [
            'id' => 1,
            'name' => 'Router Main Office',
            'host' => '192.168.88.1',
            'user' => 'admin',
            'pass' => 'Faith1344',
            'port' => 8728,
            'timeout' => 10,
        ]
        // add more routers here...
    ],

    'app' => [
        'name' => 'Hotspot Billing System',
        'debug' => true
    ]
];
