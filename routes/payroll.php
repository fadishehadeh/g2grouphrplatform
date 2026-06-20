<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PayrollEnabledMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Payroll\PayrollController;

$router = $app->router();

$base   = [AuthMiddleware::class, AccountStatusMiddleware::class, PayrollEnabledMiddleware::class];
$hrRole = [RoleMiddleware::class, ['super_admin', 'hr_only']];

$router->get('/payroll/salary-structures',              [PayrollController::class, 'salaryStructures'],   [...$base, $hrRole]);
$router->get('/payroll/salary-structures/{id}/edit',   [PayrollController::class, 'editSalaryStructure'], [...$base, $hrRole]);
$router->post('/payroll/salary-structures/{id}/edit',  [PayrollController::class, 'saveSalaryStructure'], [...$base, $hrRole]);

$router->get('/payroll/runs',                          [PayrollController::class, 'runs'],            [...$base, $hrRole]);
$router->get('/payroll/runs/create',                   [PayrollController::class, 'createRunForm'],   [...$base, $hrRole]);
$router->post('/payroll/runs/create',                  [PayrollController::class, 'createRun'],       [...$base, $hrRole]);
$router->get('/payroll/runs/{id}',                     [PayrollController::class, 'runDetail'],       [...$base, $hrRole]);
$router->post('/payroll/runs/items/{id}',              [PayrollController::class, 'updateRunItem'],   [...$base, $hrRole]);
$router->get('/payroll/runs/{id}/finalize',            [PayrollController::class, 'finalizeRunForm'], [...$base, $hrRole]);
$router->post('/payroll/runs/{id}/finalize',           [PayrollController::class, 'finalizeRun'],     [...$base, $hrRole]);
$router->get('/payroll/runs/{runId}/payslip/{empId}',  [PayrollController::class, 'downloadPayslip'], [...$base, $hrRole]);
