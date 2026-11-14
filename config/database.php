<?php
/**
 * Database connection (Supabase PostgreSQL through PDO).
 * Credentials come from environment variables or from the ".env" file.
 * Nothing sensitive is hard-coded here.
 */
function load_env(): void
{
    $file = __DIR__ . '/../.env';
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    load_env();
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');
    $ssl  = getenv('DB_SSLMODE') ?: 'require';

    if (!$host || !$user) {
        error_log('UmaLink: database settings are missing. Create the .env file.');
        http_response_code(500);
        exit('The system is not configured yet. Please contact the administrator.');
    }
    try {
        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$name;sslmode=$ssl",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        // The real reason goes to the server log only, never to the browser.
        error_log('UmaLink DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to connect to the database. Please try again later.');
    }
    return $pdo;
}
