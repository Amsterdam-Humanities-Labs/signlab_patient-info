<?php require_once __DIR__ . '/../auth.php'; requireAuth(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segment Checker</title>
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
            margin-bottom: 20px;
        }
        .back-link {
            color: #4fc3f7;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .error {
            background: #e94560;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .main-layout {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .video-container {
            flex: 1;
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
        }
        .segments-container {
            width: 350px;
            flex-shrink: 0;
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        .segments-container h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
        }
        video {
            width: 100%;
            max-height: 400px;
            background: #000;
            border-radius: 4px;
        }
        .segment-card-side {
            background: #0f3460;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
            transition: border 0.2s;
            border: 2px solid transparent;
        }
        .segment-card-side.playing {
            border: 2px solid #4fc3f7;
        }
        .segment-card-side video {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
        .segment-card-side .seg-label {
            padding: 8px;
            font-size: 11px;
            text-align: center;
            background: #16213e;
        }
        .timeline-container {
            margin-top: 20px;
        }
        .timeline-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
            margin-bottom: 5px;
        }
        .timeline {
            position: relative;
            height: 40px;
            background: #0f3460;
            border-radius: 4px;
            cursor: pointer;
            overflow: hidden;
        }
        .timeline-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: rgba(79, 195, 247, 0.3);
            pointer-events: none;
            transition: width 0.1s linear;
        }
        .timeline-playhead {
            position: absolute;
            top: 0;
            width: 2px;
            height: 100%;
            background: #fff;
            pointer-events: none;
            z-index: 10;
        }
        .pause-marker {
            position: absolute;
            top: 0;
            height: 100%;
            background: rgba(233, 69, 96, 0.6);
            border-left: 1px solid #e94560;
            border-right: 1px solid #e94560;
            cursor: pointer;
            transition: background 0.2s;
        }
        .pause-marker:hover {
            background: rgba(233, 69, 96, 0.9);
        }
        .pause-marker .tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #16213e;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .pause-marker:hover .tooltip {
            opacity: 1;
        }
        .info-panel {
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-panel h2 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .info-item {
            background: #0f3460;
            padding: 10px;
            border-radius: 4px;
        }
        .info-item label {
            font-size: 11px;
            color: #888;
            display: block;
            margin-bottom: 3px;
        }
        .info-item value {
            font-size: 14px;
            font-weight: 500;
        }
        .pauses-list {
            margin-top: 20px;
        }
        .pauses-list h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        .pause-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background: #0f3460;
            border-radius: 4px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .pause-item:hover {
            background: #1a4a7a;
        }
        .pause-item.active {
            background: #e94560;
        }
        .pause-num {
            background: #e94560;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            flex-shrink: 0;
        }
        .pause-times {
            flex: 1;
        }
        .pause-times .times {
            font-size: 13px;
        }
        .pause-times .duration {
            font-size: 11px;
            color: #888;
        }
        .pause-velocities {
            font-size: 11px;
            color: #888;
        }
        .controls {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-primary {
            background: #4fc3f7;
            color: #1a1a2e;
        }
        .btn-primary:hover {
            background: #81d4fa;
        }
        .current-time {
            font-size: 14px;
            color: #4fc3f7;
            margin-top: 10px;
        }
        .segment-video-container {
            margin-top: 10px;
            padding: 10px;
            background: #1a1a2e;
            border-radius: 4px;
            border: 2px solid #4fc3f7;
        }
        .segment-video-container video {
            width: 100%;
            max-height: 200px;
            border-radius: 4px;
        }
        .segment-video-container .segment-label {
            font-size: 12px;
            color: #4fc3f7;
            margin-bottom: 5px;
        }
        .segments-row {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        .segment-card {
            flex-shrink: 0;
            width: 250px;
            background: #0f3460;
            border-radius: 8px;
            overflow: hidden;
        }
        .segment-card video {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .segment-card .seg-label {
            padding: 8px;
            font-size: 12px;
            text-align: center;
            background: #16213e;
        }
        .no-segment {
            color: #888;
            font-style: italic;
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<?php
$videoName = isset($_GET['video']) ? $_GET['video'] : null;
$videoDir = '/web/gebarenoverleg_media/studioFilesMini/post/';
$webPath = '/gebarenoverleg_media/studioFilesMini/post/';
$jsonDir = __DIR__ . '/output/';

$error = null;
$jsonData = null;
$videoFile = null;

$segments = [];

if (!$videoName) {
    $error = 'No video specified. Use ?video=VIDEO_NAME';
} else {
    $jsonFile = $jsonDir . $videoName . '_analysis.json';
    $videoFile = $videoDir . $videoName . '.mp4';

    if (!file_exists($jsonFile)) {
        $error = "Analysis file not found: {$videoName}_analysis.json";
    } elseif (!file_exists($videoFile)) {
        $error = "Video file not found: {$videoName}.mp4";
    } else {
        $jsonContent = file_get_contents($jsonFile);
        $jsonData = json_decode($jsonContent, true);
        if (!$jsonData) {
            $error = 'Failed to parse JSON file';
        }

        // Find all segmented videos (_1, _2, etc.)
        $segmentPattern = $videoDir . $videoName . '_*.mp4';
        $segmentFiles = glob($segmentPattern);
        foreach ($segmentFiles as $segFile) {
            $segName = basename($segFile);
            if (preg_match('/_(\d+)\.mp4$/', $segName, $m)) {
                $segments[(int)$m[1]] = $segName;
            }
        }
        ksort($segments);
    }
}
?>

    <a href="viewer.php" class="back-link">&larr; Back to Viewer</a>
    <h1>Segment Checker: <?= htmlspecialchars($videoName ?: 'Unknown') ?></h1>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php else: ?>
    <div class="main-layout">
        <div class="video-container">
            <h3>Original Video</h3>
            <video id="videoPlayer" controls>
                <source src="<?= $webPath . $videoName ?>.mp4" type="video/mp4">
            </video>

            <div class="timeline-container">
                <div class="timeline-label">
                    <span>0:00</span>
                    <span id="currentTimeDisplay">0:00</span>
                    <span><?= gmdate("i:s", (int)$jsonData['duration']) ?></span>
                </div>
                <div class="timeline" id="timeline">
                    <div class="timeline-progress" id="timelineProgress"></div>
                    <div class="timeline-playhead" id="playhead"></div>
                    <?php foreach ($jsonData['pauses'] as $i => $pause):
                        $leftPercent = ($pause['start_time'] / $jsonData['duration']) * 100;
                        $widthPercent = (($pause['end_time'] - $pause['start_time']) / $jsonData['duration']) * 100;
                    ?>
                    <div class="pause-marker"
                         style="left: <?= $leftPercent ?>%; width: <?= $widthPercent ?>%;"
                         data-start="<?= $pause['start_time'] ?>"
                         data-end="<?= $pause['end_time'] ?>"
                         data-segment="<?= $i + 1 ?>"
                         onclick="seekTo(<?= $pause['start_time'] ?>)">
                        <div class="tooltip">Pause <?= $i + 1 ?>: <?= number_format($pause['start_time'], 2) ?>s - <?= number_format($pause['end_time'], 2) ?>s</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="current-time">
                Current: <span id="currentTime">0.00</span>s / <?= number_format($jsonData['duration'], 2) ?>s
            </div>

            <div class="controls">
                <button class="btn btn-primary" onclick="playPause()">Play/Pause</button>
                <button class="btn btn-primary" onclick="seekTo(0)">Restart</button>
            </div>
        </div>

        <div class="segments-container">
            <h3>Segments (<?= count($segments) ?>)</h3>
            <?php if (count($segments) > 0): ?>
                <?php foreach ($segments as $num => $segFile): ?>
                <div class="segment-card-side" data-segment="<?= $num ?>">
                    <video id="segment-<?= $num ?>" src="<?= $webPath . $segFile ?>" muted preload="metadata"
                        onclick="this.paused ? this.play() : this.pause()"></video>
                    <div class="seg-label">Segment <?= $num ?> (after pause <?= $num ?>)</div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-segment">No segmented videos found</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-panel">
        <h2>Video Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Duration</label>
                <value><?= number_format($jsonData['duration'], 2) ?>s</value>
            </div>
            <div class="info-item">
                <label>FPS</label>
                <value><?= $jsonData['video_fps'] ?></value>
            </div>
            <div class="info-item">
                <label>Resolution</label>
                <value><?= $jsonData['resolution']['width'] ?>x<?= $jsonData['resolution']['height'] ?></value>
            </div>
            <div class="info-item">
                <label>Total Frames</label>
                <value><?= $jsonData['total_frames'] ?></value>
            </div>
            <div class="info-item">
                <label>Velocity Threshold</label>
                <value><?= $jsonData['velocity_threshold'] ?></value>
            </div>
            <div class="info-item">
                <label>Min Pause Duration</label>
                <value><?= $jsonData['min_pause_duration'] ?>s</value>
            </div>
        </div>

        <div class="pauses-list">
            <h3>Detected Pauses (<?= count($jsonData['pauses']) ?>)</h3>
            <?php foreach ($jsonData['pauses'] as $i => $pause):
                $segNum = $i + 1;
            ?>
            <div class="pause-item" onclick="seekTo(<?= $pause['start_time'] ?>)" data-index="<?= $i ?>">
                <div class="pause-num"><?= $segNum ?></div>
                <div class="pause-times">
                    <div class="times"><?= number_format($pause['start_time'], 2) ?>s &rarr; <?= number_format($pause['end_time'], 2) ?>s</div>
                    <div class="duration">Duration: <?= number_format($pause['duration'], 2) ?>s (frames <?= $pause['start_frame'] ?> - <?= $pause['end_frame'] ?>)</div>
                </div>
                <div class="pause-velocities">
                    L: <?= number_format($pause['avg_left_velocity'], 1) ?><br>
                    R: <?= number_format($pause['avg_right_velocity'], 1) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        const video = document.getElementById('videoPlayer');
        const timeline = document.getElementById('timeline');
        const playhead = document.getElementById('playhead');
        const progress = document.getElementById('timelineProgress');
        const currentTimeSpan = document.getElementById('currentTime');
        const currentTimeDisplay = document.getElementById('currentTimeDisplay');
        const duration = <?= $jsonData['duration'] ?>;
        const pauses = <?= json_encode($jsonData['pauses']) ?>;
        const segments = <?= json_encode(array_keys($segments)) ?>;

        let currentPauseIndex = -1;
        let lastPlayedSegment = -1;
        let currentSegment = 0;

        // Calculate segment transition points based on splitting algorithm:
        // - First pause: segment 1 starts at end of pause 1 (minus 1s buffer in the segment)
        // - Middle pauses: split at midpoint
        const segmentTransitions = [];

        // Segment 1 starts 1 second before first pause ends (includes 1s buffer)
        if (pauses.length > 0) {
            segmentTransitions.push({
                segment: 1,
                time: Math.max(0, pauses[0].end_time - 1.0)
            });
        }

        // Segments 2+ start at midpoint of their corresponding pause
        for (let i = 1; i < pauses.length; i++) {
            const midpoint = (pauses[i].start_time + pauses[i].end_time) / 2;
            segmentTransitions.push({
                segment: i + 1,
                time: midpoint
            });
        }

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function seekTo(time) {
            video.currentTime = time;
        }

        function playPause() {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        }

        function playSegment(segNum) {
            // Stop all other segments
            document.querySelectorAll('.segment-card-side video').forEach(v => {
                v.pause();
                v.currentTime = 0;
            });
            document.querySelectorAll('.segment-card-side').forEach(c => {
                c.classList.remove('playing');
            });

            // Play the requested segment
            const segVideo = document.getElementById('segment-' + segNum);
            const segCard = document.querySelector(`.segment-card-side[data-segment="${segNum}"]`);
            if (segVideo && segCard) {
                segCard.classList.add('playing');
                segVideo.play();
                // Scroll segment into view
                segCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function stopAllSegments() {
            document.querySelectorAll('.segment-card-side video').forEach(v => {
                v.pause();
                v.currentTime = 0;
            });
            document.querySelectorAll('.segment-card-side').forEach(c => {
                c.classList.remove('playing');
            });
        }

        video.addEventListener('timeupdate', () => {
            const percent = (video.currentTime / duration) * 100;
            playhead.style.left = `${percent}%`;
            progress.style.width = `${percent}%`;
            currentTimeSpan.textContent = video.currentTime.toFixed(2);
            currentTimeDisplay.textContent = formatTime(video.currentTime);

            // Find which pause we're in (if any)
            let inPause = -1;
            pauses.forEach((pause, index) => {
                if (video.currentTime >= pause.start_time && video.currentTime <= pause.end_time) {
                    inPause = index;
                }
            });

            // Highlight active pause in list
            document.querySelectorAll('.pause-item').forEach((item, index) => {
                if (index === inPause) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Find which segment we should be in based on transition times
            let expectedSegment = 0;
            for (const trans of segmentTransitions) {
                if (video.currentTime >= trans.time) {
                    expectedSegment = trans.segment;
                }
            }

            // Autoplay segment when crossing a transition point (only if video is playing)
            if (expectedSegment !== currentSegment && !video.paused) {
                if (expectedSegment > 0 && segments.includes(expectedSegment)) {
                    playSegment(expectedSegment);
                    lastPlayedSegment = expectedSegment;
                } else {
                    stopAllSegments();
                }
                currentSegment = expectedSegment;
            }

            currentPauseIndex = inPause;
        });

        timeline.addEventListener('click', (e) => {
            const rect = timeline.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            seekTo(percent * duration);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                e.preventDefault();
                playPause();
            } else if (e.code === 'ArrowLeft') {
                seekTo(Math.max(0, video.currentTime - 1));
            } else if (e.code === 'ArrowRight') {
                seekTo(Math.min(duration, video.currentTime + 1));
            }
        });

        // Allow manual click on segments
        document.querySelectorAll('.segment-card-side video').forEach(v => {
            v.addEventListener('play', () => {
                const card = v.closest('.segment-card-side');
                document.querySelectorAll('.segment-card-side').forEach(c => c.classList.remove('playing'));
                card.classList.add('playing');
            });
            v.addEventListener('pause', () => {
                const card = v.closest('.segment-card-side');
                card.classList.remove('playing');
            });
        });
    </script>
<?php endif; ?>
</body>
</html>
