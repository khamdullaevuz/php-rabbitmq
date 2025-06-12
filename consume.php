<?php

use App\Rabbit;

require 'vendor/autoload.php';

try {
    $rabbit = new Rabbit();

    $rabbit->consume();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}