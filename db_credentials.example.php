<?php
define("DB_PASSWORD", "your_password_here");

// Shared secret for machine clients of segment_api.php (external segmentation
// service). Send as `X-Api-Token: <token>` or `Authorization: Bearer <token>`.
// Generate with: php -r 'echo bin2hex(random_bytes(32));'
// While empty, segment_api.php stays open and logs a warning on every call.
define('HH_API_TOKEN', '');
