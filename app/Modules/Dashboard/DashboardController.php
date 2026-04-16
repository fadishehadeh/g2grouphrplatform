<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Core\Controller;
use App\Core\Request;
use Throwable;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $user = $this->app->auth()->user();
        $roleCode = $user['role_code'] ?? 'employee';
        $stats = $this->buildStats($user ?? []);

        $view = match ($roleCode) {
            'super_admin' => 'dashboard.super-admin',
            'hr_only' => 'dashboard.hr-admin',
            'manager' => 'dashboard.manager',
            default => 'dashboard.employee',
        };

        $announcements = $this->recentAnnouncements();

        $this->render($view, [
            'title' => 'Dashboard',
            'pageTitle' => 'Dashboard',
            'user' => $user,
            'stats' => $stats,
            'announcements' => $announcements,
        ]);
    }

    private function buildStats(array $user): array
    {
        $stats = [
            'headcount' => 0,
            'pendingApprovals' => 0,
            'onboardingOpen' => 0,
            'documentsExpiring' => 0,
            'teamMembers' => 0,
            'leaveBalance' => 0,
        ];

        try {
            $db = $this->app->database();
            $stats['headcount'] = (int) $db->fetchValue("SELECT COUNT(*) FROM employees WHERE archived_at IS NULL");
            $stats['onboardingOpen'] = (int) $db->fetchValue("SELECT COUNT(*) FROM employee_onboarding WHERE status IN ('pending','in_progress')");
            $stats['documentsExpiring'] = (int) $db->fetchValue(
                "SELECT COUNT(*) FROM employee_documents WHERE is_current = 1 AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            );

            if (($user['role_code'] ?? null) === 'manager' && !empty($user['employee_id'])) {
                $stats['teamMembers'] = (int) $db->fetchValue(
                    'SELECT COUNT(*) FROM employees WHERE manager_employee_id = :manager_id AND archived_at IS NULL',
                    ['manager_id' => $user['employee_id']]
                );

                $stats['pendingApprovals'] = (int) $db->fetchValue(
                    "SELECT COUNT(*)
                     FROM leave_requests lr
                     INNER JOIN employees e ON e.id = lr.employee_id
                     WHERE e.manager_employee_id = :manager_id AND lr.status = 'pending_manager'",
                    ['manager_id' => $user['employee_id']]
                );
            }

            if (($user['role_code'] ?? null) === 'employee' && !empty($user['employee_id'])) {
                $stats['leaveBalance'] = (float) $db->fetchValue(
                    'SELECT COALESCE(SUM(closing_balance), 0) FROM leave_balances WHERE employee_id = :employee_id AND balance_year = :balance_year',
                    ['employee_id' => $user['employee_id'], 'balance_year' => date('Y')]
                );

                $stats['pendingApprovals'] = (int) $db->fetchValue(
                    "SELECT COUNT(*) FROM leave_requests WHERE employee_id = :employee_id AND status IN ('submitted','pending_manager','pending_hr')",
                    ['employee_id' => $user['employee_id']]
                );
            }

            if (in_array($user['role_code'] ?? '', ['super_admin', 'hr_only'], true)) {
                $stats['pendingApprovals'] = (int) $db->fetchValue(
                    "SELECT COUNT(*) FROM leave_requests WHERE status IN ('pending_manager','pending_hr')"
                );
            }
        } catch (Throwable $throwable) {
            // Keep zeroed stats if the database is not yet imported or reachable.
        }

        return $stats;
    }

    private function recentAnnouncements(): array
    {
        try {
            $db = $this->app->database();

            return $db->fetchAll(
                "SELECT a.id, a.title, a.priority, a.created_at,
                        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), 'System') AS created_by_name
                 FROM announcements a
                 LEFT JOIN users u ON u.id = a.created_by
                 WHERE a.status = 'published'
                   AND (a.starts_at IS NULL OR a.starts_at <= NOW())
                   AND (a.ends_at IS NULL OR a.ends_at >= NOW())
                 ORDER BY a.created_at DESC
                 LIMIT 5"
            );
        } catch (Throwable $throwable) {
            return [];
        }
    }
}