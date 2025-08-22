<?php
require_once __DIR__ . '/config.php'; // <<< NEU: Session-Setup mit 72h

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim($_POST['author'] ?? '');
    if ($author !== '') {
        $_SESSION['author'] = $author;
        echo json_encode(['ok' => true, 'author' => $author]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'empty']);
    exit;
}

// GET: aktuellen Autor zurückgeben (oder null)
$author = $_SESSION['author'] ?? null;
echo json_encode(['ok' => true, 'author' => $author]);

?>
