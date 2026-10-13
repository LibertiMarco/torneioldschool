<?php

function mobile_authorization_header(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        $value = $_SERVER[$key] ?? '';
        if (is_string($value) && $value !== '') return $value;
    }
    // Some PHP SAPIs expose the original headers without populating $_SERVER.
    if (function_exists('getallheaders')) {
        foreach (getallheaders() ?: [] as $name => $value) {
            if (strcasecmp((string)$name, 'Authorization') === 0 && is_string($value)) {
                return $value;
            }
        }
    }
    return '';
}
