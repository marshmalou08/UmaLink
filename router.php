<?php
// Router for PHP's built-in server:  php -S localhost:8000 router.php
// Blocks direct access to .env, .sql and other sensitive files.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#(^|/)\.(?!well-known)|\.sql$|^/(config|includes|database)/#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
return false;
