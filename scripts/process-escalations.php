<?php
/**
 * Leave Request Escalation Processor
 *
 * For each pending leave request that has been sitting at a workflow step
 * longer than the configured escalation threshold, this script either:
 *   - Notifies the escalation target (notify_only = 1)
 *   - Reassigns the pending approval row to the escalation target (notify_only = 0)
 *
 * Run from CLI:  php scripts/process-escalations.php
 * Via cron:      0 * * * * php /path/to/scripts/process-escalations.php >> /path/to/logs/escalation.log 2>&1
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$app = new App\Core\Application(BASE_PATH);
$db  = $app->database();

echo '[' . date('Y-m-d H:i:s') . "] Escalation processor started.\n";

// Find all pending leave requests that have a linked workflow with escalation rules
$pendingRequests = $db->fetchAll(
    "SELECT lr.id AS request_id, lr.employee_id, lr.workflow_id, lr.current_step_order,
            lr.submitted_at, lr.status,
            COALESCE(
                (SELECT MAX(la2.updated_at) FROM leave_approvals la2
                 WHERE la2.leave_request_id = lr.id AND la2.decision != 'pending'),
                lr.submitted_at
            ) AS last_action_at
     FROM leave_requests lr
     WHERE lr.status IN ('pending_manager','pending_hr')
       AND lr.workflow_id IS NOT NULL"
);

if ($pendingRequests === []) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending requests with workflows.\n";
    exit(0);
}

$escalated = 0;
$notified  = 0;

foreach ($pendingRequests as $req) {
    $requestId   = (int) $req['request_id'];
    $workflowId  = (int) $req['workflow_id'];
    $stepOrder   = (int) $req['current_step_order'];
    $lastActionAt = strtotime((string) $req['last_action_at']);
    $hoursWaiting = (time() - $lastActionAt) / 3600;

    // Find escalation rule for this workflow + step
    $rule = $db->fetch(
        'SELECT * FROM approval_escalation_rules
         WHERE workflow_id = :workflow_id AND step_order = :step_order AND is_active = 1
         LIMIT 1',
        ['workflow_id' => $workflowId, 'step_order' => $stepOrder]
    );

    if ($rule === null) {
        continue;
    }

    $threshold = (int) $rule['escalate_after_hours'];

    if ($hoursWaiting < $threshold) {
        continue;
    }

    // Check if we already escalated this step recently (avoid re-escalating every minute)
    $alreadyEscalated = $db->fetchValue(
        "SELECT COUNT(*) FROM audit_logs
         WHERE entity_type = 'leave_request' AND entity_id = :id
           AND action_name = 'escalated' AND module_name = 'leave'
           AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)",
        ['id' => $requestId, 'hours' => $threshold]
    );

    if ((int) ($alreadyEscalated ?? 0) > 0) {
        continue;
    }

    // Resolve escalation target
    $escalateToUserId = null;
    $escalateToRoleId = null;

    switch ((string) $rule['escalate_to_type']) {
        case 'hr_only':
            $hrRoleId = $db->fetchValue("SELECT id FROM roles WHERE code = 'hr_only' LIMIT 1");
            $escalateToRoleId = $hrRoleId !== null && $hrRoleId !== false ? (int) $hrRoleId : null;
            break;
        case 'specific_role':
            $escalateToRoleId = $rule['escalate_to_role_id'] !== null ? (int) $rule['escalate_to_role_id'] : null;
            break;
        case 'specific_user':
            $escalateToUserId = $rule['escalate_to_user_id'] !== null ? (int) $rule['escalate_to_user_id'] : null;
            break;
    }

    if ((int) $rule['notify_only'] === 0) {
        // Reassign: update the pending approval row to the escalation target
        $db->execute(
            'UPDATE leave_approvals
             SET approver_user_id = :user_id, approver_role_id = :role_id, updated_at = NOW()
             WHERE leave_request_id = :request_id AND step_order = :step_order AND decision = :decision',
            [
                'user_id'    => $escalateToUserId,
                'role_id'    => $escalateToRoleId,
                'request_id' => $requestId,
                'step_order' => $stepOrder,
                'decision'   => 'pending',
            ]
        );
        $escalated++;
        echo "  ↑ Request #{$requestId} escalated at step {$stepOrder} after {$hoursWaiting:.1f}h\n";
    } else {
        $notified++;
        echo "  ✉ Request #{$requestId} escalation notification at step {$stepOrder} after {$hoursWaiting:.1f}h\n";
    }

    // Send notification to escalation target users
    if ($escalateToUserId !== null) {
        $db->execute(
            'INSERT INTO notifications (user_id, notification_type, title, message, reference_type, reference_id, action_url)
             VALUES (:user_id, :type, :title, :message, :ref_type, :ref_id, :url)',
            [
                'user_id'  => $escalateToUserId,
                'type'     => 'leave_escalation',
                'title'    => 'Leave Request Escalated to You',
                'message'  => 'Leave request #' . $requestId . ' has been waiting for ' . round($hoursWaiting) . ' hours and has been escalated to you.',
                'ref_type' => 'leave_request',
                'ref_id'   => $requestId,
                'url'      => '/leave/approvals',
            ]
        );
    } elseif ($escalateToRoleId !== null) {
        // Notify all users with the escalation role
        $roleUsers = $db->fetchAll(
            'SELECT id FROM users WHERE role_id = :role_id AND status = :status',
            ['role_id' => $escalateToRoleId, 'status' => 'active']
        );
        foreach ($roleUsers as $u) {
            $db->execute(
                'INSERT INTO notifications (user_id, notification_type, title, message, reference_type, reference_id, action_url)
                 VALUES (:user_id, :type, :title, :message, :ref_type, :ref_id, :url)',
                [
                    'user_id'  => (int) $u['id'],
                    'type'     => 'leave_escalation',
                    'title'    => 'Leave Request Escalated',
                    'message'  => 'Leave request #' . $requestId . ' has been waiting for ' . round($hoursWaiting) . ' hours.',
                    'ref_type' => 'leave_request',
                    'ref_id'   => $requestId,
                    'url'      => '/leave/approvals',
                ]
            );
        }
    }

    // Audit log the escalation
    $db->execute(
        'INSERT INTO audit_logs (user_id, module_name, entity_type, entity_id, action_name, new_values)
         VALUES (:user_id, :module, :entity_type, :entity_id, :action, :new_values)',
        [
            'user_id'    => null,
            'module'     => 'leave',
            'entity_type'=> 'leave_request',
            'entity_id'  => $requestId,
            'action'     => 'escalated',
            'new_values' => json_encode([
                'step_order'     => $stepOrder,
                'hours_waiting'  => round($hoursWaiting, 1),
                'escalated_to'   => $rule['escalate_to_type'],
                'notify_only'    => (bool) $rule['notify_only'],
            ]),
        ]
    );
}

echo '[' . date('Y-m-d H:i:s') . "] Done. Escalated: {$escalated}, Notified: {$notified}\n";
