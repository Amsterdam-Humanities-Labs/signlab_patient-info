<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Token, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

error_reporting(E_ERROR | E_PARSE);

include '../mysql_config.php';

$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }
require_once __DIR__ . '/auth.php';
requireApiToken();   // X-Api-Token / Bearer (HH_API_TOKEN in db_credentials.php) or a portal session

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list_unsegmented':
        listUnsegmented($conn);
        break;
    case 'upload_segments':
        uploadSegments($conn);
        break;
    case 'status':
        segmentStatus($conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action. Use: list_unsegmented, upload_segments, status']);
        break;
}

$conn->close();

// ---------- Endpoint functions ----------

function listUnsegmented($conn) {
    $sql = "SELECT mt.m_file, mt.m_transcription, mt.post_processed,
                   REPLACE(mt.m_file, '.wav', '') AS base_filename
            FROM matched_transcriptions mt
            WHERE mt.zOg = 'tekst'
              AND mt.added = '1'
              AND NOT EXISTS (
                SELECT 1 FROM hh_segments hs
                WHERE hs.base_filename = REPLACE(mt.m_file, '.wav', '')
              )
            ORDER BY mt.ID ASC";

    $result = $conn->query($sql);
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
        return;
    }

    $videos = [];
    while ($row = $result->fetch_assoc()) {
        $base = $row['base_filename'];
        $videos[] = [
            'base_filename'   => $base,
            'm_file'          => $row['m_file'],
            'm_transcription' => (int)$row['m_transcription'],
            'download_url'    => 'https://signcollect.nl/gebarenoverleg_media/studioFilesMini/post/' . $base . '.mp4'
        ];
    }

    echo json_encode([
        'success' => true,
        'videos'  => $videos,
        'total'   => count($videos)
    ]);
}

function uploadSegments($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST method required']);
        return;
    }

    $baseFilename = $_POST['base_filename'] ?? '';
    if (empty($baseFilename)) {
        echo json_encode(['success' => false, 'error' => 'Missing required field: base_filename']);
        return;
    }

    // Sanitize: only allow alphanumeric, underscores, hyphens
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $baseFilename)) {
        echo json_encode(['success' => false, 'error' => 'Invalid base_filename format']);
        return;
    }

    // Validate base_filename exists in matched_transcriptions
    $stmt = $conn->prepare("SELECT ID FROM matched_transcriptions WHERE REPLACE(m_file, '.wav', '') = ? LIMIT 1");
    $stmt->bind_param('s', $baseFilename);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'base_filename not found in matched_transcriptions']);
        return;
    }
    $stmt->close();

    // Check that segment files were uploaded
    if (!isset($_FILES['segments']) || empty($_FILES['segments']['name'][0])) {
        echo json_encode(['success' => false, 'error' => 'No segment files uploaded. Use field name: segments[]']);
        return;
    }

    $saveDir = '/web/gebarenoverleg_media/studioFilesMini/post/';
    if (!is_dir($saveDir)) {
        echo json_encode(['success' => false, 'error' => 'Save directory does not exist']);
        return;
    }

    $files = $_FILES['segments'];
    $fileCount = count($files['name']);
    $maxSize = 100 * 1024 * 1024; // 100 MB per file

    // Validate all files before saving any
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => "Upload error on file $i: code " . $files['error'][$i]]);
            return;
        }
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            echo json_encode(['success' => false, 'error' => "File $i is not an .mp4 file: " . $files['name'][$i]]);
            return;
        }
        if ($files['size'][$i] > $maxSize) {
            echo json_encode(['success' => false, 'error' => "File $i exceeds max size of 100MB"]);
            return;
        }
    }

    // Save files and insert into DB
    $upsertSql = "INSERT INTO hh_segments (base_filename, segment_number, filename, location)
                  VALUES (?, ?, ?, 'post')
                  ON DUPLICATE KEY UPDATE
                    filename = VALUES(filename),
                    location = VALUES(location)";
    $stmt = $conn->prepare($upsertSql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
        return;
    }

    $savedFiles = [];
    $conn->autocommit(false);

    for ($i = 0; $i < $fileCount; $i++) {
        $segNum = $i + 1; // 1-indexed
        $segFilename = $baseFilename . '_' . $segNum . '.mp4';
        $destPath = $saveDir . $segFilename;

        if (!move_uploaded_file($files['tmp_name'][$i], $destPath)) {
            $conn->rollback();
            $conn->autocommit(true);
            $stmt->close();
            echo json_encode(['success' => false, 'error' => "Failed to save file: $segFilename"]);
            return;
        }

        $stmt->bind_param('sis', $baseFilename, $segNum, $segFilename);
        if (!$stmt->execute()) {
            $conn->rollback();
            $conn->autocommit(true);
            $stmt->close();
            echo json_encode(['success' => false, 'error' => 'DB insert failed: ' . $stmt->error]);
            return;
        }

        $savedFiles[] = $segFilename;
    }

    $conn->commit();
    $conn->autocommit(true);
    $stmt->close();

    echo json_encode([
        'success'        => true,
        'base_filename'  => $baseFilename,
        'segments_saved' => count($savedFiles),
        'files'          => $savedFiles
    ]);
}

function segmentStatus($conn) {
    $baseFilename = $_GET['base_filename'] ?? '';
    if (empty($baseFilename)) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: base_filename']);
        return;
    }

    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $baseFilename)) {
        echo json_encode(['success' => false, 'error' => 'Invalid base_filename format']);
        return;
    }

    $stmt = $conn->prepare("SELECT segment_number, filename FROM hh_segments WHERE base_filename = ? ORDER BY segment_number ASC");
    $stmt->bind_param('s', $baseFilename);
    $stmt->execute();
    $result = $stmt->get_result();

    $segments = [];
    while ($row = $result->fetch_assoc()) {
        $segments[] = [
            'segment_number' => (int)$row['segment_number'],
            'filename'       => $row['filename']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success'       => true,
        'base_filename' => $baseFilename,
        'has_segments'  => count($segments) > 0,
        'segment_count' => count($segments),
        'segments'      => $segments
    ]);
}
?>
