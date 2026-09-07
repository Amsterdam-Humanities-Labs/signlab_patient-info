<?php
/**
 * hh's database configuration: locate signcollect-lib and take the
 * credentials from it.
 *
 * This replaces `require_once __DIR__ . '/db_credentials.php'`, which was
 * gitignored and therefore absent from every fresh checkout. On a host where
 * nobody had hand-placed one, api.php, getGlosses.php, get_begrippen.php and
 * save_subtitle.php returned 500 before reading a single byte of the request.
 * Nothing in the deploy provisioned it and nothing warned that it had not
 * been - the file simply had to be known about.
 *
 * After including this, both of the shapes hh already uses are available and
 * both point at the same database:
 *
 *   $db_config['host'|'user'|'password'|'database']   api.php and friends
 *   DB_HOST / DB_USER / DB_PASS / DB_NAME             save_subtitle.php
 *   DB_PASSWORD                                       the old spelling
 *
 * They used to disagree. The array shape hardcoded 'user' => 'user' and took
 * only the password from db_credentials.php; the constants took the password
 * from the same place but named the user separately. No host has a database
 * user called "user", so the array shape could not have worked anywhere.
 *
 * db_credentials.php is still honoured when a host has one, because it is
 * also where HH_API_TOKEN lives - a shared secret for segment_api.php, not a
 * database credential. It is included FIRST and only if present: the library
 * shim defines its constants with defined()-guards, so a legacy host's
 * DB_PASSWORD stays exactly as it was and no redefinition warning can land
 * in the middle of a JSON response.
 */

// HH_API_TOKEN, and whatever a legacy host has already defined.
if (is_file(__DIR__ . '/db_credentials.php')) {
    require_once __DIR__ . '/db_credentials.php';
}

// signcollect-lib. ../lib is the deployed layout (/web/hh -> /web/lib); the
// absolute path is the fallback for a checkout that sits somewhere else.
$hh_lib = null;
foreach ([__DIR__ . '/../lib/db_config.php', '/web/lib/db_config.php'] as $hh_candidate) {
    if (is_file($hh_candidate)) {
        $hh_lib = $hh_candidate;
        break;
    }
}
if ($hh_lib === null) {
    error_log('hh/db_config.php: signcollect-lib not found at ../lib or /web/lib');
    http_response_code(500);
    die('Configuration error: signcollect-lib is not installed.');
}
require_once dirname($hh_lib) . '/compat/db_credentials.php';
unset($hh_lib, $hh_candidate);

$db_config = [
    'host'     => DB_HOST,
    'user'     => DB_USER,
    'password' => DB_PASS,
    'database' => DB_NAME,
];
