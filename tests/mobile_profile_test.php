<?php
require_once __DIR__ . '/../includi/mobile_auth.php';
require_once __DIR__ . '/../includi/mobile_profile.php';
function profile_expect(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
foreach (['id', 'user_id', 'email', 'ruolo', 'permissions', 'avatar', 'feature_flags'] as $field) {
    try {
        mobile_profile_input([$field => 'changed']);
        throw new RuntimeException('Accepted protected field: ' . $field);
    } catch (MobileAuthError $error) {
        profile_expect($error->status === 400, 'Protected field rejected');
    }
}
foreach ([[], 'false', 'yes', 2] as $value) {
    try {
        mobile_profile_input(['consenso_newsletter' => $value]);
        throw new RuntimeException('Accepted malformed consent');
    } catch (MobileAuthError $error) { profile_expect($error->status === 400, 'Bad consent rejected'); }
}
profile_expect(mobile_profile_input(['consenso_newsletter' => '0'])['consenso_newsletter'] === 0, 'Multipart false consent');
profile_expect(mobile_profile_input(['consenso_newsletter' => true])['consenso_newsletter'] === 1, 'JSON consent');
$validate = static fn(string $password, string $confirmation, string $current): string => account_profile_validation('Nome', 'Cognome', 'user@example.test', $password, $confirmation, $current);
profile_expect($validate('', '', '') === '', 'Profile save does not require password');
profile_expect($validate('ValidPwd1!', 'ValidPwd1!', 'current') === '', 'Valid password accepted');
profile_expect($validate('weak', 'weak', 'current') !== '', 'Weak password rejected');
profile_expect($validate('ValidPwd1!', 'other', 'current') !== '', 'Mismatch rejected');
profile_expect($validate('ValidPwd1!', 'ValidPwd1!', '') !== '', 'Current password required');
profile_expect(account_profile_validation('', 'Cognome', 'user@example.test', '', '', '') !== '', 'Required name');
$public = account_profile_public([
    'currentUser' => ['id' => 1, 'nome' => 'Nome', 'password' => 'secret', 'email' => 'user@example.test', 'ruolo' => 'admin'],
    'giocatoreAssociato' => ['id' => 9, 'foto' => '/photo.png', 'utente_id' => 1],
    'consents' => ['newsletter' => 1, 'marketing' => 0, 'tracking' => 0, 'terms' => 1],
    'successMessage' => '',
]);
profile_expect(!isset($public['profile']['password'], $public['profile']['ruolo']), 'Profile does not leak private data');
profile_expect(!isset($public['player']['utente_id']), 'Player projection is limited');
profile_expect($public['consents'] === ['newsletter' => true, 'marketing' => false, 'tracking' => false], 'Only optional consents exposed');
echo "Mobile profile checks passed\n";
