<?php
/**
 * Authentication for the hh endpoints.
 *
 * Browser requests are authenticated with the signcollect.nl portal's
 * `sessionObject` cookie (set by /login.html). Machine clients (the external
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
    return [
        'userId'   => $decoded['userId'],
        'username' => $decoded['username'],
        'role'     => $decoded['role'] ?? 'user',
    ];
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
