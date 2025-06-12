<?php

namespace services;

class UserService
{
    public function create(string $email, string $name): void
    {
        throw new \Exception('test');
        echo "User created with email: $email and name: $name\n";
    }

    public function getUser(string $id): array
    {
        throw new \Exception('test');
        return [
            'id' => $id,
            'name' => 'John Doe',
            'email' => 'test@mail.com'
        ];
    }
}