<?php

use App\Rabbit;

require 'vendor/autoload.php';

try {
    $rabbit = new Rabbit();

    $result = $rabbit->request(method: 'getUser',
            params: [
                    'id' => 5
            ])->getResult();
    dd($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
