<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$uploadDir = '/web/gebarenoverleg_media/studioFilesMini/post/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Never trust the client-supplied name: strip any path, allow only a safe
// character set, and only the extension this endpoint exists for.
$filename = basename((string)($_FILES['file']['name'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_\-]+\.mp4$/', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename: expected <name>.mp4 with letters, digits, _ or -']);
    exit;
}
$uploadFile = $uploadDir . $filename;
if (!is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['error' => 'Failed to save file']);
}
