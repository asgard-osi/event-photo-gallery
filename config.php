<?php
// ===== Site title and subtitle (for index page) =====
$SITE_TITLE    = 'EventGallery';
$SITE_SUBTITLE = 'EventGallery - Photo Upload & Share';

// ===== Gallery password =====
$GALLERY_PASSWORD = 'secret'; // change here

// ===== Additional subfolders inside uploads/ =====
// Add folder names located under uploads/.
// The folder names will be used as section titles in the gallery.
$EXTRA_FOLDERS = [
    // Example:
    'subfolder1',
    //'subfolder2',
    //'subfolder3',
];

// ===== Session lifetime: 72 hours =====
$lifetime = 72 * 60 * 60; // 72h
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params($lifetime, '/', '', $secure, true);
}
ini_set('session.gc_maxlifetime', (string)$lifetime);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// ===== Paths / base directories =====
$BASE_DIR   = __DIR__;
$UPLOAD_DIR = $BASE_DIR . '/uploads';      // Main upload folder
$THUMB_DIR  = $UPLOAD_DIR . '/_thumbs';    // Thumbnails for main folder
$UPLOAD_URL = 'uploads';                   // Web path to main folder (relative to galerie.php)
?>

