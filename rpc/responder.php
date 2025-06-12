<?php

use App\Rabbit;

require 'vendor/autoload.php';

try {
    $rabbit = new Rabbit();
    $rabbit->rpcConsume();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}