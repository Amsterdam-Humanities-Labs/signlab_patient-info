<?php
require_once __DIR__ . '/auth.php';
$currentUser = requireAuthApi();   // portal session cookie required

require_once __DIR__ . '/db_config.php';   // $db_config, from /web/.env via signcollect-lib
// get_begrippen.php
//disable php warnings
error_reporting(E_ERROR | E_PARSE);

// MySQL connection details - all four come from db_config.php.

$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 25; // Default perPage

if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $perPage;

header('Content-Type: application/json; charset=utf-8');

$whereClause = '';
if (!empty($search)) {
  // Search in both glos and text
  $whereClause = "WHERE hig.glos LIKE '%$search%' OR hig.text LIKE '%$search%'";
}

// Fetch all matching entries first, ordered for grouping
$query = "SELECT hig.id as glos_id, hig.glos, hig.text, hi.id as content_id, hi.url
          FROM hh_index_glos hig
          LEFT JOIN hh_index hi ON hig.hh_index_id = hi.id
          $whereClause
          ORDER BY hig.glos ASC, hig.text ASC";

$result = $conn->query($query);
$allRows = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $allRows[] = $row;
  }
}

// Group results by glos and text in PHP
$groupedData = [];
foreach ($allRows as $row) {
  $key = $row['glos'] . '||' . $row['text']; // Unique key for grouping

  if (!isset($groupedData[$key])) {
    $groupedData[$key] = [
      'glos' => $row['glos'],
      'text' => $row['text'],
      'original_glos' => $row['glos'],
      'original_text' => $row['text'],
      'sources' => [],
      'videoTop' => [] // Initialize videoTop array
    ];
  }

  // Add source if content_id exists, include glos_id
  if ($row['content_id']) {
    $groupedData[$key]['sources'][] = [
      'glos_id' => $row['glos_id'],
      'content_id' => $row['content_id'],
      'url' => $row['url']
    ];
  } else {
     $groupedData[$key]['sources'][] = [
         'glos_id' => $row['glos_id'],
         'content_id' => null,
         'url' => null
     ];
  }
}

// Prepare statement for fetching videoTop
$videoTopStmt = $conn->prepare("SELECT videoTop FROM CameraRecords WHERE id = ? AND zOg = 'begrip'");
if (!$videoTopStmt) {
    // Handle prepare error if needed
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

// Fetch videoTop for each group
foreach ($groupedData as $key => &$group) {
    $videoTopArray = [];
    $processedGlosIds = []; // Keep track of processed glos_ids to avoid duplicates per group

    foreach ($group['sources'] as $source) {
        $glosId = $source['glos_id'];

        // Check if this glos_id was already processed for this group
        if ($glosId !== null && !in_array($glosId, $processedGlosIds)) {
            $videoTopStmt->bind_param("i", $glosId);
            $videoTopStmt->execute();
            $videoResult = $videoTopStmt->get_result();

            while ($videoRow = $videoResult->fetch_assoc()) {
                if (!empty($videoRow['videoTop'])) {
                    // Decode JSON string if it's stored as JSON
                    $decodedVideoTop = json_decode($videoRow['videoTop'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedVideoTop)) {
                        // If it's an array, merge it
                        $videoTopArray = array_merge($videoTopArray, $decodedVideoTop);
                    } else {
                        // Otherwise, add the string value
                        $videoTopArray[] = $videoRow['videoTop'];
                    }
                }
            }
            $processedGlosIds[] = $glosId; // Mark as processed for this group
        }
    }
    // Add the unique videoTop entries to the group
    $group['videoTop'] = array_values(array_unique($videoTopArray));
}
unset($group); // Unset reference

$videoTopStmt->close();

// Convert grouped data to indexed array
$finalGroupedData = array_values($groupedData);

// Calculate total based on grouped data
$total = count($finalGroupedData);

// Apply pagination to the grouped data
$paginatedData = array_slice($finalGroupedData, $offset, $perPage);

echo json_encode([
  'data' => $paginatedData,
  'total' => $total,
  'page' => $page,
  'perPage' => $perPage
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
