<?php

use services\dto\UserCreateDto;
use services\dto\UserGetDto;
use services\UserService;

return [
    'createUser' => [
        'class' => UserService::class,
        'method' => 'create',
        'dto' => UserCreateDto::class
    ],
    'getUser' => [
        'class' => UserService::class,
        'method' => 'getUser',
        'dto' => UserGetDto::class
    ]
];