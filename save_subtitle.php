<?php
require_once __DIR__ . '/auth.php';
$currentUser = requireAuthApi();   // portal session cookie required

require_once __DIR__ . '/db_config.php';   // DB_* constants, from /web/.env via signcollect-lib
/**
 * Save subtitle timing data
 * This script receives timing data for each line displayed in the autocue
 * and saves it to the subtitles database table.
 */

// Define constants
define('LOG_FILE', 'subtitle_timings.log');

// DB_HOST / DB_USER / DB_PASS / DB_NAME are defined by db_config.php.

// Ensure we have POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $lineIndex = isset($_POST['index']) ? intval($_POST['index']) : null;
    $lineText = isset($_POST['text']) ? $_POST['text'] : '';
    $startTime = isset($_POST['startTime']) ? $_POST['startTime'] : '';
    $endTime = isset($_POST['endTime']) ? $_POST['endTime'] : '';
    $contentId = isset($_POST['contentId']) ? $_POST['contentId'] : 'unknown';
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'toggle';
    $take = isset($_POST['take']) ? $_POST['take'] : generateTakeId($contentId); // Get take ID or generate one
    
    // Validate required data
    if ($lineIndex !== null && $startTime && $endTime) {
        try {
            // Convert ISO timestamps to Unix timestamp (seconds)
            $startTimeDouble = convertIsoToTimestamp($startTime);
            $endTimeDouble = convertIsoToTimestamp($endTime);
            
            // Save to database
            saveToDatabase($contentId, $lineText, $startTimeDouble, $endTimeDouble, $mode, $take);
            
            // Log the action
            logAction("Saved timing data for content ID $contentId, line $lineIndex, take $take");
            
            // Send success response
            echo json_encode(['status' => 'success', 'message' => 'Timing data saved to database', 'take' => $take]);
        } catch (Exception $e) {
            // Send error response for database issues
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            logAction("Database error: " . $e->getMessage());
        }
    } else {
        // Send error response for missing data
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
        logAction("Error: Missing required data in request");
    }
} else {
    // Send error response for invalid request method
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    logAction("Error: Invalid request method");
}

/**
 * Generate a unique take ID using content ID and timestamp
 * 
 * @param string $contentId Content ID
 * @return string Take ID
 */
function generateTakeId($contentId) {
    $timestamp = date('Y-m-d H:i:s');
    $randomStr = substr(md5(uniqid(rand(), true)), 0, 8);
    $source = $contentId . $timestamp . $randomStr;
    return 'take-' . md5($source);
}

/**
 * Convert ISO datetime string to Unix timestamp (seconds)
 * 
 * @param string $isoDate ISO timestamp
 * @return float Unix timestamp with microseconds
 */
function convertIsoToTimestamp($isoDate) {
    $dateTime = new DateTime($isoDate);
    return (float) $dateTime->format('U.u');
}

/**
 * Connect to the database
 * 
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function connectToDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

/**
 * Save subtitle data to the database
 * 
 * @param string $contentId Content ID
 * @param string $lineText Line text content
 * @param float $startTime Start time in seconds
 * @param float $endTime End time in seconds
 * @param string $mode Mode value (toggle, scroll, line)
 * @param string $take Take ID to group related subtitles
 * @throws Exception If database operation fails
 */
function saveToDatabase($contentId, $lineText, $startTime, $endTime, $mode, $take) {
    $conn = connectToDatabase();
    
    // Prepare statement - now includes the take field
    $stmt = $conn->prepare("INSERT INTO subtitles (content_id, line_text, start_time, end_time, mode, take) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Limit take ID to 255 characters (varchar limit)
    $take = substr($take, 0, 255);
    
    // Bind parameters - added 's' for the new string parameter (take)
    $contentIdInt = is_numeric($contentId) ? (int)$contentId : 0;
    $stmt->bind_param("isddss", $contentIdInt, $lineText, $startTime, $endTime, $mode, $take);
    
    // Execute statement
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Close statement and connection
    $stmt->close();
    $conn->close();
}

/**
 * Log an action to the log file
 * 
 * @param string $message Message to log
 */
function logAction($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}
?>
