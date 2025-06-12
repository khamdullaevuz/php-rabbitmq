<?php

use App\Rabbit;

require 'vendor/autoload.php';

try {
    $rabbit = new Rabbit();

    $result = $rabbit->rpcPublish(method: 'getUser',
            params: [
                    'id' => 4
            ]);
    dd($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
