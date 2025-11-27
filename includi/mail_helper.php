<?php

// Invio email con envelope/From configurabili per ridurre lo spam score
if (!function_exists('tos_mail_send')) {
    function tos_mail_send(string $to, string $subject, string $body, string $fromName = 'Tornei Old School'): bool
    {
        $fromEmail = getenv('MAIL_FROM') ?: 'noreply@torneioldschool.it';
        $replyTo = getenv('MAIL_REPLY_TO') ?: 'info@torneioldschool.it';
        $returnPath = getenv('MAIL_RETURN_PATH') ?: $fromEmail;

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "List-Unsubscribe: <mailto:{$replyTo}?subject=Unsubscribe>\r\n";

        $params = $returnPath ? "-f{$returnPath}" : null;
        if ($params) {
            return mail($to, $subject, $body, $headers, $params);
        }
        return mail($to, $subject, $body, $headers);
    }
}

if (!function_exists('inviaEmailVerifica')) {
    function inviaEmailVerifica($email, $nome, $token) {
        $link = build_absolute_url('/verify_email.php?token=' . urlencode($token) . '&email=' . urlencode($email));

        $subject = "Conferma la tua registrazione - Tornei Old School";
        $message = "Ciao {$nome},\n\n"
            . "Grazie per esserti registrato su Tornei Old School.\n"
            . "Per completare la registrazione, conferma il tuo indirizzo email cliccando sul link seguente:\n\n"
            . "{$link}\n\n"
            . "Il link scadra' tra 24 ore.\n\n"
            . "Se non hai richiesto questa registrazione, ignora questa email.\n\n"
            . "A presto,\nIl team Tornei Old School";

        return tos_mail_send($email, $subject, $message);
    }
}

if (!function_exists('inviaEmailResetPassword')) {
    function inviaEmailResetPassword(string $email, string $nome, string $token): bool
    {
        $link = build_absolute_url('/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        $subject = "Reimposta la tua password - Tornei Old School";
        $body = "Ciao {$nome},\n\n"
            . "Hai richiesto di reimpostare la password del tuo account su Tornei Old School.\n"
            . "Se non hai effettuato tu la richiesta, puoi ignorare questa email.\n\n"
            . "Per procedere clicca il link qui sotto (scade tra 1 ora):\n{$link}\n\n"
            . "A presto,\nIl team Tornei Old School";

        return tos_mail_send($email, $subject, $body);
    }
}

if (!function_exists('build_absolute_url')) {
    function build_absolute_url(string $path): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $normalizedPath = '/' . ltrim($path, '/');
        return "{$protocol}://{$host}{$normalizedPath}";
    }
}

if (!function_exists('estrai_estratto_testo')) {
    function estrai_estratto_testo(string $htmlOrText, int $maxLen = 240): string {
        $plain = strip_tags($htmlOrText);
        $plain = preg_replace('/\s+/', ' ', $plain);
        $plain = trim($plain);
        if (mb_strlen($plain) > $maxLen) {
            $plain = mb_substr($plain, 0, $maxLen - 3) . '...';
        }
        return $plain;
    }
}

if (!function_exists('destinatari_newsletter')) {
    function destinatari_newsletter(mysqli $conn): array {
        $lista = [];
        $sql = "SELECT u.email, u.nome FROM consensi_utenti c INNER JOIN utenti u ON u.id = c.user_id WHERE c.newsletter = 1 AND u.email_verificata = 1";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['email'])) {
                    $lista[] = ['email' => $row['email'], 'nome' => $row['nome'] ?? ''];
                }
            }
            $res->close();
        }
        return $lista;
    }
}

if (!function_exists('log_newsletter_send')) {
    function log_newsletter_send(mysqli $conn, int $postId, string $email, string $status, ?string $error = null): void {
        $stmt = $conn->prepare("INSERT INTO newsletter_log (post_id, email, status, error) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isss', $postId, $email, $status, $error);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('invia_notifica_articolo')) {
    function invia_notifica_articolo(mysqli $conn, int $postId, string $titolo, string $contenuto): array {
        $destinatari = destinatari_newsletter($conn);
        if (empty($destinatari)) {
            return ['inviate' => 0, 'totali' => 0];
        }

        $link = build_absolute_url('/articolo.php?id=' . $postId);
        $excerpt = estrai_estratto_testo($contenuto);

        $subject = "Nuovo articolo: {$titolo}";
        $sent = 0;
        foreach ($destinatari as $dest) {
            $body = "Ciao " . ($dest['nome'] ?: 'giocatore') . ",\n\n";
            $body .= "Abbiamo pubblicato un nuovo articolo:\n";
            $body .= $titolo . "\n\n";
            if ($excerpt !== '') {
                $body .= $excerpt . "\n\n";
            }
            $body .= "Leggi qui: {$link}\n\n";
            $body .= "Ricevi questa email perche' hai dato il consenso alla newsletter su Tornei Old School.\n";
            $body .= "Puoi revocare il consenso dalla pagina account o dal link \"Gestisci preferenze\" nel sito.\n";

            $ok = tos_mail_send($dest['email'], $subject, $body, 'Tornei Old School');
            if ($ok) {
                $sent++;
                log_newsletter_send($conn, $postId, $dest['email'], 'sent', null);
            } else {
                log_newsletter_send($conn, $postId, $dest['email'], 'failed', 'mail() failed');
            }
        }

        return ['inviate' => $sent, 'totali' => count($destinatari)];
    }
}
