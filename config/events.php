<?php

use services\UserService;

return [
    'createUser' => [
        'class' => UserService::class,
        'method' => 'create',
    ],
    'getUser' => [
        'class' => UserService::class,
        'method' => 'getUser',
    ]
];