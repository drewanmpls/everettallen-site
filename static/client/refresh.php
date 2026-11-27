<?php
// Admin only: clears the server's PHP code cache so a fresh deploy shows up right away.
declare(strict_types=1);
require __DIR__ . '/lib.php';
noindex();
header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8');
start_session();
if (empty($_SESSION['admin'])) { http_response_code(403); echo "Log in to the admin first, then open this page again.\n"; exit; }
$ok = function_exists('opcache_reset') ? @opcache_reset() : false;
echo $ok ? "Cache cleared. Reload the admin now.\n" : "No code cache to clear (or it can't be cleared from here).\n";
