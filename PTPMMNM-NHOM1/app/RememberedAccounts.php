<?php
declare(strict_types=1);
/**
 * Remembered profiles on one trusted browser. The cookie contains a random device
 * capability; only its SHA-256 digest is saved in the application datastore.
 * Passwords are never saved or sent to the browser for account switching.
 */
final class RememberedAccounts {
    public const COOKIE = 'ff_trusted_device';
    public const LIFETIME = 30 * 24 * 60 * 60;
    private const MAX_ACCOUNTS = 8;

    public static function fromCookie(): ?string {
        $value = $_COOKIE[self::COOKIE] ?? null;
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) ? $value : null;
    }

    public static function issueCookie(): string {
        $secret = self::fromCookie() ?? bin2hex(random_bytes(32));
        self::setCookie($secret);
        $_COOKIE[self::COOKIE] = $secret;
        return $secret;
    }

    private static function setCookie(string $secret): void {
        setcookie(self::COOKIE, $secret, [
            'expires' => time() + self::LIFETIME,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void {
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function digest(string $secret): string {
        return hash('sha256', $secret);
    }

    public static function passwordProof(array $account): string {
        return hash('sha256', (string)($account['password_hash'] ?? ''));
    }

    private static function userById(array $db, int $userId): ?array {
        foreach ($db['users'] ?? [] as $user) {
            if ((int)($user['id'] ?? 0) === $userId) return $user;
        }
        return null;
    }

    private static function validRecord(array $db, array $entry, string $digest, int $now): ?array {
        if (!is_string($entry['device_hash'] ?? null) ||
            !hash_equals($digest, (string)$entry['device_hash']) ||
            (int)($entry['expires_at'] ?? 0) <= $now) return null;
        $account = self::userById($db, (int)($entry['user_id'] ?? 0));
        if (!$account || !hash_equals(self::passwordProof($account), (string)($entry['password_proof'] ?? ''))) return null;
        if (!in_array((string)($account['role'] ?? ''), AccountService::ROLES, true)) return null;
        return $account;
    }

    /** Only returns accounts explicitly remembered on THIS browser. */
    public static function list(array $db, ?string $secret): array {
        if ($secret === null || !preg_match('/\A[a-f0-9]{64}\z/D', $secret)) return [];
        $digest = self::digest($secret);
        $seen = [];
        $result = [];
        foreach ($db['remembered_accounts'] ?? [] as $entry) {
            if (!is_array($entry)) continue;
            $account = self::validRecord($db, $entry, $digest, time());
            if ($account === null || isset($seen[(int)$account['id']])) continue;
            $seen[(int)$account['id']] = true;
            $result[] = [
                'id' => (int)$account['id'],
                'username' => (string)$account['username'],
                'name' => (string)$account['name'],
                'role' => (string)$account['role'],
            ];
        }
        return $result;
    }

    public static function verify(array $db, ?string $secret, int $userId): ?array {
        if ($userId <= 0 || $secret === null) return null;
        foreach (self::list($db, $secret) as $profile) {
            if ($profile['id'] === $userId) return self::userById($db, $userId);
        }
        return null;
    }

    /** Atomic aggregate update; max 8 accounts per device and 30-day TTL. */
    public static function remember(array &$db, array $account, string $secret): void {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $secret)) throw new DomainException('Mã thiết bị không hợp lệ');
        $now = time(); $digest = self::digest($secret); $uid = (int)$account['id'];
        $entries = [];
        foreach ($db['remembered_accounts'] ?? [] as $entry) {
            if (!is_array($entry) || (int)($entry['expires_at'] ?? 0) <= $now) continue;
            if (hash_equals($digest, (string)($entry['device_hash'] ?? '')) && (int)($entry['user_id'] ?? 0) === $uid) continue;
            $entries[] = $entry;
        }
        $entries[] = [
            'device_hash'=>$digest, 'user_id'=>$uid,
            'password_proof'=>self::passwordProof($account),
            'created_at'=>$now, 'expires_at'=>$now + self::LIFETIME,
        ];
        // Keep the newest MAX_ACCOUNTS entries on this device.
        $deviceIndexes = [];
        foreach ($entries as $i=>$entry) if (hash_equals($digest,(string)($entry['device_hash']??''))) $deviceIndexes[] = $i;
        if (count($deviceIndexes) > self::MAX_ACCOUNTS) {
            $remove = array_slice($deviceIndexes, 0, count($deviceIndexes)-self::MAX_ACCOUNTS);
            foreach ($remove as $i) unset($entries[$i]);
        }
        $db['remembered_accounts'] = array_values($entries);
    }

    public static function forget(array &$db, ?string $secret, int $uid): void {
        if ($secret === null || $uid <= 0) return;
        $digest = self::digest($secret);
        $db['remembered_accounts'] = array_values(array_filter($db['remembered_accounts'] ?? [],
            static fn($entry): bool => !(
                is_array($entry) &&
                hash_equals($digest,(string)($entry['device_hash'] ?? '')) &&
                (int)($entry['user_id'] ?? 0)===$uid
            )
        ));
    }

    public static function revokeAll(array &$db, int $uid): void {
        $db['remembered_accounts'] = array_values(array_filter($db['remembered_accounts'] ?? [],
            static fn($entry): bool => (int)($entry['user_id'] ?? 0) !== $uid
        ));
    }
}
