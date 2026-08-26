<?php
require_once __DIR__ . '/db_credentials.php';
// api.php
//disable php warnings
error_reporting(E_ERROR | E_PARSE);
// MySQL connection details
$db_config = [
  'host'     => 'localhost',
  'user'     => 'user',
  'password' => DB_PASSWORD,
  'database' => 'admin_gebarenoverleg'
];

$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 to match collation (utf8mb4_general_ci)
$conn->set_charset("utf8mb4");

$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$order = isset($_GET['order']) ? $_GET['order'] : 'cnt_desc';

if ($page < 1) { $page = 1; }
$perPage = 25;
$offset = ($page - 1) * $perPage;

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
  case 'dashboard':
    $indexCount = $conn->query("SELECT COUNT(*) as count FROM hh_index")->fetch_assoc()['count'];
    $sentencesCount = $conn->query("SELECT COUNT(*) as count FROM hh_sentences")->fetch_assoc()['count'];
    $wordsCount = $conn->query("SELECT COUNT(*) as count FROM hh_words")->fetch_assoc()['count'];
    
    // Add unique lemma count
    $uniqueLemmasCount = $conn->query("SELECT COUNT(DISTINCT lemma) as count FROM hh_lemma")->fetch_assoc()['count'];
    
    $data = [
      'hh_index'     => $indexCount,
      'hh_sentences' => $sentencesCount,
      'hh_words'     => $wordsCount,
      'unique_lemmas' => $uniqueLemmasCount
    ];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    break;

  case 'keywords':
    // Get top keywords and their counts
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10000;
    
    // First, get all keywords from JSON arrays and count their occurrences
    $result = $conn->query("SELECT keywords FROM hh_index WHERE keywords IS NOT NULL AND keywords != '[]'");
    
    $keywordCounts = [];
    while ($row = $result->fetch_assoc()) {
      // Fix: Make sure to properly decode and validate the JSON before accessing elements
      $keywordsJson = $row['keywords'];
      $keywordsArray = json_decode($keywordsJson, true);
      
      // Make sure $keywordsArray is actually an array before proceeding
      if (is_array($keywordsArray)) {
        foreach ($keywordsArray as $keyword) {
          // Make sure each keyword is a non-empty string
          if (is_string($keyword) && trim($keyword) !== '') {
            $keyword = trim($keyword); // Normalize by trimming whitespace
            if (array_key_exists($keyword, $keywordCounts)) {
              $keywordCounts[$keyword]++;
            } else {
              $keywordCounts[$keyword] = 1;
            }
          }
        }
      }
    }
    
    // Sort by count (descending)
    arsort($keywordCounts);
    
    // Take only the top keywords based on limit
    $topKeywords = array_slice($keywordCounts, 0, $limit, true);
    
    // Format for response
    $formattedKeywords = [];
    foreach ($topKeywords as $keyword => $count) {
      $formattedKeywords[] = [
        'keyword' => $keyword,
        'count' => $count
      ];
    }
    
    echo json_encode([
      'keywords' => $formattedKeywords,
      'total' => count($keywordCounts)
    ], JSON_UNESCAPED_UNICODE);
    break;

    case 'contents':
      $whereClause = '';
      $whereConditions = [];
      $ngt_not_captured = isset($_GET['ngt_not_captured']) ? intval($_GET['ngt_not_captured']) : 0;
    
      // Add condition if ngt_not_captured is set (non-zero)
      if ($ngt_not_captured) {
        $whereConditions[] = "ngt_text IS NOT NULL";
      }
    
      if (!empty($search)) {
        $whereConditions[] = "(url LIKE '%$search%' OR plain_text LIKE '%$search%')";
      }
      
      // Add support for keyword search (single or multiple keywords)
      if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
        $keywords = explode(',', $conn->real_escape_string($_GET['keyword']));
        $keywordConditions = [];
        
        foreach ($keywords as $keyword) {
          $keyword = trim($keyword);
          if (!empty($keyword)) {
            $keywordConditions[] = "JSON_SEARCH(keywords, 'one', '$keyword') IS NOT NULL";
          }
        }
        
        if (!empty($keywordConditions)) {
          $whereConditions[] = "(" . implode(" AND ", $keywordConditions) . ")";
        }
      }
      
      // Add status filter
      if (isset($_GET['status']) && $_GET['status'] !== '') {
        $status = intval($_GET['status']);
        if ($status === 1) {
          $whereConditions[] = "status = 1"; // Klaar
        } else if ($status === 2) {
          $whereConditions[] = "status = 2"; // Video goedgekeurd
        } else if ($status === 0) {
          $whereConditions[] = "(status IS NULL OR status = 0)"; // Niet Klaar
        }
      }

      // Add label filter
      if (isset($_GET['label']) && !empty($_GET['label'])) {
        $label = $conn->real_escape_string($_GET['label']);
        $whereConditions[] = "JSON_SEARCH(labels, 'one', '$label') IS NOT NULL";
      }

      // Combine conditions with AND
      if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
      }
      
      // Add sorting functionality
      $sortField = isset($_GET['sort']) ? $_GET['sort'] : 'id';
      $sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
      
      // Validate sort field to prevent SQL injection
      $allowedSortFields = ['id', 'priority'];
      if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'id';
      }
      
      // Build the ORDER BY clause
      $orderByClause = "ORDER BY $sortField $sortOrder";
      
      $result = $conn->query("SELECT * FROM hh_index $whereClause $orderByClause LIMIT $offset, $perPage");
      $rows = [];
      while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
      }
      
      $totalQuery = "SELECT COUNT(*) as total FROM hh_index $whereClause";
      $total = $conn->query($totalQuery)->fetch_assoc()['total'];
      
      $data = [
        'data'    => $rows,
        'total'   => $total,
        'page'    => $page,
        'perPage' => $perPage
      ];
      echo json_encode($data, JSON_UNESCAPED_UNICODE);
      break;
    
  case 'content_extended':
    $whereClause = '';
    $whereConditions = [];
    $ngt_not_captured = isset($_GET['ngt_not_captured']) ? intval($_GET['ngt_not_captured']) : 0;
  
    // Add condition if ngt_not_captured is set (non-zero)
    if ($ngt_not_captured) {
      $whereConditions[] = "(ngt_text IS NOT NULL OR ngt_text2 IS NOT NULL OR ngt_text3 IS NOT NULL OR ngt_text4 IS NOT NULL) AND status IS NULL";
    }
  
    if (!empty($search)) {
      $whereConditions[] = "(url LIKE '%$search%' OR plain_text LIKE '%$search%')";
    }
    
    // Add support for keyword search (single or multiple keywords)
    if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
      $keywords = explode(',', $conn->real_escape_string($_GET['keyword']));
      $keywordConditions = [];
      
      foreach ($keywords as $keyword) {
        $keyword = trim($keyword);
        if (!empty($keyword)) {
          $keywordConditions[] = "JSON_SEARCH(keywords, 'one', '$keyword') IS NOT NULL";
        }
      }
      
      if (!empty($keywordConditions)) {
        $whereConditions[] = "(" . implode(" AND ", $keywordConditions) . ")";
      }
    }
    
    // Add status filter
    if (isset($_GET['status']) && $_GET['status'] !== '') {
      $status = intval($_GET['status']);
      if ($status === 1) {
        $whereConditions[] = "status = 1"; // Klaar
      } else if ($status === 0) {
        $whereConditions[] = "(status IS NULL OR status = 0)"; // Niet Klaar
      }
    }
    
    // Combine conditions with AND
    if (!empty($whereConditions)) {
      $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Add sorting functionality
    $sortField = isset($_GET['sort']) ? $_GET['sort'] : 'id';
    $sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
    
    // Validate sort field to prevent SQL injection
    $allowedSortFields = ['id', 'priority'];
    if (!in_array($sortField, $allowedSortFields)) {
      $sortField = 'id';
    }
    
    // Build the ORDER BY clause
    $orderByClause = "ORDER BY $sortField $sortOrder";
    
    $result = $conn->query("SELECT * FROM hh_index $whereClause $orderByClause");
    $rows = [];
    
    // Transform the data into the extended format
    while ($row = $result->fetch_assoc()) {
      // Create base record with common properties
      $baseRecord = [
        'keywords' => $row['keywords'],
        'labels' => $row['labels'],
        'priority' => $row['priority'],
        'status' => $row['status'],
        'url' => $row['url'] . '_1',
        'plain_text' => $row['plain_text'],

      ];
      
      // First entry with ngt_text
      if (!empty($row['ngt_text'])) {
        $rows[] = array_merge($baseRecord, [
          'id' => $row['id'],
          'plain_text' => $row['ngt_text'],
            'url' => $row['url'] . '_1'

        ]);
      }
      
      // Second entry with ngt_text2
      if (!empty($row['ngt_text2'])) {
        $rows[] = array_merge($baseRecord, [
          'id' => $row['id'] . '_2',
          'plain_text' => $row['ngt_text2'],
          'url' => $row['url'] . '_2'
        ]);
      }
      
      // Third entry with ngt_text3
      if (!empty($row['ngt_text3'])) {
        $rows[] = array_merge($baseRecord, [
          'id' => $row['id'] . '_3',
          'plain_text' => $row['ngt_text3'],
          'url' => $row['url'] . '_3'
        ]);
      }
      
      // Fourth entry with ngt_text4
      if (!empty($row['ngt_text4'])) {
        $rows[] = array_merge($baseRecord, [
          'id' => $row['id'] . '_4',
          'plain_text' => $row['ngt_text4'],
          'url' => $row['url'] . '_4'
        ]);
      }
      
      // If no NGT texts exist, include the original record with empty NGT fields
      if (empty($row['ngt_text']) && empty($row['ngt_text2']) && 
          empty($row['ngt_text3']) && empty($row['ngt_text4'])) {
        $rows[] = array_merge($baseRecord, [
          'id' => $row['id']
        ]);
      }
    }
    
    // Get total count of original records
    $totalQuery = "SELECT COUNT(*) as total FROM hh_index $whereClause";
    $total = $conn->query($totalQuery)->fetch_assoc()['total'];
    
    $data = [
      'data'    => $rows,
      'total'   => $total,  // This remains the count of original records
      'page'    => $page,
      'perPage' => $perPage
    ];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    break;

  case 'words':
    // Update search clause to reference table alias "w"
    $whereClause = '';
    if (!empty($search)) {
      $whereClause = "WHERE w.word LIKE '%$search%'";
    }
    
    // Update order clause to order by lemma when requested
    $orderByClause = 'ORDER BY cnt DESC';
    switch ($order) {
      case 'cnt_asc':
        $orderByClause = 'ORDER BY cnt ASC';
        break;
      case 'word_asc':
        $orderByClause = 'ORDER BY w.lemma ASC';
        break;
      case 'word_desc':
        $orderByClause = 'ORDER BY w.lemma DESC';
        break;
    }
    
    // Join hh_words (alias w) with hh_lemma (alias l) on lemma
    $query = "SELECT 
      w.lemma, 
      MAX(l.video) AS video, 
      MAX(l.video_id) AS video_id, 
      MAX(l.origin) AS origin, 
      COUNT(*) AS cnt 
    FROM hh_words w
    JOIN hh_lemma l ON w.lemma = l.lemma 
    $whereClause 
    GROUP BY w.lemma 
    $orderByClause 
    LIMIT $offset, $perPage";
  
    $result = $conn->query($query);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    
    $totalQuery = "SELECT COUNT(DISTINCT w.lemma) as total FROM hh_words w JOIN hh_lemma l ON w.lemma = l.lemma $whereClause";
    $total = $conn->query($totalQuery)->fetch_assoc()['total'];
    
    $maxQuery = "SELECT MAX(cnt) as maxCount FROM (
      SELECT COUNT(*) as cnt 
      FROM hh_words w 
      JOIN hh_lemma l ON w.lemma = l.lemma 
      $whereClause GROUP BY w.lemma
    ) as t";
    $maxRow = $conn->query($maxQuery)->fetch_assoc();
    $maxCount = $maxRow['maxCount'] ? $maxRow['maxCount'] : 1;
    
    $data = [
      'data'     => $rows,
      'total'    => $total,
      'maxCount' => $maxCount,
      'page'     => $page,
      'perPage'  => $perPage
    ];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    break;

  case 'sentences':
    $whereClause = '';
    if (!empty($search)) {
      $whereClause = "WHERE sentence LIKE '%$search%'";
    }
    
    $orderByClause = 'ORDER BY cnt DESC';
    switch ($order) {
      case 'cnt_asc':
        $orderByClause = 'ORDER BY cnt ASC';
        break;
      case 'sentence_asc':
        $orderByClause = 'ORDER BY sentence ASC';
        break;
      case 'sentence_desc':
        $orderByClause = 'ORDER BY sentence DESC';
        break;
    }
    
    $query = "SELECT sentence, COUNT(*) as cnt FROM hh_sentences $whereClause GROUP BY sentence $orderByClause LIMIT $offset, $perPage";
    $result = $conn->query($query);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    $total = $conn->query("SELECT COUNT(DISTINCT sentence) as total FROM hh_sentences")->fetch_assoc()['total'];
    $maxRow = $conn->query("SELECT MAX(cnt) as maxCount FROM (SELECT COUNT(*) as cnt FROM hh_sentences GROUP BY sentence) as t")
      ->fetch_assoc();
    $maxCount = $maxRow['maxCount'] ? $maxRow['maxCount'] : 1;
    $data = [
      'data'     => $rows,
      'total'    => $total,
      'maxCount' => $maxCount,
      'page'     => $page,
      'perPage'  => $perPage
    ];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    break;

  case 'keyword_connections':
    // Get keyword connections based on co-occurrences
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30; // Limit to top keywords
    $minConnections = isset($_GET['min_connections']) ? intval($_GET['min_connections']) : 1; // Minimum number of connections
    
    // First, get all keywords and their occurrences
    $result = $conn->query("SELECT keywords FROM hh_index WHERE keywords IS NOT NULL AND keywords != '[]'");
    
    $keywordCounts = [];
    $connections = [];
    
    while ($row = $result->fetch_assoc()) {
      $keywordsArray = json_decode($row['keywords'], true);
      
      // Make sure it's an array before proceeding
      if (is_array($keywordsArray)) {
        // Count individual keywords
        foreach ($keywordsArray as $keyword) {
          if (is_string($keyword) && trim($keyword) !== '') {
            $keyword = trim($keyword);
            if (isset($keywordCounts[$keyword])) {
              $keywordCounts[$keyword]++;
            } else {
              $keywordCounts[$keyword] = 1;
            }
          }
        }
        
        // Track connections between pairs of keywords
        $uniqueKeywords = array_unique(array_filter($keywordsArray, function($k) {
          return is_string($k) && trim($k) !== '';
        }));
        
        // Normalize keywords
        $uniqueKeywords = array_map('trim', $uniqueKeywords);
        
        // Generate all possible pairs and count co-occurrences
        for ($i = 0; $i < count($uniqueKeywords); $i++) {
          for ($j = $i + 1; $j < count($uniqueKeywords); $j++) {
            $keyA = $uniqueKeywords[$i];
            $keyB = $uniqueKeywords[$j];
            
            // Create a connection identifier (alphabetically ordered pair)
            $connectionId = $keyA < $keyB ? "$keyA-$keyB" : "$keyB-$keyA";
            
            if (isset($connections[$connectionId])) {
              $connections[$connectionId]['weight']++;
            } else {
              $connections[$connectionId] = [
                'source' => $keyA,
                'target' => $keyB,
                'weight' => 1
              ];
            }
          }
        }
      }
    }
    
    // Sort keywords by frequency
    arsort($keywordCounts);
    $topKeywords = array_slice($keywordCounts, 0, $limit, true);
    
    // Filter connections to only include top keywords and meet minimum connection threshold
    $topKeywordSet = array_keys($topKeywords);
    $filteredConnections = array_filter($connections, function($conn) use ($topKeywordSet, $minConnections) {
      return in_array($conn['source'], $topKeywordSet) && 
             in_array($conn['target'], $topKeywordSet) &&
             $conn['weight'] >= $minConnections;
    });
    
    // Format data for graph visualization
    $nodes = [];
    foreach ($topKeywords as $keyword => $count) {
      $nodes[] = [
        'id' => $keyword,
        'count' => $count
      ];
    }
    
    $links = array_values($filteredConnections);
    
    echo json_encode([
      'nodes' => $nodes,
      'links' => $links
    ], JSON_UNESCAPED_UNICODE);
    break;

  case 'get_content_ngt_text':
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
      echo json_encode(['error' => 'Valid ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $query = "SELECT ngt_text, ngt_text2, ngt_text3, ngt_text4, plain_text, zelfopname, status FROM hh_index WHERE id = $id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $ngtText = !empty($row['ngt_text']) ? $row['ngt_text'] : $row['plain_text'];
      
      // Process recorded videos if they exist
      $recordedVideos = [];
      if (!empty($row['zelfopname'])) {
        $zelfopname = json_decode($row['zelfopname'], true);
        if (is_array($zelfopname)) {
          $recordedVideos = $zelfopname;
        }
      }
      
      echo json_encode([
        'ngt_text' => $ngtText,
        'ngt_text2' => $row['ngt_text2'],
        'ngt_text3' => $row['ngt_text3'],
        'ngt_text4' => $row['ngt_text4'],
        'plain_text' => $row['plain_text'],
        'recorded_videos' => $recordedVideos,
        'status' => $row['status'] // Include status in the response
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Record not found'], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'get_content_ngt_text_extended':
    $id_param = isset($_GET['id']) ? $_GET['id'] : '';
    
    if (empty($id_param)) {
      echo json_encode(['error' => 'Valid ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Parse ID to check if it contains a suffix (e.g., 8848_2)
    $id_parts = explode('_', $id_param);
    $base_id = intval($id_parts[0]);
    $text_version = isset($id_parts[1]) ? intval($id_parts[1]) : 1; // Default to 1 if no suffix
    
    if ($base_id <= 0) {
      echo json_encode(['error' => 'Valid base ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $query = "SELECT ngt_text, ngt_text2, ngt_text3, ngt_text4, plain_text, zelfopname, status FROM hh_index WHERE id = $base_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      
      // Determine which NGT text to return based on version
      $target_text = '';
      $source_field = '';
      
      switch ($text_version) {
        case 2:
          $target_text = $row['ngt_text2'];
          $source_field = 'ngt_text2';
          break;
        case 3:
          $target_text = $row['ngt_text3'];
          $source_field = 'ngt_text3';
          break;
        case 4:
          $target_text = $row['ngt_text4'];
          $source_field = 'ngt_text4';
          break;
        default:
          $target_text = $row['ngt_text'];
          $source_field = 'ngt_text';
          break;
      }
      
      // Fall back to plain text if the specified NGT text is empty
      if (empty($target_text)) {
        $target_text = $row['plain_text'];
      }
      
      // Process recorded videos if they exist
      $recordedVideos = [];
      if (!empty($row['zelfopname'])) {
        $zelfopname = json_decode($row['zelfopname'], true);
        if (is_array($zelfopname)) {
          $recordedVideos = $zelfopname;
        }
      }
      
      echo json_encode([
        'ngt_text' => $target_text,     // Return as ngt_text regardless of source
        'plain_text' => $row['plain_text'],
        'recorded_videos' => $recordedVideos,
        'status' => $row['status'],
        'source_field' => $source_field, // Information about which field was used
        'base_id' => $base_id,
        'text_version' => $text_version
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Record not found'], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'update_content_ngt_text':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $id = isset($postData['id']) ? intval($postData['id']) : 0;
    $ngtText = isset($postData['ngt_text']) ? $conn->real_escape_string($postData['ngt_text']) : '';
    $ngtText2 = isset($postData['ngt_text2']) ? $conn->real_escape_string($postData['ngt_text2']) : '';
    $ngtText3 = isset($postData['ngt_text3']) ? $conn->real_escape_string($postData['ngt_text3']) : '';
    $ngtText4 = isset($postData['ngt_text4']) ? $conn->real_escape_string($postData['ngt_text4']) : '';
    
    if ($id <= 0) {
      echo json_encode(['error' => 'Valid ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $updateQuery = "UPDATE hh_index SET ngt_text = '$ngtText', ngt_text2 = '$ngtText2', ngt_text3 = '$ngtText3', ngt_text4 = '$ngtText4' WHERE id = $id";
    if ($conn->query($updateQuery)) {
      echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Update failed: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'update_content_status':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $id = isset($postData['id']) ? intval($postData['id']) : 0;
    
    // Handle 'null' as a string from JSON
    if (isset($postData['status']) && $postData['status'] === 'null') {
      $statusSql = "NULL"; // This will be inserted directly in the SQL query
    } else {
      $status = isset($postData['status']) ? intval($postData['status']) : null;
      $statusSql = $status === null ? "NULL" : $status;
    }
    
    if ($id <= 0) {
      echo json_encode(['error' => 'Valid ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $updateQuery = "UPDATE hh_index SET status = $statusSql WHERE id = $id";
    if ($conn->query($updateQuery)) {
      // Retrieve the current status after update
      $result = $conn->query("SELECT status FROM hh_index WHERE id = $id");
      $row = $result->fetch_assoc();
      
      echo json_encode([
        'success' => true,
        'status' => $row['status']
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Update failed: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'update_content_naam':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $id = isset($postData['id']) ? intval($postData['id']) : 0;
    $naam = isset($postData['naam']) ? $conn->real_escape_string($postData['naam']) : '';
    
    if ($id <= 0) {
      echo json_encode(['error' => 'Valid ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Allow empty naam values, so we don't check for non-empty
    $updateQuery = "UPDATE hh_index SET naam = '$naam' WHERE id = $id";
    if ($conn->query($updateQuery)) {
      // Retrieve the current naam after update
      $result = $conn->query("SELECT naam FROM hh_index WHERE id = $id");
      $row = $result->fetch_assoc();
      
      echo json_encode([
        'success' => true,
        'naam' => $row['naam']
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Update failed: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'upload_video':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Handle content_id case (for content pages)
    if (isset($_POST['content_id']) && !empty($_POST['content_id'])) {
      $contentId = intval($_POST['content_id']);
      
      if ($contentId <= 0) {
        echo json_encode(['error' => 'Invalid content ID'], JSON_UNESCAPED_UNICODE);
        break;
      }
      
      if (!isset($_FILES['video']) || $_FILES['video']['error'] != UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Video upload failed'], JSON_UNESCAPED_UNICODE);
        break;
      }
      
      // Create uploads directory if it doesn't exist
      $uploadDir = '../uploads/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      
      // Generate unique filename with MD5 hash
      $filename = md5(uniqid() . time()) . '.webm';
      $uploadFile = $uploadDir . $filename;
      
      if (move_uploaded_file($_FILES['video']['tmp_name'], $uploadFile)) {
        // Get existing zelfopname data
        $result = $conn->query("SELECT zelfopname FROM hh_index WHERE id = $contentId");
        
        if ($result && $result->num_rows > 0) {
          $row = $result->fetch_assoc();
          
          // Update the zelfopname field (add to existing array or create new array)
          $zelfopname = !empty($row['zelfopname']) ? json_decode($row['zelfopname'], true) : [];
          if (!is_array($zelfopname)) {
            $zelfopname = [];
          }
          $zelfopname[] = $filename;
          $zelfopnameJson = $conn->real_escape_string(json_encode($zelfopname));
          
          $updateQuery = "UPDATE hh_index SET zelfopname = '$zelfopnameJson' WHERE id = $contentId";
          if ($conn->query($updateQuery)) {
            echo json_encode([
              'success' => true, 
              'filename' => $filename,
              'url' => '/uploads/' . $filename
            ], JSON_UNESCAPED_UNICODE);
          } else {
            echo json_encode(['error' => 'Update failed: ' . $conn->error], JSON_UNESCAPED_UNICODE);
          }
        } else {
          echo json_encode(['error' => 'Record not found'], JSON_UNESCAPED_UNICODE);
        }
      } else {
        echo json_encode(['error' => 'Failed to save the video'], JSON_UNESCAPED_UNICODE);
      }
      break;
    }
    
    // Handle lemma case (existing functionality)
    $lemma = isset($_POST['lemma']) ? $conn->real_escape_string($_POST['lemma']) : '';
    
    // ...existing code for lemma case...
    if (empty($lemma)) {
      echo json_encode(['error' => 'Lemma or content ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // ...rest of existing upload_video case...
    break;
    
  case 'delete_recorded_video':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $id = isset($postData['id']) ? intval($postData['id']) : 0;
    $filename = isset($postData['filename']) ? $conn->real_escape_string($postData['filename']) : '';
    
    if ($id <= 0 || empty($filename)) {
      echo json_encode(['error' => 'Valid ID and filename are required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Get current zelfopname array
    $result = $conn->query("SELECT zelfopname FROM hh_index WHERE id = $id");
    
    if (!$result || $result->num_rows === 0) {
      echo json_encode(['error' => 'Record not found'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $row = $result->fetch_assoc();
    $zelfopname = !empty($row['zelfopname']) ? json_decode($row['zelfopname'], true) : [];
    
    if (!is_array($zelfopname)) {
      $zelfopname = [];
    }
    
    // Remove the filename from the array
    $zelfopname = array_filter($zelfopname, function($item) use ($filename) {
      return $item !== $filename;
    });
    
    // Update the database with the new array
    $zelfopnameJson = $conn->real_escape_string(json_encode(array_values($zelfopname)));
    $updateQuery = "UPDATE hh_index SET zelfopname = '$zelfopnameJson' WHERE id = $id";
    
    if ($conn->query($updateQuery)) {
      echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Update failed: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'get_glossary':
    $contentId = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
    
    if ($contentId <= 0) {
      echo json_encode(['error' => 'Valid content ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $query = "SELECT id, glos, text FROM hh_index_glos WHERE hh_index_id = $contentId ORDER BY glos ASC";
    $result = $conn->query($query);
    
    $entries = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
      }
    }
    
    echo json_encode([
      'entries' => $entries,
      'content_id' => $contentId
    ], JSON_UNESCAPED_UNICODE);
    break;
    
  case 'add_glossary_entry':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $contentId = isset($postData['content_id']) ? intval($postData['content_id']) : 0;
    // Convert the glossary term to uppercase
    $glos = isset($postData['glos']) ? strtoupper($conn->real_escape_string($postData['glos'])) : '';
    $text = isset($postData['text']) ? $conn->real_escape_string($postData['text']) : '';
    
    if ($contentId <= 0 || empty($glos) || empty($text)) {
      echo json_encode(['error' => 'Content ID, glossary term, and definition are required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Check if the content ID exists in hh_index
    $checkQuery = "SELECT id FROM hh_index WHERE id = $contentId";
    $checkResult = $conn->query($checkQuery);
    
    if (!$checkResult || $checkResult->num_rows === 0) {
      echo json_encode(['error' => 'Content ID does not exist'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Insert the new glossary entry
    $insertQuery = "INSERT INTO hh_index_glos (hh_index_id, glos, text) VALUES ('$contentId', '$glos', '$text')";
    
    if ($conn->query($insertQuery)) {
      $newId = $conn->insert_id;
      echo json_encode([
        'success' => true,
        'id' => $newId,
        'glos' => $glos,
        'text' => $text
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Failed to add glossary entry: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;
    
  case 'update_glossary_entry':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $id = isset($postData['id']) ? intval($postData['id']) : 0;
    $glos = isset($postData['glos']) ? $conn->real_escape_string($postData['glos']) : '';
    $text = isset($postData['text']) ? $conn->real_escape_string($postData['text']) : '';
    
    if ($id <= 0 || empty($glos) || empty($text)) {
      echo json_encode(['error' => 'Entry ID, glossary term, and definition are required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Update the glossary entry
    $updateQuery = "UPDATE hh_index_glos SET glos = '$glos', text = '$text' WHERE id = $id";
    
    if ($conn->query($updateQuery)) {
      echo json_encode([
        'success' => true,
        'id' => $id,
        'glos' => $glos,
        'text' => $text
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Failed to update glossary entry: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;
    
  case 'delete_glossary_entry':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $id = isset($postData['id']) ? intval($postData['id']) : 0;
    
    if ($id <= 0) {
      echo json_encode(['error' => 'Valid entry ID is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Delete the glossary entry
    $deleteQuery = "DELETE FROM hh_index_glos WHERE id = $id";
    
    if ($conn->query($deleteQuery)) {
      echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Failed to delete glossary entry: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'get_lemmas':
    if (!isset($_GET['text']) || empty($_GET['text'])) {
      echo json_encode(['error' => 'Text parameter is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $text = $_GET['text'];
    // Split the text by spaces and other separators
    $words = preg_split('/[\s,\.;:!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    // Remove duplicates to avoid processing the same word multiple times
    $words = array_unique($words);
    
    $lemmasWithSign = [];
    $lemmasWithoutSign = [];
    
    foreach ($words as $word) {
      // Clean the word and convert to lowercase for better matching
      $cleanWord = trim(strtolower($conn->real_escape_string($word)));
      // Remove special characters, keep only alphanumeric chars
      $cleanWord = preg_replace('/[^a-z0-9]/u', '', $cleanWord);
      // Skip words that are too short (3 characters or less)
      if (empty($cleanWord) || mb_strlen($cleanWord) <= 3) continue;
      
      // Look for the word in hh_words to get the lemma
      $query = "SELECT lemma FROM hh_words WHERE word = '$cleanWord' LIMIT 1";
      $result = $conn->query($query);
      
      if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lemma = $row['lemma'];
        
        // Check if this lemma has a video in hh_lemma
        $videoQuery = "SELECT lemma, video, origin FROM hh_lemma WHERE lemma = '$lemma' AND video IS NOT NULL AND video != '' LIMIT 1";
        $videoResult = $conn->query($videoQuery);
        
        if ($videoResult && $videoResult->num_rows > 0) {
          $videoRow = $videoResult->fetch_assoc();
          // Add to lemmas with sign
          $lemmasWithSign[] = [
            'lemma' => $videoRow['lemma'],
            'video' => $videoRow['video'],
            'origin' => $videoRow['origin']
          ];
        } else {
          // Check if the lemma exists in form_data table
          $formDataQuery = "SELECT glos FROM form_data WHERE glos = '$lemma' AND extern = 1 LIMIT 1";
          $formDataResult = $conn->query($formDataQuery);
          
          // Only add to lemmasWithoutSign if not found in form_data
          if (!($formDataResult && $formDataResult->num_rows > 0)) {
            // Check if lemma is already in the list before adding
            $lemmaExists = false;
            foreach ($lemmasWithoutSign as $existingItem) {
              if ($existingItem['lemma'] === $lemma) {
                $lemmaExists = true;
                break;
              }
            }
            
            if (!$lemmaExists) {
              $lemmasWithoutSign[] = [
                'lemma' => $lemma
              ];
            }
          }
        }
      } else {
        // Also check if the word exists in form_data table
        $formDataQuery = "SELECT glos FROM form_data WHERE glos = '$word' AND extern = 1 LIMIT 1";
        $formDataResult = $conn->query($formDataQuery);
        
        // Only add to lemmasWithoutSign if not found in form_data
        if (!($formDataResult && $formDataResult->num_rows > 0)) {
          // Word not found in hh_words, add as-is to lemmas without sign
          // Check if word is already in the list before adding
          $wordExists = false;
          foreach ($lemmasWithoutSign as $existingItem) {
            if ($existingItem['lemma'] === $word) {
              $wordExists = true;
              break;
            }
          }
          
          if (!$wordExists) {
            $lemmasWithoutSign[] = [
              'lemma' => $word
            ];
          }
        }
      }
    }
    
    // Return the results
    echo json_encode([
      'lemmas_with_sign' => $lemmasWithSign,
      'lemmas_without_sign' => $lemmasWithoutSign,
      'text' => $text
    ], JSON_UNESCAPED_UNICODE);
    break;

  // Add new endpoint to get themas
  case 'get_themas':
    // Forward the request to the external API
    $externalUrl = "https://signcollect.nl/uniqueThema.php?extern=1";
    
    // Use cURL to make the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $externalUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
      echo json_encode(['error' => 'Failed to fetch themas: ' . curl_error($ch)], JSON_UNESCAPED_UNICODE);
      curl_close($ch);
      break;
    }
    
    curl_close($ch);
    
    // Pass through the response
    echo $response;
    break;

  case 'get_all_glossary':
    $whereClause = '';
    if (!empty($search)) {
      // Search in both glos and text
      $whereClause = "WHERE hig.glos LIKE '%$search%' OR hig.text LIKE '%$search%'";
    }
    
    // Fetch all matching entries first, ordered for grouping
    // Include glos_id
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
          'original_glos' => $row['glos'], // Store original for updates
          'original_text' => $row['text'], // Store original for updates
          'sources' => [] // Initialize sources array
        ];
      }
      
      // Add source if content_id exists, include glos_id
      if ($row['content_id']) {
        $groupedData[$key]['sources'][] = [
          'glos_id' => $row['glos_id'], // Add glos_id here
          'content_id' => $row['content_id'],
          'url' => $row['url']
        ];
      } else {
         // Handle cases where a glos entry might not have a source link (optional)
         // If you want to track these, add them with null content_id/url but include glos_id
         $groupedData[$key]['sources'][] = [
             'glos_id' => $row['glos_id'],
             'content_id' => null,
             'url' => null
         ];
      }
    }
    
    // Convert grouped data to indexed array
    $finalGroupedData = array_values($groupedData);
    
    // Calculate total based on grouped data
    $total = count($finalGroupedData);
    
    // Apply pagination to the grouped data
    $paginatedData = array_slice($finalGroupedData, $offset, $perPage);
    
    echo json_encode([
      'data' => $paginatedData, // Send paginated grouped data
      'total' => $total,       // Send total count of unique groups
      'page' => $page,
      'perPage' => $perPage
    ], JSON_UNESCAPED_UNICODE);
    break;

  case 'update_glossary_group':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $originalGlos = isset($postData['original_glos']) ? $conn->real_escape_string($postData['original_glos']) : '';
    $originalText = isset($postData['original_text']) ? $conn->real_escape_string($postData['original_text']) : '';
    $newGlos = isset($postData['new_glos']) ? $conn->real_escape_string($postData['new_glos']) : '';
    $newText = isset($postData['new_text']) ? $conn->real_escape_string($postData['new_text']) : '';
    
    if (empty($originalGlos) || empty($originalText) || empty($newGlos) || empty($newText)) {
      echo json_encode(['error' => 'Original and new glossary term/definition are required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Update all entries matching the original glos and text
    $updateQuery = "UPDATE hh_index_glos 
                    SET glos = '$newGlos', text = '$newText' 
                    WHERE glos = '$originalGlos' AND text = '$originalText'";
                    
    if ($conn->query($updateQuery)) {
      echo json_encode(['success' => true, 'affected_rows' => $conn->affected_rows], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode(['error' => 'Failed to update glossary group: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'delete_glossary_source':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $glosId = isset($postData['glos_id']) ? intval($postData['glos_id']) : 0;
    
    if ($glosId <= 0) {
      echo json_encode(['error' => 'Valid glossary entry ID (glos_id) is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Delete the specific glossary entry
    $deleteQuery = "DELETE FROM hh_index_glos WHERE id = $glosId";
    
    if ($conn->query($deleteQuery)) {
       if ($conn->affected_rows > 0) {
           echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
       } else {
           echo json_encode(['error' => 'Glossary source not found or already deleted'], JSON_UNESCAPED_UNICODE);
       }
    } else {
      echo json_encode(['error' => 'Failed to delete glossary source: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'get_glossary_definition':
    $glos = isset($_GET['glos']) ? $conn->real_escape_string($_GET['glos']) : '';
    
    if (empty($glos)) {
      echo json_encode(['error' => 'Glossary term is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // Find the most common definition for the given glos
    $query = "SELECT text, COUNT(*) as cnt 
              FROM hh_index_glos 
              WHERE glos = '$glos' 
              GROUP BY text 
              ORDER BY cnt DESC 
              LIMIT 1";
              
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      echo json_encode(['definition' => $row['text']], JSON_UNESCAPED_UNICODE);
    } else {
      // No definition found for this term
      echo json_encode(['definition' => null], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'bulk_add_glossary_entries':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $contentId = isset($postData['content_id']) ? intval($postData['content_id']) : 0;
    $bulkText = isset($postData['bulk_text']) ? $postData['bulk_text'] : '';
    $thema = isset($postData['thema']) ? $conn->real_escape_string($postData['thema']) : '';
    
    if ($contentId <= 0 || empty($bulkText)) {
      echo json_encode(['error' => 'Content ID and bulk text are required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    $lines = preg_split('/\r\n|\r|\n/', $bulkText, -1, PREG_SPLIT_NO_EMPTY);
    $results = [
      'processed' => 0,
      'linked_existing' => 0,
      'added_new' => 0,
      'signbank_added' => [],
      'signbank_errors' => [],
      'errors' => []
    ];
    
    // Prepare statements for efficiency
    $findDefStmt = $conn->prepare("SELECT text, COUNT(*) as cnt FROM hh_index_glos WHERE glos = ? GROUP BY text ORDER BY cnt DESC LIMIT 1");
    $insertStmt = $conn->prepare("INSERT INTO hh_index_glos (hh_index_id, glos, text) VALUES (?, ?, ?)");
    
    foreach ($lines as $line) {
      $results['processed']++;
      $parts = explode(',', $line, 2); // Split by the first comma only
      
      // Convert the term to uppercase
      $term = isset($parts[0]) ? trim(strtoupper($conn->real_escape_string($parts[0]))) : '';
      $definition = isset($parts[1]) ? trim($conn->real_escape_string($parts[1])) : '';
      
      if (empty($term)) {
        $results['errors'][] = "Skipped line (empty term): " . $line;
        continue;
      }
      
      // Check if term exists and get most common definition
      $findDefStmt->bind_param("s", $term);
      $findDefStmt->execute();
      $defResult = $findDefStmt->get_result();
      
      $existingDefinition = null;
      if ($defResult && $defResult->num_rows > 0) {
        $existingDefinition = $defResult->fetch_assoc()['text'];
      }
      
      $finalDefinition = '';
      $isNewTerm = false;
      
      if ($existingDefinition !== null) {
        // Term exists, use its most common definition
        $finalDefinition = $existingDefinition;
        $results['linked_existing']++;
      } else {
        // Term is new, use the provided definition
        if (empty($definition)) {
           $results['errors'][] = "Skipped new term (no definition provided): " . $term;
           continue;
        }
        $finalDefinition = $definition;
        $results['added_new']++;
        $isNewTerm = true;
      }
      
      // Insert into hh_index_glos
      $insertStmt->bind_param("iss", $contentId, $term, $finalDefinition);
      if (!$insertStmt->execute()) {
        $results['errors'][] = "Failed to add/link '$term': " . $insertStmt->error;
        // If insert failed, don't attempt Signbank add
        continue; 
      }
      
      // If it was a new term, try adding to Signbank
      if ($isNewTerm) {
        if (empty($thema)) {
           $results['signbank_errors'][] = "Skipped Signbank add for '$term' (no thema provided).";
           continue;
        }

        // Use cURL to call the external batch_add.php script
        $signbankUrl = 'https://signcollect.nl/batch_add.php';
        $postFields = [
            'wordList' => $term, // Send the raw term, not escaped
            'thema' => $thema,   // Send the raw thema
            'userId' => '6',     // Assuming default user ID
            'checkDuplicatesWithSuffix' => 'true',  // Or 'false' based on desired behavior
            //      formData.append('labels', JSON.stringify(['TYDbase', 'HealthHolland']));
            'labels' => json_encode(['TYDbase', 'HealthHolland']), // Send as JSON
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $signbankUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Use cautiously, consider proper verification
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Add a timeout

        $signbankResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $results['signbank_errors'][] = "Signbank API call failed for '$term': " . $curlError;
        } else {
            $signbankResult = json_decode($signbankResponse, true);
            if ($signbankResult) {
                if (isset($signbankResult['success']) && !empty($signbankResult['success'])) {
                    $results['signbank_added'] = array_merge($results['signbank_added'], $signbankResult['success']);
                }
                if (isset($signbankResult['errors']) && !empty($signbankResult['errors'])) {
                    $results['signbank_errors'][] = "Signbank error for '$term': " . implode(', ', $signbankResult['errors']);
                }
                 if (isset($signbankResult['error']) && !empty($signbankResult['error'])) { // Handle single error string
                    $results['signbank_errors'][] = "Signbank error for '$term': " . $signbankResult['error'];
                }
                // Handle other potential responses like duplicates etc. if needed
            } else {
                $results['signbank_errors'][] = "Invalid Signbank API response for '$term'.";
            }
        }
      }
    }
    
    $findDefStmt->close();
    $insertStmt->close();
    
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    break;

  case 'check_term_video':
    $glos = isset($_GET['glos']) ? $conn->real_escape_string($_GET['glos']) : '';
    
    if (empty($glos)) {
      echo json_encode(['error' => 'Term is required'], JSON_UNESCAPED_UNICODE);
      break;
    }
    
    // First, find the id in form_data table
    $query = "SELECT id FROM form_data WHERE glos = '$glos' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $form_data_id = $row['id'];
      
      // First attempt: Check for video in matched_transcriptions with zOg='extern'
      $query = "SELECT m_file 
                FROM matched_transcriptions 
                WHERE m_transcription = $form_data_id 
                  AND zOg = 'extern' 
                ORDER BY m_file DESC 
                LIMIT 1";
      $result = $conn->query($query);
      
      if ($result && $result->num_rows > 0) {
        // Video found with zOg='extern'
        $row = $result->fetch_assoc();
        $m_file = $row['m_file'];
        
        // Replace .wav with .mp4 and build the URL
        $videoUrl = str_replace(".wav", ".mp4", $m_file);
        $fullVideoUrl = "https://media.signcollect.nl/" . $videoUrl;
        
        echo json_encode([
          'has_video' => true,
          'video_url' => $fullVideoUrl
        ], JSON_UNESCAPED_UNICODE);
      } else {
        // Second attempt: Check nmm_data and then matched_transcriptions with zOg LIKE 'nmm'
        $query = "SELECT id FROM nmm_data WHERE glos = '$glos' LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
          $row = $result->fetch_assoc();
          $nmm_id = $row['id'];
          
          // Use nmm_id to look in matched_transcriptions
          $query = "SELECT m_file 
                    FROM matched_transcriptions 
                    WHERE m_transcription = $nmm_id 
                      AND zOg LIKE 'nmm'
                    LIMIT 1";
          $result = $conn->query($query);
          
          if ($result && $result->num_rows > 0) {
            // Video found with zOg LIKE 'nmm'
            $row = $result->fetch_assoc();
            $m_file = $row['m_file'];
            
            // Replace .wav with .mp4 and build the URL
            $videoUrl = str_replace(".wav", ".mp4", $m_file);
            $fullVideoUrl = "https://media.signcollect.nl/" . $videoUrl;
            
            echo json_encode([
              'has_video' => true,
              'video_url' => $fullVideoUrl
            ], JSON_UNESCAPED_UNICODE);
          } else {
            echo json_encode(['has_video' => false], JSON_UNESCAPED_UNICODE);
          }
        } else {
          echo json_encode(['has_video' => false], JSON_UNESCAPED_UNICODE);
        }
      }
    } else {
      echo json_encode(['has_video' => false], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'ngt_comparison_stats':
    // Return pre-computed NGT text comparison statistics
    $resultsFile = __DIR__ . '/ngt_comparison_results.json';

    if (file_exists($resultsFile)) {
      $jsonContent = file_get_contents($resultsFile);
      echo $jsonContent;
    } else {
      echo json_encode([
        'error' => 'Comparison results not found. Run compare_ngt_texts.py first.',
        'hint' => 'Execute: python3 /web/hh/compare_ngt_texts.py'
      ], JSON_UNESCAPED_UNICODE);
    }
    break;

  case 'get_unique_labels':
    // Get all unique labels from the hh_index table
    $result = $conn->query("SELECT labels FROM hh_index WHERE labels IS NOT NULL AND labels != '[]'");

    $uniqueLabels = [];
    while ($row = $result->fetch_assoc()) {
      $labelsJson = $row['labels'];
      $labelsArray = json_decode($labelsJson, true);

      if (is_array($labelsArray)) {
        foreach ($labelsArray as $label) {
          if (is_string($label) && trim($label) !== '' && !in_array($label, $uniqueLabels)) {
            $uniqueLabels[] = $label;
          }
        }
      }
    }

    sort($uniqueLabels);
    echo json_encode(['labels' => $uniqueLabels], JSON_UNESCAPED_UNICODE);
    break;

  default:
    echo json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    break;
}

$conn->close();
?>
