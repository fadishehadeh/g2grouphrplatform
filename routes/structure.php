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
$router->post('/admin/branches/{id}/update', [StructureController::class, 'updateBranch'], $adminStructureMiddleware);
$router->get('/admin/departments', [StructureController::class, 'departments'], $adminStructureMiddleware);
$router->post('/admin/departments', [StructureController::class, 'storeDepartment'], $adminStructureMiddleware);
$router->post('/admin/departments/{id}/update', [StructureController::class, 'updateDepartment'], $adminStructureMiddleware);
$router->get('/admin/teams', [StructureController::class, 'teams'], $adminStructureMiddleware);
$router->post('/admin/teams', [StructureController::class, 'storeTeam'], $adminStructureMiddleware);
$router->post('/admin/teams/{id}/update', [StructureController::class, 'updateTeam'], $adminStructureMiddleware);
$router->get('/admin/job-titles', [StructureController::class, 'jobTitles'], $adminStructureMiddleware);
$router->post('/admin/job-titles', [StructureController::class, 'storeJobTitle'], $adminStructureMiddleware);
$router->post('/admin/job-titles/{id}/update', [StructureController::class, 'updateJobTitle'], $adminStructureMiddleware);
$router->get('/admin/designations', [StructureController::class, 'designations'], $adminStructureMiddleware);
$router->post('/admin/designations', [StructureController::class, 'storeDesignation'], $adminStructureMiddleware);
$router->post('/admin/designations/{id}/update', [StructureController::class, 'updateDesignation'], $adminStructureMiddleware);
$router->get('/admin/reporting-lines', [StructureController::class, 'reportingLines'], $adminStructureMiddleware);
$router->post('/admin/reporting-lines', [StructureController::class, 'storeReportingLine'], $adminStructureMiddleware);