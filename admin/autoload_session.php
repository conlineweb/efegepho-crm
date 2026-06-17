<?php
/**
 * autoload_session.php
 * Include this file at the top of protected pages to automatically restore session
 * from remember cookie when the session is missing.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure DB connection is available
if (!isset($conn) || !$conn instanceof mysqli) {
    require_once __DIR__ . '/conn.php';
}
require_once __DIR__ . '/persistent_login.php';

// If user already has session, nothing to do
if (!empty($_SESSION['uid'])) {
    return;
}

// Check remember cookie
if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
    $cookieValue = $_COOKIE[REMEMBER_COOKIE_NAME];
    $userId = consumeRememberToken($cookieValue, $conn);
    if ($userId !== false && $userId !== null) {
        // reconstruct session (fetch user minimal info)
        $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            if ($user) {
                // set session minimally but you can set any fields needed
                $_SESSION['uid'] = (int)$user['id'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['tus'] = $user['tipoUsu'];
                // optional: add more session fields like $user['nombre']
            }
        }
    } else {
        // Clear the cookie if invalid
        clearRememberCookie();
    }
}

?>
