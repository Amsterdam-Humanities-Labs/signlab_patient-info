<?php require_once __DIR__ . '/../auth.php'; requireAuth(); ?>

// signcollect-lib's install-root resolver: sc_path(), sc_dir(), sc_root().
// Vendored shim - it finds /web/lib/paths.php, or falls back to /web.
require_once __DIR__ . '/../sc_paths.php';

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Segments Viewer</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .video-card {
            background: #16213e;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .video-card.active {
            outline: 3px solid #4fc3f7;
        }
        .video-card img,
        .video-card video {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        .video-card .placeholder {
            width: 100%;
            height: 150px;
            background: #0f3460;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        .video-card .info {
            padding: 10px;
        }
        .video-card .name {
            font-size: 13px;
            word-break: break-all;
        }
        .video-card .segments-count {
            font-size: 11px;
            color: #4fc3f7;
            margin-top: 5px;
        }
        .segments-panel {
            display: none;
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .segments-panel.active {
            display: block;
        }
        .segments-panel h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        .segments-row {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        .segment-item {
            flex-shrink: 0;
            width: 280px;
            background: #0f3460;
            border-radius: 8px;
            overflow: hidden;
        }
        .segment-item video {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
            cursor: pointer;
        }
        .segment-item .seg-info {
            padding: 10px;
            font-size: 12px;
        }
        .close-btn {
            float: right;
            background: #e94560;
            border: none;
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .close-btn:hover {
            background: #ff6b6b;
        }
        .no-segments {
            color: #666;
            font-style: italic;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }
        .pagination a, .pagination span {
            padding: 8px 16px;
            background: #16213e;
            border-radius: 4px;
            text-decoration: none;
            color: #eee;
        }
        .pagination a:hover {
            background: #0f3460;
        }
        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .pagination .current {
            background: #4fc3f7;
            color: #1a1a2e;
        }
        .check-btn {
            float: right;
            background: #4fc3f7;
            border: none;
            color: #1a1a2e;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
            font-size: 14px;
        }
        .check-btn:hover {
            background: #81d4fa;
        }
        .check-btn.disabled {
            background: #555;
            color: #888;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <h1>Video Segments Viewer</h1>

<?php
$videoDir = sc_dir('media_post');
$webPath = '/gebarenoverleg_media/studioFilesMini/post/';

// Get all mp4 files
$files = glob($videoDir . '*.mp4');
$grouped = [];

foreach ($files as $file) {
    $filename = basename($file);
    $name = pathinfo($filename, PATHINFO_FILENAME);

    // Match split files: Letter + 8 digits + underscore + digits + underscore + segment number
    // e.g., M20250610_9130_1 (base: M20250610_9130, segment: 1)
    if (preg_match('/^([A-Z]\d{8}_\d+)_(\d+)$/', $name, $matches)) {
        $baseName = $matches[1];
        $segmentNum = (int)$matches[2];

        if (!isset($grouped[$baseName])) {
            $grouped[$baseName] = [];
        }
        $grouped[$baseName][$segmentNum] = $filename;
    }
}

// Sort by name descending (newest first based on naming convention)
krsort($grouped);

// Sort segments within each group
foreach ($grouped as &$segments) {
    ksort($segments);
}
unset($segments);

// Pagination
$perPage = 25;
$totalVideos = count($grouped);
$totalPages = ceil($totalVideos / $perPage);
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
$offset = ($page - 1) * $perPage;

// Get current page items
$groupedKeys = array_keys($grouped);
$pageKeys = array_slice($groupedKeys, $offset, $perPage);
$pageItems = [];
$jsonDir = __DIR__ . '/output/';
foreach ($pageKeys as $key) {
    $pageItems[$key] = [
        'original' => file_exists($videoDir . $key . '.mp4') ? $key . '.mp4' : null,
        'segments' => $grouped[$key],
        'hasJson' => file_exists($jsonDir . $key . '_analysis.json')
    ];
}
?>

    <div class="segments-panel" id="segmentsPanel">
        <button class="close-btn" onclick="closePanel()">Close</button>
        <a href="#" class="check-btn" id="segmentCheckerBtn" target="_blank">Segment Checker</a>
        <h2 id="panelTitle">Segments</h2>
        <div class="segments-row" id="segmentsRow"></div>
    </div>

    <h2>Split Videos (<?= $totalVideos ?>) - Page <?= $page ?> of <?= $totalPages ?></h2>

    <div class="pagination">
        <a href="?page=1" class="<?= $page <= 1 ? 'disabled' : '' ?>">« First</a>
        <a href="?page=<?= $page - 1 ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
        <span class="current"><?= $page ?> / <?= $totalPages ?></span>
        <a href="?page=<?= $page + 1 ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">Next ›</a>
        <a href="?page=<?= $totalPages ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">Last »</a>
    </div>

    <div class="video-grid">
<?php foreach ($pageItems as $baseName => $item):
    $firstSegment = reset($item['segments']);
    $segmentCount = count($item['segments']);
?>
        <div class="video-card" onclick="showSegments('<?= htmlspecialchars($baseName) ?>')" data-base="<?= htmlspecialchars($baseName) ?>">
            <video src="<?= $webPath . $firstSegment ?>" muted preload="metadata"></video>
            <div class="info">
                <div class="name"><?= htmlspecialchars($baseName) ?></div>
                <div class="segments-count"><?= $segmentCount ?> segment<?= $segmentCount != 1 ? 's' : '' ?><?= $item['original'] ? ' + original' : '' ?></div>
            </div>
        </div>
<?php endforeach; ?>
    </div>

    <div class="pagination">
        <a href="?page=1" class="<?= $page <= 1 ? 'disabled' : '' ?>">« First</a>
        <a href="?page=<?= $page - 1 ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
        <span class="current"><?= $page ?> / <?= $totalPages ?></span>
        <a href="?page=<?= $page + 1 ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">Next ›</a>
        <a href="?page=<?= $totalPages ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">Last »</a>
    </div>

    <script>
        // Video data from PHP (current page only)
        const videoData = <?= json_encode(array_map(function($item) use ($webPath) {
            $segments = [];
            foreach ($item['segments'] as $num => $filename) {
                $segments[$num] = $webPath . $filename;
            }
            return [
                'original' => $item['original'] ? $webPath . $item['original'] : null,
                'segments' => $segments,
                'hasJson' => $item['hasJson']
            ];
        }, $pageItems)) ?>;

        function showSegments(baseName) {
            const panel = document.getElementById('segmentsPanel');
            const row = document.getElementById('segmentsRow');
            const title = document.getElementById('panelTitle');
            const checkerBtn = document.getElementById('segmentCheckerBtn');

            // Update active card
            document.querySelectorAll('.video-card').forEach(c => c.classList.remove('active'));
            document.querySelector(`.video-card[data-base="${baseName}"]`)?.classList.add('active');

            const data = videoData[baseName];
            if (!data) return;

            title.textContent = baseName;
            row.innerHTML = '';

            // Update Segment Checker button
            if (data.hasJson) {
                checkerBtn.href = `segment_checker.php?video=${encodeURIComponent(baseName)}`;
                checkerBtn.classList.remove('disabled');
                checkerBtn.style.pointerEvents = 'auto';
            } else {
                checkerBtn.href = '#';
                checkerBtn.classList.add('disabled');
                checkerBtn.style.pointerEvents = 'none';
            }

            // Add original video first if it exists
            if (data.original) {
                row.innerHTML += `
                    <div class="segment-item" style="border: 2px solid #4fc3f7;">
                        <video src="${data.original}" muted loop preload="metadata"
                            onmouseenter="this.play()"
                            onmouseleave="this.pause(); this.currentTime=0;"
                            onclick="this.paused ? this.play() : this.pause()"></video>
                        <div class="seg-info" style="color: #4fc3f7;">Original</div>
                    </div>
                `;
            }

            // Add segments sorted by number
            const sortedEntries = Object.entries(data.segments).sort((a, b) => parseInt(a[0]) - parseInt(b[0]));

            sortedEntries.forEach(([num, src]) => {
                row.innerHTML += `
                    <div class="segment-item">
                        <video src="${src}" muted loop preload="metadata"
                            onmouseenter="this.play()"
                            onmouseleave="this.pause(); this.currentTime=0;"
                            onclick="this.paused ? this.play() : this.pause()"></video>
                        <div class="seg-info">Segment ${num}</div>
                    </div>
                `;
            });

            panel.classList.add('active');
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closePanel() {
            document.getElementById('segmentsPanel').classList.remove('active');
            document.querySelectorAll('.video-card').forEach(c => c.classList.remove('active'));
        }
    </script>
</body>
</html>
