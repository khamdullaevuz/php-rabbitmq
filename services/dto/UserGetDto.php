<?php

namespace services\dto;

use App\BaseDto;

class UserGetDto extends BaseDto
{
    public function __construct(
        public string $id
    ) {
    }
}