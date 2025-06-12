<?php

namespace services\dto;

use App\BaseDto;

class UserCreateDto extends BaseDto
{
    public function __construct(
            public string $email,
            public string $name
    ) {
    }
}