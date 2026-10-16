
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Use POST to close the current week.'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock settings so two requests cannot close the same week at once.
    $stmt = $pdo->query(
        'SELECT current_week, weekly_contribution_pesewas,
                next_member_order
         FROM settings
         WHERE id = 1
         FOR UPDATE'
    );

    $settings = $stmt->fetch();

    if (!$settings) {
        throw new RuntimeException('Susu settings were not found.');
    }

    $currentWeek = (int) $settings['current_week'];
    $weeklyAmount = (int) $settings['weekly_contribution_pesewas'];
    $nextOrder = (int) $settings['next_member_order'];

    $stmt = $pdo->prepare(
        'SELECT id, status
         FROM weeks
         WHERE week_number = ?
         FOR UPDATE'
    );
    $stmt->execute([$currentWeek]);
    $week = $stmt->fetch();

    if (!$week || $week['status'] !== 'open') {
        $pdo->rollBack();

        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'The current week is not open.'
        ]);
        exit;
    }

    // Find every member and their total contribution this week.
    $stmt = $pdo->prepare(
        'SELECT
            m.id,
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

    if (count($members) === 0) {
        $pdo->rollBack();

        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Add members before closing the week.'
        ]);
        exit;
    }

    // Block closure if any member has not paid the full amount.
    $unpaidMembers = [];

    foreach ($members as $member) {
        $paid = (int) $member['paid_pesewas'];

        if ($paid < $weeklyAmount) {
            $unpaidMembers[] = [
                'name' => $member['name'],
                'paid_kes' => number_format($paid / 100, 2, '.', ''),
                'owed_kes' => number_format(
                    ($weeklyAmount - $paid) / 100,
                    2,
                    '.',
                    ''
                )
            ];
        }
    }

    if (!empty($unpaidMembers)) {
        $pdo->rollBack();

        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot close the week. Some members still owe money.',
            'unpaid_members' => $unpaidMembers
        ]);
        exit;
    }

    // Find the next recipient in join order.
    $recipient = null;

    foreach ($members as $member) {
        if ((int) $member['join_order'] === $nextOrder) {
            $recipient = $member;
            break;
        }
    }

    if ($recipient === null) {
        // Recover gracefully if the stored next order is out of range.
        $recipient = $members[0];
        $nextOrder = (int) $recipient['join_order'];
    }

    // Calculate the total pot using integer cents.
    $pot = 0;

    foreach ($members as $member) {
        $pot += (int) $member['paid_pesewas'];
    }

    // Record the payout. week_id is unique in the payouts table.
    $stmt = $pdo->prepare(
        'INSERT INTO payouts (week_id, recipient_member_id, amount_pesewas)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([
        (int) $week['id'],
        (int) $recipient['id'],
        $pot
    ]);

    // Mark this week as closed.
    $stmt = $pdo->prepare(
        "UPDATE weeks
         SET status = 'closed', closed_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'open'"
    );
    $stmt->execute([(int) $week['id']]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The week could not be closed.');
    }

    // Rotate to the next member, wrapping to the beginning when needed.
    $orders = array_map(
        fn($member) => (int) $member['join_order'],
        $members
    );

    sort($orders, SORT_NUMERIC);

    $position = array_search($nextOrder, $orders, true);

    if ($position === false) {
        $position = array_search(
            (int) $recipient['join_order'],
            $orders,
            true
        );
    }

    $nextPosition = ($position + 1) % count($orders);
    $followingOrder = $orders[$nextPosition];

    $nextWeek = $currentWeek + 1;

    // Create the next week.
    $stmt = $pdo->prepare(
        "INSERT INTO weeks (week_number, status)
         VALUES (?, 'open')"
    );
    $stmt->execute([$nextWeek]);

    // Advance the group to the next week and recipient.
    $stmt = $pdo->prepare(
        'UPDATE settings
         SET current_week = ?, next_member_order = ?
         WHERE id = 1'
    );
    $stmt->execute([$nextWeek, $followingOrder]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Week closed and payout recorded successfully.',
        'closed_week' => $currentWeek,
        'next_week' => $nextWeek,
        'recipient' => $recipient['name'],
        'payout_kes' => number_format($pot / 100, 2, '.', '')
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not close the week. Check the server error log.'
    ]);
}
