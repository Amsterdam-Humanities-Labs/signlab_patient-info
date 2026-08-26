<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$uploadDir = '/web/gebarenoverleg_media/studioFilesMini/raw/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = $_FILES['file']['name'];
$uploadFile = $uploadDir . $filename;

if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['error' => 'Failed to save file']);
}
