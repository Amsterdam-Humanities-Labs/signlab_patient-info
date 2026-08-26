<?php
/**
 * Authentication for the hh endpoints.
 *
 * Browser requests are authenticated with the signcollect.nl portal's
 * `sessionObject` cookie (set by /login.html). The cookie is unsigned, so the
 * user it names is verified against the `users` table on every request;
 * blocked accounts are rejected. Session lifetime itself is the portal's
 * responsibility (it sets `expiresAt`); this file only honours it. Machine clients (the external
 * segmentation service) authenticate with a bearer token instead — see
 * requireApiToken().
 *
 * Usage:
 *   require_once __DIR__ . '/auth.php';
 *   $currentUser = requireAuthApi();   // JSON endpoint: 401 JSON on failure
 *   $currentUser = requireAuth();      // HTML page: redirect to /login.html
 *   requireApiToken();                 // machine endpoint: X-Api-Token header
 */

function hhSessionUser(): ?array {
    if (!isset($_COOKIE['sessionObject'])) {
        return null;
    }
    $decoded = json_decode(urldecode($_COOKIE['sessionObject']), true);
    if (!$decoded || empty($decoded['userId']) || empty($decoded['username'])) {
        return null;
    }
    if (!empty($decoded['expiresAt'])) {
        $expires = strtotime($decoded['expiresAt']);
        if ($expires !== false && $expires < time()) {
            return null;
        }
    }
    // The cookie is written client-side and unsigned, so never trust its
    // contents alone: the (userId, username) pair must exist in `users` and
    // the account must not be blocked. Result is cached for the request.
    static $cache = [];
    $key = $decoded['userId'] . '|' . $decoded['username'];
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = hhLookupUser((int)$decoded['userId'], (string)$decoded['username']);
    }
    if ($cache[$key] === null) {
        return null;
    }
    return [
        'userId'   => (int)$decoded['userId'],
        'username' => $decoded['username'],
        'role'     => $cache[$key]['role'] ?: ($decoded['role'] ?? 'user'),
    ];
}

/** Returns ['role' => …] when the user exists and is not blocked, else null. */
function hhLookupUser(int $userId, string $cookieUser): ?array {
    $servername = $username = $password = $database = null;
    $cfg = dirname(__DIR__) . '/mysql_config.php';
    if (!is_file($cfg)) {
        error_log('hh/auth.php: ../mysql_config.php missing — cannot validate sessions');
        return null;  // fail closed
    }
    include $cfg;   // defines $servername, $username, $password, $database
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli($servername, $username, $password, $database);
        if ($db->connect_errno) {
            error_log('hh/auth.php: DB connect failed: ' . $db->connect_error);
            return null;
        }
        $stmt = $db->prepare('SELECT role, blocked FROM users WHERE userId = ? AND user = ? LIMIT 1');
        $stmt->bind_param('is', $userId, $cookieUser);
        $stmt->execute();
        $stmt->bind_result($role, $blocked);
        $found = $stmt->fetch();
        $stmt->close();
        $db->close();
        if (!$found || (int)$blocked === 1) {
            return null;
        }
        return ['role' => (string)$role];
    } catch (Throwable $e) {
        error_log('hh/auth.php: lookup failed: ' . $e->getMessage());
        return null;
    }
}

/** JSON endpoints: 401 + JSON body when not logged in. */
function requireAuthApi(): array {
    $user = hhSessionUser();
    if ($user === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Not authenticated — log in at /login.html']);
        exit;
    }
    return $user;
}

/** HTML pages: redirect to the portal login when not logged in. */
function requireAuth(): array {
    $user = hhSessionUser();
    if ($user === null) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/hh/');
        header('Location: /login.html?redirect=' . $redirect);
        exit;
    }
    return $user;
}

/**
 * Machine-to-machine endpoints. The expected token is HH_API_TOKEN in
 * db_credentials.php. A logged-in browser session is accepted as well.
 *
 * Until HH_API_TOKEN is defined the check is skipped with a warning in the
 * PHP error log, so an existing integration keeps working while the token
 * is being rolled out — define it as soon as the client is configured.
 */
function requireApiToken(): void {
    if (hhSessionUser() !== null) {
        return;
    }
    if (!defined('HH_API_TOKEN') || HH_API_TOKEN === '') {
        error_log('hh/auth.php: HH_API_TOKEN not set in db_credentials.php — ' . basename($_SERVER['SCRIPT_NAME'] ?? '') . ' is unauthenticated');
        return;
    }
    $given = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($given === '' && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) {
        $given = trim($m[1]);
    }
    if ($given === '' || !hash_equals(HH_API_TOKEN, $given)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing API token']);
        exit;
    }
}
