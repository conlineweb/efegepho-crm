<?php
header('Content-Type: application/json; charset=utf-8');
// Ensure fatal errors return JSON so the frontend can parse them
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err !== null) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Server error', 'details' => $err['message']]);
        exit;
    }
});

// Basic endpoint to accept an uploaded image and return JSON { default: "<public_url>" }
// Security: adjust size limits, checks, and permissions for production
$targetDir = __DIR__ . '/uploads/ckeditor/';
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

if (empty($_FILES['upload']) || !is_uploaded_file($_FILES['upload']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['upload'];
$maxSize = 5 * 1024 * 1024; // 5 MB max
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large']);
    exit;
}

// Determine MIME type safely without requiring the fileinfo extension
$mime = '';
$imgInfo = @getimagesize($file['tmp_name']);
if ($imgInfo && isset($imgInfo['mime'])) {
    $mime = $imgInfo['mime'];
} elseif (!empty($file['type'])) {
    // Fallback to client-provided mime type (less reliable)
    $mime = $file['type'];
}

if (!$mime || !in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('img_') . '.' . $ext;
$dest = $targetDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save file']);
    exit;
}

// Build public URL (works if site is served from the same host)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$url = $protocol . '://' . $host . $path . '/uploads/ckeditor/' . $filename;

echo json_encode(['default' => $url]);
