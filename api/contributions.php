
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET: Show member balances for the current week.
    if ($method === 'GET') {
        $stmt = $pdo->query(
            'SELECT current_week, weekly_contribution_pesewas
             FROM settings
             WHERE id = 1'
        );

        $settings = $stmt->fetch();

        if (!$settings) {
            throw new RuntimeException('Susu settings were not found.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, status
             FROM weeks
             WHERE week_number = ?'
        );

        $stmt->execute([(int) $settings['current_week']]);
        $week = $stmt->fetch();

        if (!$week) {
            throw new RuntimeException('The current week was not found.');
        }

        $stmt = $pdo->prepare(
            'SELECT
                m.id AS member_id,
                m.name,
                m.join_order,
                COALESCE(SUM(c.amount_pesewas), 0) AS paid_pesewas
             FROM members m
             LEFT JOIN contributions c
                ON c.member_id = m.id
                AND c.week_id = ?
             GROUP BY m.id, m.name, m.join_order
             ORDER BY m.join_order ASC'
        );

        $stmt->execute([(int) $week['id']]);
        $members = $stmt->fetchAll();

        $weeklyAmount = (int) $settings['weekly_contribution_pesewas'];

        foreach ($members as &$member) {
            $paid = (int) $member['paid_pesewas'];

            $member['member_id'] = (int) $member['member_id'];
            $member['join_order'] = (int) $member['join_order'];
            $member['paid_pesewas'] = $paid;
            $member['owed_pesewas'] = max(0, $weeklyAmount - $paid);
            $member['paid_ghs'] = number_format($paid / 100, 2, '.', '');
            $member['owed_ghs'] = number_format(
                max(0, $weeklyAmount - $paid) / 100,
                2,
                '.',
                ''
            );
        }
        unset($member);

        echo json_encode([
            'success' => true,
            'week_number' => (int) $settings['current_week'],
            'week_status' => $week['status'],
            'weekly_contribution_ghs' => number_format(
                $weeklyAmount / 100,
                2,
                '.',
                ''
            ),
            'members' => $members
        ]);
        exit;
    }

    // Only GET and POST are supported.
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    // Read the JSON sent by JavaScript.
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
        exit;
    }

    $memberId = filter_var(
        $input['member_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $amount = trim((string) ($input['amount'] ?? ''));

    if (!$memberId || $memberId < 1) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Select a valid member.'
        ]);
        exit;
    }

    // Accept amounts such as 10, 10.5 or 10.50.
    // Reject zero, negative values and more than two decimal places.
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Enter a valid amount, such as 20.00.'
        ]);
        exit;
    }

    $parts = explode('.', $amount, 2);
    $whole = $parts[0];
    $fraction = str_pad($parts[1] ?? '', 2, '0');

    // Guard against amounts too large for PHP integers.
    if (
        strlen($whole) > 12 ||
        (strlen($whole) === 12 && strcmp($whole, '999999999999') > 0)
    ) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'The amount is too large.'
        ]);
        exit;
    }

    $amountPesewas = ((int) $whole * 100) + (int) $fraction;

    if ($amountPesewas <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'The amount must be greater than zero.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // Lock settings so concurrent payments cannot exceed the balance.
    $stmt = $pdo->query(
        'SELECT current_week, weekly_contribution_pesewas
         FROM settings
         WHERE id = 1
         FOR UPDATE'
    );

    $settings = $stmt->fetch();

    if (!$settings) {
        throw new RuntimeException('Susu settings were not found.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, status
         FROM weeks
         WHERE week_number = ?
         FOR UPDATE'
    );

    $stmt->execute([(int) $settings['current_week']]);
    $week = $stmt->fetch();

    if (!$week || $week['status'] !== 'open') {
        $pdo->rollBack();

        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'The current week is not open for payments.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM members WHERE id = ?'
    );

    $stmt->execute([$memberId]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();

        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Member not found.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount_pesewas), 0)
         FROM contributions
         WHERE member_id = ? AND week_id = ?'
    );

    $stmt->execute([$memberId, (int) $week['id']]);
    $alreadyPaid = (int) $stmt->fetchColumn();

    $weeklyAmount = (int) $settings['weekly_contribution_pesewas'];
    $remaining = $weeklyAmount - $alreadyPaid;

    if ($amountPesewas > $remaining) {
        $pdo->rollBack();

        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Payment exceeds the remaining balance of GHS '
                . number_format(max(0, $remaining) / 100, 2)
                . '.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO contributions (member_id, week_id, amount_pesewas)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([
        $memberId,
        (int) $week['id'],
        $amountPesewas
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Contribution recorded successfully.',
        'amount_paid_ghs' => number_format(
            $amountPesewas / 100,
            2,
            '.',
            ''
        ),
        'total_paid_ghs' => number_format(
            ($alreadyPaid + $amountPesewas) / 100,
            2,
            '.',
            ''
        ),
        'remaining_ghs' => number_format(
            ($remaining - $amountPesewas) / 100,
            2,
            '.',
            ''
        )
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong while processing the contribution.'
    ]);
}
