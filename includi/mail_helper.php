<?php

if (!function_exists('inviaEmailVerifica')) {
    function inviaEmailVerifica($email, $nome, $token) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname($script), '/\\');
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }

        $link = $protocol . '://' . $host . $basePath . '/verify_email.php?token=' . urlencode($token) . '&email=' . urlencode($email);

        $subject = "Conferma la tua registrazione - Tornei Old School";
        $message = "Ciao {$nome},\n\n"
            . "Grazie per esserti registrato su Tornei Old School.\n"
            . "Per completare la registrazione, conferma il tuo indirizzo email cliccando sul link seguente:\n\n"
            . "{$link}\n\n"
            . "Il link scadrà tra 24 ore.\n\n"
            . "Se non hai richiesto questa registrazione, ignora questa email.\n\n"
            . "A presto,\nIl team Tornei Old School";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "From: Tornei Old School <no-reply@torneioldschool.local>\r\n";

        return mail($email, $subject, $message, $headers);
    }
}
