<?php
/**
 * persistent_login.php
 * Functions for a secure "remember me" persistent login implementation using MySQLi.
 * - Uses random_bytes() to generate tokens
 * - Uses MySQLi prepared statements (no PDO anywhere)
 * - Stores a selector and a SHA-256 hash of validator in DB
 * - Cookie format: selector:validator (plain), where validator is never stored raw in DB
 * - Cookie is HttpOnly and persistent
 */

declare(strict_types=1);

// Ensure conn.php exists in the same directory as other admin scripts
if (!isset($conn) || !$conn instanceof mysqli) {
    require_once __DIR__ . '/conn.php';
}

// Cookie and token settings
// Use a custom cookie name and 99 years expiry as requested
define('REMEMBER_COOKIE_NAME', 'remember_token_efege');
define('REMEMBER_EXPIRE_YEARS', 99); // 99 years cookie expiry
define('REMEMBER_SELECTOR_BYTES', 9); // 9 bytes -> 18 hex
define('REMEMBER_VALIDATOR_BYTES', 33); // 33 bytes -> 66 hex

/**
 * Generate selector and validator tokens
 * @return array [selector, validator]
 */
function generate_token_parts()
{
    $selector = bin2hex(random_bytes(REMEMBER_SELECTOR_BYTES));
    $validator = bin2hex(random_bytes(REMEMBER_VALIDATOR_BYTES));
    return [$selector, $validator];
}

/**
 * Create and store a persistent login token for a user and set the cookie.
 * @param int $userId
 * @param mysqli $conn
 * @return bool True on success
 */
function createRememberToken(int $userId, mysqli $conn): bool
{
    [$selector, $validator] = generate_token_parts();
    $tokenHash = hash('sha256', $validator);
    // Compute expiry date using DateTime for better clarity and to handle leap years
    $dt = new DateTime();
    $dt->modify('+' . REMEMBER_EXPIRE_YEARS . ' years');
    $expiresAt = $dt->format('Y-m-d H:i:s');

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // prepared statement insertion
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, selector, token_hash, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('PersistentLogin: prepare failed - ' . $conn->error);
        return false;
    }
    $stmt->bind_param('isssss', $userId, $selector, $tokenHash, $expiresAt, $ip, $userAgent);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        error_log('PersistentLogin: execute failed - ' . $conn->error);
        return false;
    }

    // Set cookie with $selector:$validator
    $cookieValue = $selector . ':' . $validator;
    // Set cookie with expiration time as a unix timestamp
    return setRememberCookie($cookieValue, $dt->getTimestamp());
}

/**
 * Sets the HttpOnly cookie with persistent expiration
 */
function setRememberCookie(string $cookieValue, int $expiresAt): bool
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    // For compatibility, prefer the options array if available (PHP 7.3+)
    $cookieName = REMEMBER_COOKIE_NAME;
    $options = [
        'expires' => $expiresAt,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    if (PHP_VERSION_ID >= 70300) {
        return setcookie($cookieName, $cookieValue, $options);
    }
    // fallback
    $secureFlag = $secure ? 'Secure;' : '';
    $cookieString = urlencode($cookieName) . '=' . urlencode($cookieValue) . '; Expires=' . gmdate('D, d-M-Y H:i:s T', $expiresAt) . '; Path=/; Domain=' . ($_SERVER['HTTP_HOST'] ?? '') . '; HttpOnly; ' . $secureFlag . 'SameSite=Lax';
    header('Set-Cookie: ' . $cookieString, false);
    return true;
}

/**
 * Parse cookie value which is expected as selector:validator
 * @return array|false [selector, validator] or false on error
 */
function parseRememberCookie(string $cookie)
{
    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) {
        return false;
    }
    return [$parts[0], $parts[1]];
}

/**
 * Fetch token DB record by selector.
 * @return array|null - associative array details or null if not found
 */
