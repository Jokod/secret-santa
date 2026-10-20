<?php

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Strips trailing slashes from an env value (e.g. DEFAULT_URI before path concat).
 */
final class RtrimSlashEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        /** @var string $value */
        $value = (string) $getEnv($name);

        return rtrim($value, '/');
    }

    public static function getProvidedTypes(): array
    {
        return [
            'rtrimslash' => 'string',
        ];
    }
}
