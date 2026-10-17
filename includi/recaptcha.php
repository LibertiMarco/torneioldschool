<?php
function verify_recaptcha(string $secret, string $token, string $ip = '', string $expectedAction = '', float $minScore = 0.0): bool
{
    if (trim($secret) === '' || trim($token) === '') {
        return false;
    }
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
        ]
    ]);
    $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($result === false) {
        return false;
    }
    $data = json_decode($result, true);
    if (!is_array($data) || empty($data['success'])) {
        return false;
    }
    if ($expectedAction !== '' && isset($data['action']) && $data['action'] !== $expectedAction) {
        return false;
    }
    if ($minScore > 0 && isset($data['score']) && $data['score'] < $minScore) {
        return false;
    }
    return true;
}

