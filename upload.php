<?php
$uploadDir = __DIR__ . "/uploads/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$isAjax     = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$allowed    = ['jpg','jpeg','png','gif','webp'];
$uploadedCount = 0;
$errors = [];

$author = isset($_POST['author']) ? trim($_POST['author']) : '';
$author = str_replace(["\r","\n",";"], ' ', $author); // für CSV

$authorsCsv = $uploadDir . '_authors.csv';

if (!empty($_FILES['photos']['tmp_name'])) {
    foreach ($_FILES['photos']['tmp_name'] as $index => $tmpName) {
        $err  = $_FILES['photos']['error'][$index];
        $orig = $_FILES['photos']['name'][$index] ?? 'unbekannt';

        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
            $errors[] = ['file' => $orig, 'error' => $err];
            continue;
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $errors[] = ['file' => $orig, 'error' => 'unsupported_type'];
            continue;
        }

        $safeName = preg_replace('/[^\w\-.]+/u', '_', basename($orig));
        $target   = $uploadDir . date('Ymd_His') . '_' . uniqid('', true) . '_' . $safeName;

        if (move_uploaded_file($tmpName, $target)) {
            $uploadedCount++;

            // Autorzeile protokollieren: gespeicherter Dateiname (basename!), Autor, Zeit
            $line = basename($target) . ';' . ($author ?: '') . ';' . date('c') . PHP_EOL;
            @file_put_contents($authorsCsv, $line, FILE_APPEND | LOCK_EX);
        } else {
            $errors[] = ['file' => $orig, 'error' => 'move_failed'];
        }
    }
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['uploaded' => $uploadedCount, 'errors' => $errors]);
    exit;
}

header("Location: index.html?upload=success");
exit;
?>
