<?php
// Router for PHP's built-in server (local testing only). Copies the two rewrite rules from static/client/.htaccess
// and blocks database files, so the admin behaves like it does on DreamHost.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$root = $_SERVER['DOCUMENT_ROOT'];

if (preg_match('/\.(sqlite|db|log)$/i', $path)) { http_response_code(403); echo 'Forbidden'; return true; }
if (basename($path) === 'galleries-index.json') { http_response_code(403); echo 'Forbidden'; return true; }   // like static/client/.htaccess

$run = function (string $file) use ($root) {
    $_SERVER['SCRIPT_NAME'] = $file;
    $_SERVER['SCRIPT_FILENAME'] = $root . $file;
    chdir(dirname($root . $file));
    require $root . $file;
    return true;
};
if (preg_match('#^/client/g/([a-f0-9]{24})/?$#', $path, $m)) { $_GET['t'] = $_REQUEST['t'] = $m[1]; return $run('/client/index.php'); }
if (preg_match('#^/client/new/?$#', $path)) return $run('/client/new.php');

return false;   // everything else: let the built-in server serve the file (or the folder's index.php / index.html)
