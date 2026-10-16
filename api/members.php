
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query(
            'SELECT id, name, join_order, created_at
             FROM members
             ORDER BY join_order ASC'
        );

        echo json_encode([
            'success' => true,
            'members' => $stmt->fetchAll()
        ]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
        exit;
    }

    $name = trim($input['name'] ?? '');

    if ($name === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Member name or ID is required.'
        ]);
        exit;
    }

    if (strlen($name) > 100) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Member name must not exceed 100 bytes.'
        ]);
        exit;
    }

    // Case-insensitive duplicate detection.
    $nameKey = mb_strtolower($name, 'UTF-8');

    $stmt = $pdo->prepare(
        'SELECT id FROM members WHERE name_key = ?'
    );
    $stmt->execute([$nameKey]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'That member already exists.'
        ]);
        exit;
    }

    // Members may only join before the first week starts.
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM contributions'
    );

    if ((int) $stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Members cannot join after contributions begin.'
        ]);
        exit;
    }

    $stmt = $pdo->query(
        'SELECT COALESCE(MAX(join_order), 0) + 1 FROM members'
    );
    $joinOrder = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO members (name, name_key, join_order)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$name, $nameKey, $joinOrder]);

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Member added successfully.',
        'member' => [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'join_order' => $joinOrder
        ]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ]);
}
