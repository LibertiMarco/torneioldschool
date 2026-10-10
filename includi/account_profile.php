<?php
// Shared by the website and authenticated mobile account endpoints.
require_once __DIR__ . '/consent_helpers.php';
require_once __DIR__ . '/image_optimizer.php';

function caricaUtente($conn, $id) {
    $stmt = $conn->prepare("SELECT id, nome, cognome, email, avatar, password FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $utente = $result->fetch_assoc();
    $stmt->close();
    return $utente;
}

function generaNomeAvatar($nome, $cognome, $estensione, $uploadDir) {
    $nomeSan = preg_replace('/[^A-Za-z0-9]/', '', ucwords(strtolower($nome)));
    $cognomeSan = preg_replace('/[^A-Za-z0-9]/', '', ucwords(strtolower($cognome)));
    $base = $nomeSan . $cognomeSan;
    if ($base === '') {
        $base = 'avatar';
    }

    $filename = $base . '.' . $estensione;
    $counter = 2;
    while (file_exists($uploadDir . '/' . $filename)) {
        $filename = $base . $counter . '.' . $estensione;
        $counter++;
    }
    return $filename;
}

function risolviAvatarUrl($avatarPath) {
    if (!empty($avatarPath)) {
        if (preg_match('#^https?://#i', $avatarPath)) {
            return $avatarPath;
        }
        return '/' . ltrim($avatarPath, '/');
    }
    return '/img/icone/user.png';
}

function caricaGiocatoreAssociato(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare("SELECT id, nome, cognome, foto FROM giocatori WHERE utente_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function risolviFotoGiocatoreUrl(?string $path): string {
    if (!$path) {
        return '/img/giocatori/unknown.jpg';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $clean = '/' . ltrim($path, '/');
    return $clean ?: '/img/giocatori/unknown.jpg';
}

function eliminaFotoGiocatoreSeLocale(?string $path): void {
    if (!$path) return;
    $path = str_replace('\\', '/', $path);
    if ($path === '/img/giocatori/unknown.jpg') return;
    if (strpos($path, '/img/giocatori/') !== 0) return;
    $uploadsDir = realpath(dirname(__DIR__) . '/img/giocatori');
    if (!$uploadsDir) return;
    $filename = basename($path);
    $full = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Tenta più passaggi di compressione/resize per mantenere la foto leggera.
 * Ritorna true se il file finale rientra nei limiti indicati.
 */
function comprimiFotoGiocatoreAggressivo(string $fullPath): bool {
    $targetLimit = 6 * 1024 * 1024; // 6MB limite duro post-compressione
    $attempts = [
        ['maxWidth' => 2200, 'maxHeight' => 2200, 'quality' => 82, 'maxBytes' => (int)round(5.5 * 1024 * 1024)],
        ['maxWidth' => 1800, 'maxHeight' => 1800, 'quality' => 78, 'maxBytes' => (int)round(4.5 * 1024 * 1024)],
        ['maxWidth' => 1400, 'maxHeight' => 1400, 'quality' => 74, 'maxBytes' => (int)round(3.5 * 1024 * 1024)],
        ['maxWidth' => 1100, 'maxHeight' => 1100, 'quality' => 70, 'maxBytes' => (int)round(3.0 * 1024 * 1024)],
    ];

    foreach ($attempts as $opts) {
        optimize_image_file($fullPath, $opts);
        $size = @filesize($fullPath) ?: PHP_INT_MAX;
        if ($size <= $opts['maxBytes']) {
            return true;
        }
    }

    return (@filesize($fullPath) ?: PHP_INT_MAX) <= $targetLimit;
}

function aggiornaFotoSuSquadre(mysqli $conn, int $giocatoreId, string $relativePath): void {
    $stmt = $conn->prepare("UPDATE squadre_giocatori SET foto=? WHERE giocatore_id=?");
    if ($stmt) {
        $stmt->bind_param("si", $relativePath, $giocatoreId);
        $stmt->execute();
        $stmt->close();
    }
}


function account_profile_validation(string $nome, string $cognome, string $email,
    string $password, string $confirmation, string $currentPassword): string {
    $passwordRegex = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\\\|,.<>\/?]).{8,}$/';
    if ($nome === '' || $cognome === '' || $email === '') { return 'Compila tutti i campi obbligatori.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return "Inserisci un'email valida."; }
    if ($password !== '' && $password !== $confirmation) { return 'Le password non coincidono.'; }
    if ($password !== '' && !preg_match($passwordRegex, $password)) {
        return 'La password deve avere almeno 8 caratteri, una maiuscola, un numero e un simbolo speciale.';
    }
    if ($password !== '' && $currentPassword === '') {
        return 'Per cambiare password inserisci prima quella attuale.';
    }
    return '';
}

function account_profile_run(mysqli $conn, int $userId, ?array $post, array $files, bool $csrfValid, bool $updateBrowserSession = false): array {
    $successMessage = '';
    $errorMessage = '';
    $infoMessage = '';
    $currentUser = caricaUtente($conn, $userId);
    if (!$currentUser) {
        throw new RuntimeException('Account non disponibile.');
    }
    $consents = consent_current_snapshot($conn, $userId, $currentUser['email'] ?? '');
    $giocatoreAssociato = caricaGiocatoreAssociato($conn, $userId);
    $fotoGiocatoreUrl = risolviFotoGiocatoreUrl($giocatoreAssociato['foto'] ?? null);

    if ($post !== null) {
        if (!$csrfValid) {
            $errorMessage = "Sessione scaduta. Ricarica la pagina e riprova.";
        } elseif (isset($post['upload_foto_giocatore'])) {
            if (!$giocatoreAssociato) {
                $errorMessage = "Non abbiamo ancora collegato il tuo account a un profilo giocatore. Contatta un amministratore.";
            } elseif (!isset($files['foto_giocatore']) || $files['foto_giocatore']['error'] === UPLOAD_ERR_NO_FILE) {
                $errorMessage = "Seleziona una foto da caricare.";
            } elseif ($files['foto_giocatore']['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = "Errore nel caricamento della foto. Riprova.";
            } else {
                $maxUpload = 50 * 1024 * 1024; // 50MB input massimo
                if ($files['foto_giocatore']['size'] > $maxUpload) {
                    $errorMessage = "La foto è troppo pesante. Carica un file sotto i 50MB.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $files['foto_giocatore']['tmp_name']) : false;
                    if ($finfo instanceof finfo) {
                        unset($finfo);
                    }
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!$mime || !isset($allowed[$mime])) {
                        $errorMessage = "Formato non valido. Usa JPG, PNG o WEBP.";
                    } else {
                        $uploadDir = dirname(__DIR__) . '/img/giocatori';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0775, true);
                        }
                        $ext = $allowed[$mime];
                        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', ($giocatoreAssociato['nome'] ?? '') . ($giocatoreAssociato['cognome'] ?? '')));
                        if ($slug === '') {
                            $slug = 'giocatore';
                        }
                        $base = $slug . '_uid' . $userId;
                        $filename = $base . '.' . $ext;
                        $counter = 2;
                        while (file_exists($uploadDir . '/' . $filename)) {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        }

                        $destination = $uploadDir . '/' . $filename;
                        if (!move_uploaded_file($files['foto_giocatore']['tmp_name'], $destination)) {
                            $errorMessage = "Salvataggio file non riuscito.";
                        } else {
                            $compressed = comprimiFotoGiocatoreAggressivo($destination);
                            $finalSize = @filesize($destination) ?: PHP_INT_MAX;
                            if (!$compressed || $finalSize > 6 * 1024 * 1024) {
                                @unlink($destination);
                                $errorMessage = "Immagine troppo pesante anche dopo la compressione (usa una foto più leggera).";
                            } else {
                                $relativePath = '/img/giocatori/' . $filename;
                                $stmt = $conn->prepare("UPDATE giocatori SET foto=? WHERE id=? AND utente_id=?");
                                $stmt->bind_param("sii", $relativePath, $giocatoreAssociato['id'], $userId);
                                if ($stmt->execute()) {
                                    eliminaFotoGiocatoreSeLocale($giocatoreAssociato['foto'] ?? null);
                                    aggiornaFotoSuSquadre($conn, (int)$giocatoreAssociato['id'], $relativePath);
                                    $successMessage = "Foto giocatore aggiornata con successo.";
                                    $giocatoreAssociato = caricaGiocatoreAssociato($conn, $userId);
                                    $fotoGiocatoreUrl = risolviFotoGiocatoreUrl($giocatoreAssociato['foto'] ?? null);
                                } else {
                                    @unlink($destination);
                                    $errorMessage = "Errore durante il salvataggio nel database.";
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
            }
        } elseif (isset($post['revoca_consensi'])) {
            $consents = consent_save($conn, $userId, $currentUser['email'] ?? '', [
                'marketing' => 0,
                'newsletter' => 0,
                'tracking' => 0,
            ], 'account', 'revoke_all');
            $successMessage = "Consensi marketing/newsletter/tracciamento revocati.";
        } else {
            $nome = trim($post['nome'] ?? '');
            $cognome = trim($post['cognome'] ?? '');
            $email = $currentUser['email'];
            $password = trim($post['password'] ?? '');
            $confirmPassword = trim($post['confirm_password'] ?? '');
            $currentPassword = trim($post['current_password'] ?? '');
            $consensoMarketing = !empty($post['consenso_marketing']);
            $consensoNewsletter = !empty($post['consenso_newsletter']);
            $consensoTracking = !empty($post['consenso_tracking']);
            $avatarPath = $currentUser['avatar'] ?? null;
            $emailChanged = false;

            $errorMessage = account_profile_validation($nome, $cognome, $email, $password, $confirmPassword, $currentPassword);
            // Verify before moving an uploaded image or making any account changes.
            if (!$errorMessage && $password !== '' && !password_verify($currentPassword, $currentUser['password'] ?? '')) {
                $errorMessage = 'La password attuale non è corretta.';
            }

        if (!$errorMessage && isset($files['avatar']) && $files['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($files['avatar']['error'] === UPLOAD_ERR_OK) {
                $maxSize = 2 * 1024 * 1024; // 2MB
                if ($files['avatar']['size'] > $maxSize) {
                    $errorMessage = "La foto deve essere inferiore a 2MB.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $files['avatar']['tmp_name']) : false;
                    if ($finfo instanceof finfo) {
                        unset($finfo); // finfo_close deprecato, lasciamo al GC
                    }
                    if (!$mime) {
                        $errorMessage = "Impossibile determinare il formato dell'immagine.";
                    }

                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp'
                    ];

                    if ($errorMessage || !isset($allowed[$mime])) {
                        $errorMessage = "Formato immagine non valido. Usa JPG, PNG, GIF o WEBP.";
                    } else {
                        $uploadDir = dirname(__DIR__) . '/img/utenti';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0775, true);
                        }
                        $estensione = $allowed[$mime];
                        $filename = generaNomeAvatar($nome, $cognome, $estensione, $uploadDir);
                        $destination = $uploadDir . '/' . $filename;

                        if (move_uploaded_file($files['avatar']['tmp_name'], $destination)) {
                            $nuovoPercorso = 'img/utenti/' . $filename;
                            if (!empty($avatarPath) && strpos($avatarPath, 'img/utenti/') === 0) {
                                $vecchioAssoluto = dirname(__DIR__) . '/' . ltrim($avatarPath, '/');
                                if (is_file($vecchioAssoluto)) {
                                    @unlink($vecchioAssoluto);
                                }
                            }
                            $avatarPath = $nuovoPercorso;
                        } else {
                            $errorMessage = "Impossibile salvare la foto. Riprova.";
                        }
                    }
                }
            } else {
                $errorMessage = "Errore nel caricamento dell'immagine.";
            }
        }

        if (!$errorMessage) {
            $updateFields = ["nome=?", "cognome=?", "email=?", "avatar=?"];
            $types = "ssss";
            $params = [$nome, $cognome, $email, $avatarPath];

            if ($password !== '') {
                $updateFields[] = "password=?";
                $types .= "s";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $types .= "i";
            $params[] = $userId;

            $sql = "UPDATE utenti SET " . implode(', ', $updateFields) . " WHERE id=?";
            $stmt = $conn->prepare($sql);
            $bindParams = [$types];
            foreach ($params as $idx => $value) {
                $bindParams[] = &$params[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);

            if ($stmt->execute()) {
                if ($updateBrowserSession) {
                $_SESSION['nome'] = $nome;
                $_SESSION['cognome'] = $cognome;
                $_SESSION['email'] = $email;
                $_SESSION['avatar'] = $avatarPath;
                }
                $consents = consent_save($conn, $userId, $currentUser['email'] ?? '', [
                    'marketing' => $consensoMarketing,
                    'newsletter' => $consensoNewsletter,
                    'tracking' => $consensoTracking,
                    'terms' => 1,
                ], 'account');
                $successMessage = "Impostazioni aggiornate con successo.";

                $currentUser = caricaUtente($conn, $userId);
            } else {
                $errorMessage = "Errore durante l'aggiornamento dell'account.";
            }

            $stmt->close();
        }
    }
    }


    return compact('currentUser', 'consents', 'giocatoreAssociato', 'fotoGiocatoreUrl',
        'successMessage', 'errorMessage', 'infoMessage');
}

function account_profile_public(array $result): array {
    $user = array_intersect_key($result['currentUser'], array_flip(['id', 'nome', 'cognome', 'email', 'avatar']));
    $player = $result['giocatoreAssociato'];
    return [
        'profile' => $user,
        'consents' => array_map(static fn($value): bool => !empty($value),
            array_intersect_key($result['consents'], array_flip(['newsletter', 'marketing', 'tracking']))),
        'player' => $player ? array_intersect_key($player, array_flip(['id', 'nome', 'cognome', 'foto'])) : null,
        'message' => $result['successMessage'],
    ];
}
