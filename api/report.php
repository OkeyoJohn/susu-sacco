
<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed.'
    ]);
    exit;
}

try {
    // Get the group's settings.
    $stmt = $pdo->query(
        'SELECT current_week, weekly_contribution_pesewas,
                next_member_order
         FROM settings
         WHERE id = 1'
    );

    $settings = $stmt->fetch();

    if (!$settings) {
        throw new RuntimeException('Group settings were not found.');
    }

    $currentWeek = (int) $settings['current_week'];
    $weeklyAmount = (int) $settings['weekly_contribution_pesewas'];
    $nextOrder = (int) $settings['next_member_order'];

    // Get the current week record.
    $stmt = $pdo->prepare(
        'SELECT id, status
         FROM weeks
         WHERE week_number = ?'
    );

    $stmt->execute([$currentWeek]);
    $week = $stmt->fetch();

    if (!$week) {
        throw new RuntimeException('Current week was not found.');
    }

    // Count registered members.
    $memberCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM members'
    )->fetchColumn();

    // Calculate contributions collected this week.
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount_pesewas), 0)
         FROM contributions
         WHERE week_id = ?'
    );

    $stmt->execute([(int) $week['id']]);
    $currentWeekCollected = (int) $stmt->fetchColumn();

    // Find the next payout recipient.
    $stmt = $pdo->prepare(
        'SELECT id, name, join_order
         FROM members
         WHERE join_order = ?'
    );

    $stmt->execute([$nextOrder]);
    $nextRecipient = $stmt->fetch();

    // Calculate lifetime payouts.
    $stmt = $pdo->query(
        'SELECT
            COUNT(*) AS payout_count,
            COALESCE(SUM(amount_pesewas), 0) AS total_payouts_pesewas
         FROM payouts'
    );

    $payoutTotals = $stmt->fetch();

    // Retrieve payout history.
    $stmt = $pdo->query(
        'SELECT
            p.id,
            w.week_number,
            m.name AS recipient_name,
            p.amount_pesewas,
            p.paid_at
         FROM payouts p
         INNER JOIN weeks w ON w.id = p.week_id
         INNER JOIN members m ON m.id = p.recipient_member_id
         ORDER BY w.week_number DESC'
    );

    $payoutHistory = $stmt->fetchAll();

    foreach ($payoutHistory as &$payout) {
        $payout['id'] = (int) $payout['id'];
        $payout['week_number'] = (int) $payout['week_number'];
        $payout['amount_kes'] = number_format(
            (int) $payout['amount_pesewas'] / 100,
            2,
            '.',
            ''
        );
        unset($payout['amount_pesewas']);
    }

    unset($payout);

    echo json_encode([
        'success' => true,
        'current_week' => $currentWeek,
        'week_status' => $week['status'],
        'weekly_contribution_kes' => number_format(
            $weeklyAmount / 100,
            2,
            '.',
            ''
        ),
        'member_count' => $memberCount,
        'current_week_collected_kes' => number_format(
            $currentWeekCollected / 100,
            2,
            '.',
            ''
        ),
        'next_recipient' => $nextRecipient
            ? $nextRecipient['name']
            : null,
        'payout_count' => (int) $payoutTotals['payout_count'],
        'total_payouts_kes' => number_format(
            (int) $payoutTotals['total_payouts_pesewas'] / 100,
            2,
            '.',
            ''
        ),
        'payout_history' => $payoutHistory
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not load the group report.'
    ]);
}
