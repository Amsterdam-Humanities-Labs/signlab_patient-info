<?php
// db_credentials.php is now optional and holds no database credentials.
//
// Those come from /web/.env through signcollect-lib; db_config.php is what
// endpoints require, and it exposes both $db_config[...] and the DB_*
// constants from that one source. DB_PASSWORD is still defined for anything
// that has not caught up, but defining it here as well only shadows the real
// one and is not what you want.
//
// What is still worth putting in this file, if a host needs it:

// Shared secret for machine clients of segment_api.php (external segmentation
// service). Send as `X-Api-Token: <token>` or `Authorization: Bearer <token>`.
// Generate with: php -r 'echo bin2hex(random_bytes(32));'
// While empty, segment_api.php stays open and logs a warning on every call.
define('HH_API_TOKEN', '');
