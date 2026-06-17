<?php
    session_start();
    // Include DB connection and persistent login functions
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/persistent_login.php';

    // If cookie exists, remove associated token from DB
    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $cookie = $_COOKIE[REMEMBER_COOKIE_NAME];
        $parts = explode(':', $cookie, 2);
        if (count($parts) === 2) {
            deleteRememberTokenBySelector($parts[0], $conn);
        }
    }

    // Optionally: delete all tokens for this user
    if (!empty($_SESSION['uid'])) {
        deleteAllRememberTokensForUser((int)$_SESSION['uid'], $conn);
    }

    session_unset();
    session_destroy();
    clearRememberCookie();
?>
<!DOCTYPE html>
<html>
<head>
<script>
    // Limpiar localStorage al cerrar sesión
    localStorage.removeItem('username');
    localStorage.removeItem('password');
    // Redirigir a la página de ingreso
    window.location.href = 'ingreso.php';
</script>
</head>
<body></body>
</html>
?>