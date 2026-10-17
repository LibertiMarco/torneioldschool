<?php
function create_reset_token(mysqli $conn, int $userId, string $token, DateTime $expires): bool
{
    // annulla token precedenti
    $conn->query("DELETE FROM password_reset_tokens WHERE user_id = " . (int)$userId);
    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $expStr = $expires->format('Y-m-d H:i:s');
    $stmt->bind_param("iss", $userId, $token, $expStr);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