function findTokenBySelector(string $selector, mysqli $conn)
{
    $stmt = $conn->prepare('SELECT id, user_id, token_hash, expires_at FROM user_tokens WHERE selector = ? LIMIT 1');
    if (!$stmt) {
        error_log('PersistentLogin: prepare failed - ' . $conn->error);
        return null;
    }
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Delete token by selector
 */
function deleteRememberTokenBySelector(string $selector, mysqli $conn): bool
{
    $stmt = $conn->prepare('DELETE FROM user_tokens WHERE selector = ?');
    if (!$stmt) return false;
    $stmt->bind_param('s', $selector);
    $ok = $stmt->execute();
    $stmt->close();
    clearRememberCookie();
    return $ok;
}

/**
 * Delete all tokens for a user (e.g. logout from all devices)
 */
function deleteAllRememberTokensForUser(int $userId, mysqli $conn): bool
{
    $stmt = $conn->prepare('DELETE FROM user_tokens WHERE user_id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();
    clearRememberCookie();
    return $ok;
}

/**
 * Clear the remember cookie
 */
function clearRememberCookie(): void
{
    $cookieName = REMEMBER_COOKIE_NAME;
    if (PHP_VERSION_ID >= 70300) {
        setcookie($cookieName, '', ['expires' => time() - 3600, 'path' => '/', 'domain' => ($_SERVER['HTTP_HOST'] ?? ''), 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']);
    } else {
        setcookie($cookieName, '', time() - 3600, '/');
    }
    // Also expunged via header for safe fallback
    header('Set-Cookie: ' . urlencode($cookieName) . '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; Domain=' . ($_SERVER['HTTP_HOST'] ?? '') . '; HttpOnly; SameSite=Lax', false);
}

/**
 * Validate a cookie value (selector:validator), restore session, rotate token and return user id.
 * Returns int user_id on success, or false on failure. Uses token rotation to issue a new validator.
 */
function consumeRememberToken(string $cookieValue, mysqli $conn)
{
    $parsed = parseRememberCookie($cookieValue);
    if ($parsed === false) {
        clearRememberCookie();
        return false;
    }
    [$selector, $validator] = $parsed;
    $row = findTokenBySelector($selector, $conn);
    if (!$row) {
        clearRememberCookie();
        return false;
    }
    // Check expiration
    if (strtotime($row['expires_at']) < time()) {
        // token was expired -> delete it
        deleteRememberTokenBySelector($selector, $conn);
        return false;
    }
    $expectedHash = $row['token_hash'];
    $givenHash = hash('sha256', $validator);
    // timing-attack safe comparison
    if (!hash_equals($expectedHash, $givenHash)) {
        // attacker possibly reusing a stolen token -> remove this token
        deleteRememberTokenBySelector($selector, $conn);
        return false;
    }

    // Valid token; rotate validator for security
    $newValidator = bin2hex(random_bytes(REMEMBER_VALIDATOR_BYTES));
    $newValidatorHash = hash('sha256', $newValidator);
    $dt2 = new DateTime();
    $dt2->modify('+' . REMEMBER_EXPIRE_YEARS . ' years');
    $newExpiresAt = $dt2->format('Y-m-d H:i:s');

    $updateStmt = $conn->prepare('UPDATE user_tokens SET token_hash = ?, expires_at = ?, last_used_at = NOW() WHERE id = ?');
    if (!$updateStmt) {
        error_log('PersistentLogin: update prepare failed - ' . $conn->error);
        return false;
    }
    $updateStmt->bind_param('ssi', $newValidatorHash, $newExpiresAt, $row['id']);
    $ok = $updateStmt->execute();
    $updateStmt->close();
    if (!$ok) {
        error_log('PersistentLogin: update execute failed - ' . $conn->error);
        return false;
    }

    // Set cookie with same selector and new validator
    $cookieNew = $selector . ':' . $newValidator;
    setRememberCookie($cookieNew, $dt2->getTimestamp());

    return (int)$row['user_id'];
}

?>
