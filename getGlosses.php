<?php
require_once __DIR__ . '/auth.php';
$currentUser = requireAuthApi();   // portal session cookie required

require_once __DIR__ . '/db_config.php';   // $db_config, from /web/.env via signcollect-lib
// getGlosses.php - Search for glosses in Signbank and Signcollect

// Database connection - host, user, password and database all come
// from db_config.php.

$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) {
  die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

// Set charset to utf8mb4 to match collation
$conn->set_charset("utf8mb4");

// Helper function to check if a sense string matches the search term
function senseMatches($senseString, $searchTerm) {
    // Remove quotes and trim the search term for comparison
    $cleanSearchTerm = trim(str_replace('"', '', $searchTerm));
    
    // Check if the search term is within the sense string
    return stripos($senseString, $cleanSearchTerm) !== false;
}

// Function to perform search in Signbank and Signcollect
function searchInSignbankAndCollect($conn, $senseOrGlosID, $isSense = true) {
    $output = [];
    $glossesSeen = []; // Track glosses we've already seen

    if ($isSense) {
        $searchTerm = "%{$senseOrGlosID}%";
        $query = "SELECT glos, senses, signbank, id FROM form_data WHERE senses LIKE ? AND glosZichtbaar = 0";
    } else {
        $searchTerm = "%{$senseOrGlosID}%"; // Add wildcards for partial matching
        $query = "SELECT glos, senses, signbank, id FROM form_data WHERE glos LIKE ? AND glosZichtbaar = 0";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Include all matching items regardless of video availability
            $glos = $row['glos'];
            $glossesSeen[] = strtoupper($glos); // Track the gloss in uppercase for comparison
            
            $output[$glos] = [
                'glos' => $glos,
                'glosID' => $row['signbank'],
                'sense' => json_decode($row['senses']), // Decode to remove backslashes
                'source' => 'Signcollect',
                'video' => '' // Default empty video source
            ];
        }
    }

    // Look for the glosID in the Signbank JSON file
    // The Signbank dump lives in the connector's own directory, which is where
    // it is rebuilt - /web is not writable by the web server, so the file that
    // has to be replaced atomically cannot live at the docroot root. The old
    // location is still honoured for a host that predates the connector.
    $signbankJson = '/web/signbank_data/glosses_transformed.json';
    if (!is_readable($signbankJson)) $signbankJson = '/web/glosses_transformed.json';
    $signbank = json_decode(file_get_contents($signbankJson), true);

    foreach ($signbank as $entry) {
        foreach ($entry as $key => $details) {
            if ($isSense) {
                $sensesDutch = $details['Senses: Dutch'] ?? [];
                $sensesEnglish = $details['Senses: English'] ?? [];
                $found = false;

                foreach ($sensesDutch as $senseString) {
                    if (senseMatches($senseString, $senseOrGlosID)) {
                        $found = true;
                        break;
                    }
                }

                foreach ($sensesEnglish as $senseString) {
                    if (senseMatches($senseString, $senseOrGlosID)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) continue;
            } else {
                if (stripos($key, $senseOrGlosID) === false) continue;
            }

            $glos = $details['Annotation ID Gloss: Dutch'] ?? '';
            
            // Skip this entry if we already have this gloss from Signcollect
            if (in_array(strtoupper($glos), $glossesSeen)) {
                continue;
            }
            
            $videoSource = !empty($glos) ? "https://leffe.science.uva.nl:8043/uploads/{$glos}.mp4" : '';

            if (!empty($videoSource)) {
                $output[$glos] = [
                    'glos' => $glos,
                    'glosID' => $key,
                    'sense' => $details['Senses: Dutch'], // Already decoded
                    'source' => 'Signbank',
                    'video' => $videoSource
                ];
            }
        }
    }

    return $output;
}

// Handle search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (empty($searchTerm)) {
        echo json_encode(['error' => 'Search term is required']);
        exit;
    }
    
    // Check if the search is for a gloss ID or sense
    $isSense = !isset($_GET['type']) || $_GET['type'] !== 'glosID';
    
    $results = searchInSignbankAndCollect($conn, $searchTerm, $isSense);
    
    // Format the results for output, removing any duplicates
    $formattedResults = array_values($results);
    
    echo json_encode([
        'results' => $formattedResults,
        'total' => count($formattedResults),
        'search_term' => $searchTerm
    ]);
    exit;
}

// Default response if no action specified
echo json_encode(['error' => 'No action specified']);
$conn->close();
?>
