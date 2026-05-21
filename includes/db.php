<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a singleton PDO database connection.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Return JSON error for AJAX requests
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                http_response_code(500);
                die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
            }
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2 style="color:#c0392b;">&#9888; Database Connection Error</h2>
                <p>Could not connect to the database. Please run <a href="' . APP_URL . '/setup.php">setup.php</a> first.</p>
                <small style="color:#999;">' . htmlspecialchars($e->getMessage()) . '</small>
            </div>');
        }
    }
    return $pdo;
}

/**
 * Execute a prepared query and return the statement.
 */
function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows.
 */
function dbFetchAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Fetch a single row.
 */
function dbFetchOne(string $sql, array $params = []): ?array {
    $row = dbQuery($sql, $params)->fetch();
    return $row ?: null;
}

/**
 * Fetch a single column value.
 */
function dbFetchColumn(string $sql, array $params = []): mixed {
    return dbQuery($sql, $params)->fetchColumn();
}
