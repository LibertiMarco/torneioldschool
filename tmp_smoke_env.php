<?php

if (!function_exists('env_or_default')) {
    function env_or_default(string $key, string $default = ''): string
    {
        static $overrides = [
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'torneioldschool',
            'DB_USER' => 'root',
            'DB_PASSWORD' => '',
        ];

        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}
