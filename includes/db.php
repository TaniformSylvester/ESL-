<?php
/**
 * PDO database connection (singleton).
 * Always use getDB() — never open a second connection.
 */

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());

            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die('Database connection failed: ' . $e->getMessage());
            }

            http_response_code(500);
            die('A system error occurred. Please try again later.');
        }
    }

    return $pdo;
}
