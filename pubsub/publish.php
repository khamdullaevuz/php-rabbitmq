<?php

use App\Rabbit;

require 'vendor/autoload.php';

try {
    $rabbit = new Rabbit();

    $rabbit->publish(
            method: 'createUser',
            params: [
                'name' => 'John Doe',
                'email' => 'test'
            ]
    );
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}