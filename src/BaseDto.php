<?php

namespace App;

use ReflectionException;

class BaseDto
{
    /**
     * @throws ReflectionException
     */
    public static function fromArray(array $data): static
    {
        $reflection = new \ReflectionClass(static::class);
        $params = [];

        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $name = $param->getName();
            $params[] = $data[$name] ?? $param->getDefaultValue();
        }

        return new static(...$params);
    }
}