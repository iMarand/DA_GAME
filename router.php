<?php
/*
 * Router for PHP's built-in server (used by host.sh):
 *   php -S 0.0.0.0:2027 router.php
 * The built-in server ignores .htaccess, so this keeps the database and internals private.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$private = '#(^|/)\.'                                   // dotfiles (.htaccess, .gitignore…)
    . '|^/(data|inc)(/|$)'                              // database + PHP includes
    . '|\.(sqlite|sqlite-wal|sqlite-shm|md|sh|log|pid)$' // db files, docs, scripts, logs
    . '|^/(config|questions|router)\.php$#i';           // config and question list

if (preg_match($private, $path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Not found');
}

return false; // everything else: let the built-in server handle it normally
