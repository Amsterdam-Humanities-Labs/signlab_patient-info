<?php
require_once __DIR__ . '/db_credentials.php';
// disable php warnings
error_reporting(E_ERROR | E_PARSE);

// MySQL connection details
$db_config = [
  'host'     => 'localhost',
  'user'     => 'user',
  'password' => DB_PASSWORD,
  'database' => 'admin_gebarenoverleg'
];

// Create database connection
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Set character encoding
$conn->set_charset('utf8mb4');

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_transcriptions') {
    // Get all m_transcriptions grouped with first line of plain_text
    $sql = "SELECT mt.m_transcription, COUNT(*) AS count, hi.plain_text, hi.id AS content_id, hi.labels
            FROM matched_transcriptions mt
            LEFT JOIN hh_index hi ON mt.m_transcription = hi.id
            WHERE mt.zOg = 'tekst' AND mt.added != 'DELETE'
            GROUP BY mt.m_transcription
            ORDER BY mt.m_transcription ASC";
    
    $result = $conn->query($sql);
    
    $transcriptions = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Get only the first line of plain_text
            if (!empty($row['plain_text'])) {
                $lines = explode("\n", $row['plain_text']);
                $row['first_line'] = trim($lines[0]);
                
                // If first line is too short, add more content
                if (strlen($row['first_line']) < 30 && count($lines) > 1) {
                    $row['first_line'] .= " " . trim($lines[1]);
                }
                
                // Truncate if too long
                if (strlen($row['first_line']) > 100) {
                    $row['first_line'] = substr($row['first_line'], 0, 97) . '...';
                }
            } else {
                $row['first_line'] = $row['m_transcription'];  // Fallback if no plain_text
            }
            
            $transcriptions[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success', 
        'data' => $transcriptions
    ]);
}
else if ($action === 'get_videos_for_transcription') {
    $transcription = isset($_GET['transcription']) ? $_GET['transcription'] : '';
    $includeDeleted = isset($_GET['include_deleted']) && $_GET['include_deleted'] === 'true';
    
    if (empty($transcription)) {
        echo json_encode(['error' => 'Transcription is required']);
        exit;
    }
    
    // Get content data from hh_index
    $contentSql = "SELECT hi.id AS content_id, hi.plain_text 
                   FROM hh_index hi 
                   WHERE hi.id = ?";
                   
    $contentStmt = $conn->prepare($contentSql);
    $contentStmt->bind_param("s", $transcription);
    $contentStmt->execute();
    
    $contentResult = $contentStmt->get_result();
    $contentData = null;
    
    if ($contentResult->num_rows > 0) {
        $contentData = $contentResult->fetch_assoc();
    }
    
    // Build query for videos, including deleted if requested
    $sql = "SELECT id, m_file, m_transcription, added 
            FROM matched_transcriptions 
            WHERE m_transcription = ? AND zOg = 'tekst'";
            
    if (!$includeDeleted) {
        $sql .= " AND added != 'DELETE'";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $transcription);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $videos = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Convert m_file to video URL
            $videoFile = $row['m_file'];
            $videoFile = str_replace('.wav', '.mp4', $videoFile);
            $row['video_url'] = "https://media.signcollect.nl/" . $videoFile;
            
            $videos[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success', 
        'data' => $videos,
        'content' => $contentData
    ]);
}
else if ($action === 'mark_as_deleted') {
    // Mark other videos with same m_transcription as deleted
    $selected_id = isset($_POST['selected_id']) ? intval($_POST['selected_id']) : 0;
    $transcription = isset($_POST['transcription']) ? $_POST['transcription'] : '';
    
    if ($selected_id <= 0 || empty($transcription)) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    // Update all videos with this transcription except the selected one
    $sql = "UPDATE matched_transcriptions 
            SET added = 'DELETE' 
            WHERE m_transcription = ? AND id != ? AND zOg = 'tekst'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $transcription, $selected_id);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Other videos marked as deleted successfully'
        ]);
    } else {
        echo json_encode([
            'error' => 'Failed to update records: ' . $conn->error
        ]);
    }
}
else if ($action === 'restore_deleted') {
    // Restore previously deleted videos
    $transcription = isset($_POST['transcription']) ? $_POST['transcription'] : '';
    
    if (empty($transcription)) {
        echo json_encode(['error' => 'Transcription is required']);
        exit;
    }
    
    // Update all deleted videos with this transcription to normal state
    $sql = "UPDATE matched_transcriptions 
            SET added = 1 
            WHERE m_transcription = ? AND zOg = 'tekst' AND added = 'DELETE'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $transcription);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Deleted videos have been restored'
        ]);
    } else {
        echo json_encode([
            'error' => 'Failed to restore records: ' . $conn->error
        ]);
    }
}
else if ($action === 'restore_single_video') {
    // Restore a single deleted video
    $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
    $transcription = isset($_POST['transcription']) ? $_POST['transcription'] : '';
    
    if ($video_id <= 0 || empty($transcription)) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    // Update this specific video to normal state
    $sql = "UPDATE matched_transcriptions 
            SET added = 1 
            WHERE id = ? AND m_transcription = ? AND zOg = 'tekst'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $video_id, $transcription);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Video has been restored'
        ]);
    } else {
        echo json_encode([
            'error' => 'Failed to restore video: ' . $conn->error
        ]);
    }
}
else if ($action === 'delete_single_video') {
    // Delete a single video
    $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
    $transcription = isset($_POST['transcription']) ? $_POST['transcription'] : '';
    
    if ($video_id <= 0 || empty($transcription)) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    // Update this specific video to deleted state
    $sql = "UPDATE matched_transcriptions 
            SET added = 'DELETE' 
            WHERE id = ? AND m_transcription = ? AND zOg = 'tekst'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $video_id, $transcription);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Video has been deleted'
        ]);
    } else {
        echo json_encode([
            'error' => 'Failed to delete video: ' . $conn->error
        ]);
    }
}
else {
    echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>