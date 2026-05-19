<?php

if (!function_exists('fanta_old_school_slugify')) {
    function fanta_old_school_slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'utente';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'utente';
    }
}

if (!function_exists('fanta_old_school_normalize_lookup_code')) {
    function fanta_old_school_normalize_lookup_code(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }

        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }
}

if (!function_exists('fanta_old_school_truncate')) {
    function fanta_old_school_truncate(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($maxLength <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('fanta_old_school_user_label')) {
    function fanta_old_school_user_label(array $user): string
    {
        $fullName = trim((string)($user['nome'] ?? '') . ' ' . (string)($user['cognome'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return 'Utente';
    }
}

if (!function_exists('fanta_old_school_column_exists')) {
    function fanta_old_school_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (!$result instanceof mysqli_result) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->close();

        return $exists;
    }
}

if (!function_exists('fanta_old_school_index_exists')) {
    function fanta_old_school_index_exists(mysqli $conn, string $table, string $index): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeIndex = $conn->real_escape_string($index);
        $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        if (!$result instanceof mysqli_result) {
            return false;
        }

        $exists = $result->num_rows > 0;
        $result->close();

        return $exists;
    }
}

if (!function_exists('fanta_old_school_ensure_referral_code_column')) {
    function fanta_old_school_ensure_referral_code_column(mysqli $conn): bool
    {
        static $checked = false;
        static $ready = false;

        if ($checked) {
            return $ready;
        }

        $checked = true;

        if (!fanta_old_school_column_exists($conn, 'utenti', 'fanta_referral_code')) {
            $added = $conn->query(
                "ALTER TABLE utenti
                 ADD COLUMN fanta_referral_code VARCHAR(120) DEFAULT NULL AFTER username"
            );
            if ($added !== true) {
                error_log('fanta_old_school: impossibile aggiungere la colonna fanta_referral_code - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        if (!fanta_old_school_index_exists($conn, 'utenti', 'uq_utenti_fanta_referral_code')) {
            $indexed = $conn->query(
                "ALTER TABLE utenti
                 ADD UNIQUE KEY uq_utenti_fanta_referral_code (fanta_referral_code)"
            );
            if ($indexed !== true) {
                error_log('fanta_old_school: impossibile creare indice univoco referral - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('fanta_old_school_ensure_leads_table')) {
    function fanta_old_school_ensure_leads_table(mysqli $conn): bool
    {
        static $checked = false;
        static $ready = false;

        if ($checked) {
            return $ready;
        }

        $checked = true;

        $query = "CREATE TABLE IF NOT EXISTS fanta_old_school_leads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            utente_referral_id INT UNSIGNED DEFAULT NULL,
            referral_code VARCHAR(120) NOT NULL,
            referral_label VARCHAR(190) NOT NULL,
            nome VARCHAR(100) NOT NULL,
            cognome VARCHAR(100) NOT NULL,
            email_leghe_fc VARCHAR(190) NOT NULL,
            mail_inviata_il DATETIME DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fanta_old_school_email (email_leghe_fc),
            KEY idx_fanta_old_school_referral_user (utente_referral_id),
            KEY idx_fanta_old_school_referral_code (referral_code),
            KEY idx_fanta_old_school_created_at (created_at),
            CONSTRAINT fk_fanta_old_school_referral_user
                FOREIGN KEY (utente_referral_id) REFERENCES utenti(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $ready = $conn->query($query) === true;
        if (!$ready) {
            error_log('fanta_old_school: impossibile creare tabella leads - ' . $conn->error);
        }

        return $ready;
    }
}

if (!function_exists('fanta_old_school_ensure_leads_mail_sent_column')) {
    function fanta_old_school_ensure_leads_mail_sent_column(mysqli $conn): bool
    {
        static $checked = false;
        static $ready = false;

        if ($checked) {
            return $ready;
        }

        $checked = true;

        if (!fanta_old_school_column_exists($conn, 'fanta_old_school_leads', 'mail_inviata_il')) {
            $added = $conn->query(
                "ALTER TABLE fanta_old_school_leads
                 ADD COLUMN mail_inviata_il DATETIME DEFAULT NULL AFTER email_leghe_fc"
            );
            if ($added !== true) {
                error_log('fanta_old_school: impossibile aggiungere la colonna mail_inviata_il - ' . $conn->error);
                $ready = false;
                return false;
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('fanta_old_school_ensure_schema')) {
    function fanta_old_school_ensure_schema(mysqli $conn): bool
    {
        return fanta_old_school_ensure_referral_code_column($conn)
            && fanta_old_school_ensure_leads_table($conn)
            && fanta_old_school_ensure_leads_mail_sent_column($conn);
    }
}

if (!function_exists('fanta_old_school_fetch_user_row')) {
    function fanta_old_school_fetch_user_row(mysqli $conn, int $userId): ?array
    {
        if ($userId <= 0 || !fanta_old_school_ensure_schema($conn)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT id, nome, cognome, username, email, fanta_referral_code
             FROM utenti
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('fanta_old_school_referral_base_from_user')) {
    function fanta_old_school_referral_base_from_user(array $user): string
    {
        $username = trim((string)($user['username'] ?? ''));
        if ($username !== '') {
            return fanta_old_school_slugify($username);
        }

        $fullName = trim((string)($user['nome'] ?? '') . ' ' . (string)($user['cognome'] ?? ''));
        if ($fullName !== '') {
            return fanta_old_school_slugify($fullName);
        }

        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            $parts = explode('@', $email);
            return fanta_old_school_slugify((string)($parts[0] ?? $email));
        }

        return 'utente';
    }
}

if (!function_exists('fanta_old_school_generate_unique_referral_code')) {
    function fanta_old_school_generate_unique_referral_code(mysqli $conn, array $user, int $ignoreUserId = 0): string
    {
        $baseCode = fanta_old_school_referral_base_from_user($user);
        $suffix = 1;

        while ($suffix <= 500) {
            $candidate = $suffix === 1 ? $baseCode : $baseCode . '-' . $suffix;
            $stmt = $conn->prepare(
                "SELECT id
                 FROM utenti
                 WHERE fanta_referral_code = ?
                   AND id <> ?
                 LIMIT 1"
            );
            if (!$stmt) {
                return $candidate;
            }

            $stmt->bind_param('si', $candidate, $ignoreUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result ? $result->fetch_assoc() : null;
            if ($result instanceof mysqli_result) {
                $result->close();
            }
            $stmt->close();

            if (!$existing) {
                return $candidate;
            }

            $suffix++;
        }

        return $baseCode . '-' . $ignoreUserId;
    }
}

if (!function_exists('fanta_old_school_get_referral_code')) {
    function fanta_old_school_get_referral_code(mysqli $conn, int $userId): ?string
    {
        $user = fanta_old_school_fetch_user_row($conn, $userId);
        if (!$user) {
            return null;
        }

        $currentCode = trim((string)($user['fanta_referral_code'] ?? ''));
        if ($currentCode !== '') {
            return $currentCode;
        }

        $newCode = fanta_old_school_generate_unique_referral_code($conn, $user, (int)$user['id']);
        if ($newCode === '') {
            return null;
        }

        $stmt = $conn->prepare(
            "UPDATE utenti
             SET fanta_referral_code = ?
             WHERE id = ?"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('si', $newCode, $userId);
        $saved = $stmt->execute();
        $stmt->close();

        if (!$saved) {
            return null;
        }

        return $newCode;
    }
}

if (!function_exists('fanta_old_school_backfill_referral_codes')) {
    function fanta_old_school_backfill_referral_codes(mysqli $conn): void
    {
        if (!fanta_old_school_ensure_schema($conn)) {
            return;
        }

        $result = $conn->query(
            "SELECT id
             FROM utenti
             WHERE fanta_referral_code IS NULL OR fanta_referral_code = ''
             ORDER BY id ASC"
        );
        if (!$result instanceof mysqli_result) {
            return;
        }

        while ($row = $result->fetch_assoc()) {
            fanta_old_school_get_referral_code($conn, (int)($row['id'] ?? 0));
        }
        $result->close();
    }
}

if (!function_exists('fanta_old_school_find_referrer')) {
    function fanta_old_school_find_referrer(mysqli $conn, string $referralCode): ?array
    {
        $referralCode = fanta_old_school_normalize_lookup_code($referralCode);
        if ($referralCode === '' || !fanta_old_school_ensure_schema($conn)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT id, nome, cognome, username, email, fanta_referral_code
             FROM utenti
             WHERE fanta_referral_code = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $referralCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('fanta_old_school_find_lead_by_email')) {
    function fanta_old_school_find_lead_by_email(mysqli $conn, string $emailLegheFc): ?array
    {
        if (!fanta_old_school_ensure_schema($conn)) {
            return null;
        }

        $emailLegheFc = strtolower(trim($emailLegheFc));
        if ($emailLegheFc === '') {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT id, utente_referral_id, referral_code, referral_label, nome, cognome, email_leghe_fc, mail_inviata_il, created_at
             FROM fanta_old_school_leads
             WHERE email_leghe_fc = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $emailLegheFc);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('fanta_old_school_fetch_lead_by_id')) {
    function fanta_old_school_fetch_lead_by_id(mysqli $conn, int $leadId): ?array
    {
        if ($leadId <= 0 || !fanta_old_school_ensure_schema($conn)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT id, utente_referral_id, referral_code, referral_label, nome, cognome, email_leghe_fc, mail_inviata_il, created_at
             FROM fanta_old_school_leads
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $leadId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('fanta_old_school_create_lead')) {
    function fanta_old_school_create_lead(mysqli $conn, array $payload): array
    {
        if (!fanta_old_school_ensure_schema($conn)) {
            return ['status' => 'error', 'message' => 'Archivio referral non disponibile.'];
        }

        $userId = isset($payload['utente_referral_id']) ? (int)$payload['utente_referral_id'] : 0;
        $referralCode = fanta_old_school_normalize_lookup_code((string)($payload['referral_code'] ?? ''));
        $referralLabel = fanta_old_school_truncate((string)($payload['referral_label'] ?? ''), 190);
        $nome = fanta_old_school_truncate((string)($payload['nome'] ?? ''), 100);
        $cognome = fanta_old_school_truncate((string)($payload['cognome'] ?? ''), 100);
        $emailLegheFc = strtolower(trim((string)($payload['email_leghe_fc'] ?? '')));
        $ipAddress = fanta_old_school_truncate((string)($payload['ip_address'] ?? ''), 45);
        $userAgent = fanta_old_school_truncate((string)($payload['user_agent'] ?? ''), 255);

        if ($userId <= 0 || $referralCode === '' || $referralLabel === '') {
            return ['status' => 'error', 'message' => 'Referral non valido.'];
        }

        if ($nome === '' || $cognome === '' || $emailLegheFc === '') {
            return ['status' => 'error', 'message' => 'Compila tutti i campi richiesti.'];
        }

        $existing = fanta_old_school_find_lead_by_email($conn, $emailLegheFc);
        if ($existing) {
            return [
                'status' => 'duplicate',
                'message' => 'Richiesta di registrazione con questa mail gia eseguita.',
                'lead' => $existing,
            ];
        }

        $stmt = $conn->prepare(
            "INSERT INTO fanta_old_school_leads
                (utente_referral_id, referral_code, referral_label, nome, cognome, email_leghe_fc, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return ['status' => 'error', 'message' => 'Impossibile preparare il salvataggio.'];
        }

        $nullableUserId = $userId > 0 ? $userId : null;
        $stmt->bind_param(
            'isssssss',
            $nullableUserId,
            $referralCode,
            $referralLabel,
            $nome,
            $cognome,
            $emailLegheFc,
            $ipAddress,
            $userAgent
        );

        $saved = $stmt->execute();
        $stmtError = (int)$stmt->errno;
        $leadId = (int)$stmt->insert_id;
        $stmt->close();

        if (!$saved) {
            if ($stmtError === 1062) {
                $duplicate = fanta_old_school_find_lead_by_email($conn, $emailLegheFc);
                return [
                    'status' => 'duplicate',
                    'message' => 'Richiesta di registrazione con questa mail gia eseguita.',
                    'lead' => $duplicate,
                ];
            }

            return ['status' => 'error', 'message' => 'Errore durante il salvataggio della richiesta.'];
        }

        $inviteeName = trim($nome . ' ' . $cognome);
        fanta_old_school_notify_referrer($conn, $userId, $inviteeName);

        return [
            'status' => 'created',
            'message' => 'Richiesta salvata con successo.',
            'lead_id' => $leadId,
        ];
    }
}

if (!function_exists('fanta_old_school_fetch_user_leads')) {
    function fanta_old_school_fetch_user_leads(mysqli $conn, int $userId): array
    {
        if ($userId <= 0 || !fanta_old_school_ensure_schema($conn)) {
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT id, nome, cognome, email_leghe_fc, mail_inviata_il, created_at
             FROM fanta_old_school_leads
             WHERE utente_referral_id = ?
             ORDER BY created_at DESC, id DESC"
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('fanta_old_school_notify_referrer')) {
    function fanta_old_school_notify_referrer(mysqli $conn, int $userId, string $inviteeName): void
    {
        if ($userId <= 0) {
            return;
        }

        require_once __DIR__ . '/push_notifications.php';
        if (!function_exists('tos_push_store_notifications_for_users')) {
            return;
        }

        $inviteeName = trim($inviteeName);
        if ($inviteeName === '') {
            $inviteeName = 'Qualcuno';
        }

        $link = function_exists('login_with_base_path')
            ? login_with_base_path('/fantaoldschool')
            : '/fantaoldschool';

        $title = 'Nuovo invito Fanta Old School';
        $text = $inviteeName . ' ha usato il tuo link Fanta Old School.';

        tos_push_store_notifications_for_users(
            $conn,
            [$userId],
            'fanta_old_school_referral',
            $title,
            $text,
            $link,
            [
                'tag' => 'fanta-old-school-referral-' . $userId,
                'data' => [
                    'type' => 'fanta_old_school_referral',
                    'url' => $link,
                ],
            ]
        );
    }
}

if (!function_exists('fanta_old_school_mark_mail_sent')) {
    function fanta_old_school_mark_mail_sent(mysqli $conn, int $leadId, bool $sent = true): bool
    {
        if ($leadId <= 0 || !fanta_old_school_ensure_schema($conn)) {
            return false;
        }

        if ($sent) {
            $stmt = $conn->prepare(
                "UPDATE fanta_old_school_leads
                 SET mail_inviata_il = NOW()
                 WHERE id = ?"
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE fanta_old_school_leads
                 SET mail_inviata_il = NULL
                 WHERE id = ?"
            );
        }

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $leadId);
        $updated = $stmt->execute();
        $stmt->close();

        return $updated;
    }
}

if (!function_exists('fanta_old_school_fetch_admin_overview')) {
    function fanta_old_school_fetch_admin_overview(mysqli $conn, bool $onlyWithInvites = false): array
    {
        if (!fanta_old_school_ensure_schema($conn)) {
            return [];
        }

        fanta_old_school_backfill_referral_codes($conn);

        $users = [];
        $result = $conn->query(
            "SELECT id, nome, cognome, email, fanta_referral_code
             FROM utenti
             ORDER BY nome ASC, cognome ASC, email ASC"
        );
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $userId = (int)($row['id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $users[$userId] = [
                    'id' => $userId,
                    'label' => fanta_old_school_user_label($row),
                    'email' => (string)($row['email'] ?? ''),
                    'referral_code' => (string)($row['fanta_referral_code'] ?? ''),
                    'lead_count' => 0,
                    'leads' => [],
                ];
            }
            $result->close();
        }

        $leadResult = $conn->query(
            "SELECT id, utente_referral_id, referral_code, referral_label, nome, cognome, email_leghe_fc, mail_inviata_il, created_at
             FROM fanta_old_school_leads
             ORDER BY created_at DESC, id DESC"
        );
        if ($leadResult instanceof mysqli_result) {
            while ($lead = $leadResult->fetch_assoc()) {
                $userId = (int)($lead['utente_referral_id'] ?? 0);
                if ($userId <= 0 || !isset($users[$userId])) {
                    continue;
                }

                $users[$userId]['lead_count']++;
                $users[$userId]['leads'][] = $lead;
            }
            $leadResult->close();
        }

        $rows = array_values($users);
        if ($onlyWithInvites) {
            $rows = array_values(array_filter($rows, static function (array $row): bool {
                return (int)($row['lead_count'] ?? 0) > 0;
            }));
        }

        usort($rows, static function (array $left, array $right): int {
            $countCompare = (int)($right['lead_count'] ?? 0) <=> (int)($left['lead_count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('fanta_old_school_fetch_form_records')) {
    function fanta_old_school_fetch_form_records(mysqli $conn): array
    {
        if (!fanta_old_school_ensure_schema($conn)) {
            return [];
        }

        $query = "SELECT
                l.id,
                l.utente_referral_id,
                l.referral_code,
                l.referral_label,
                l.nome,
                l.cognome,
                l.email_leghe_fc,
                l.mail_inviata_il,
                l.created_at,
                u.nome AS referrer_nome,
                u.cognome AS referrer_cognome,
                u.email AS referrer_email
            FROM fanta_old_school_leads l
            LEFT JOIN utenti u ON u.id = l.utente_referral_id
            ORDER BY l.created_at DESC, l.id DESC";

        $result = $conn->query($query);
        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $referrerLabel = trim((string)($row['referrer_nome'] ?? '') . ' ' . (string)($row['referrer_cognome'] ?? ''));
            if ($referrerLabel === '') {
                $referrerLabel = trim((string)($row['referrer_email'] ?? ''));
            }

            $row['referrer_label'] = $referrerLabel !== '' ? $referrerLabel : (string)($row['referral_label'] ?? '');
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}
