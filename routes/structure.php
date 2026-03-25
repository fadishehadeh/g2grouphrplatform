<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Structure\StructureController;

$router = $app->router();
$adminStructureMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [PermissionMiddleware::class, ['structure.manage']],
];

$router->get('/admin/structure', [StructureController::class, 'index'], $adminStructureMiddleware);
$router->get('/admin/companies', [StructureController::class, 'companies'], $adminStructureMiddleware);
$router->post('/admin/companies', [StructureController::class, 'storeCompany'], $adminStructureMiddleware);
$router->get('/admin/companies/{id}', [StructureController::class, 'editCompany'], $adminStructureMiddleware);
$router->post('/admin/companies/{id}', [StructureController::class, 'updateCompany'], $adminStructureMiddleware);
$router->post('/admin/companies/{id}/branches', [StructureController::class, 'storeCompanyBranch'], $adminStructureMiddleware);
$router->post('/admin/companies/{id}/departments', [StructureController::class, 'storeCompanyDepartment'], $adminStructureMiddleware);
$router->post('/admin/companies/{id}/job-titles', [StructureController::class, 'storeCompanyJobTitle'], $adminStructureMiddleware);
$router->post('/admin/companies/{id}/designations', [StructureController::class, 'storeCompanyDesignation'], $adminStructureMiddleware);
$router->get('/admin/branches', [StructureController::class, 'branches'], $adminStructureMiddleware);
$router->post('/admin/branches', [StructureController::class, 'storeBranch'], $adminStructureMiddleware);
$router->get('/admin/departments', [StructureController::class, 'departments'], $adminStructureMiddleware);
$router->post('/admin/departments', [StructureController::class, 'storeDepartment'], $adminStructureMiddleware);
$router->get('/admin/teams', [StructureController::class, 'teams'], $adminStructureMiddleware);
$router->post('/admin/teams', [StructureController::class, 'storeTeam'], $adminStructureMiddleware);
$router->get('/admin/job-titles', [StructureController::class, 'jobTitles'], $adminStructureMiddleware);
$router->post('/admin/job-titles', [StructureController::class, 'storeJobTitle'], $adminStructureMiddleware);
$router->get('/admin/designations', [StructureController::class, 'designations'], $adminStructureMiddleware);
$router->post('/admin/designations', [StructureController::class, 'storeDesignation'], $adminStructureMiddleware);
$router->get('/admin/reporting-lines', [StructureController::class, 'reportingLines'], $adminStructureMiddleware);
$router->post('/admin/reporting-lines', [StructureController::class, 'storeReportingLine'], $adminStructureMiddleware);