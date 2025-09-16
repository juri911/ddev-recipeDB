<?php
require_once __DIR__ . '/../config.php';

/**
 * Returns a shared PDO connection.
 * Uses the $conn created in config.php; if not present, creates one.
 */
function get_db_connection(): PDO {
	global $conn, $servername, $dbport, $dbname, $username, $password;
	if ($conn instanceof PDO) {
		return $conn;
	}
	$dsn = "mysql:host={$servername};port={$dbport};dbname={$dbname};charset=utf8mb4";
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	$conn = new PDO($dsn, $username, $password, $options);
	return $conn;
}

/**
 * Helper for prepared queries.
 */
function db_query(string $sql, array $params = []): PDOStatement {
	$pdo = get_db_connection();
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	return $stmt;
}

