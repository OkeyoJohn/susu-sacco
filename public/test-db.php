<?php

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query('SELECT DATABASE() AS db_name');
    $result = $stmt->fetch();

    echo 'Database connected successfully!<br>';
    echo 'Connected database: ' . htmlspecialchars($result['db_name']);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Could not verify the database connection.';
}