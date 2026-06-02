<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Modules\Jobs\JobApplicantsController;
use App\Modules\Jobs\JobsController;

$router = $app->router();

$auth = [AuthMiddleware::class];

// ------------------------------------------------------------------ //
//  HR Jobs Management
// ------------------------------------------------------------------ //
$router->get('/admin/jobs',                           [JobsController::class, 'index'],          $auth);
$router->get('/admin/jobs/create',                    [JobsController::class, 'create'],         $auth);
$router->post('/admin/jobs',                          [JobsController::class, 'store'],          $auth);
$router->get('/admin/jobs/categories',                [JobsController::class, 'categories'],     $auth);
$router->post('/admin/jobs/categories',               [JobsController::class, 'storeCategory'],  $auth);
$router->post('/admin/jobs/categories/{id}/edit',     [JobsController::class, 'updateCategory'], $auth);
$router->post('/admin/jobs/categories/{id}/delete',   [JobsController::class, 'destroyCategory'],$auth);

// Applicants (must be before /admin/jobs/{id} to avoid conflict)
$router->get('/admin/jobs/applicants',                [JobApplicantsController::class, 'index'],        $auth);
$router->get('/admin/jobs/job-bank',                  [JobApplicantsController::class, 'jobBank'],       $auth);
$router->get('/admin/jobs/applicants/export',         [JobApplicantsController::class, 'export'],        $auth);
$router->get('/admin/jobs/applicants/{id}',           [JobApplicantsController::class, 'show'],          $auth);
$router->post('/admin/jobs/applicants/{id}/status',   [JobApplicantsController::class, 'updateStatus'],  $auth);
$router->get('/admin/jobs/applicants/{id}/cv',        [JobApplicantsController::class, 'downloadCv'],    $auth);

// Job CRUD
$router->get('/admin/jobs/{id}',                      [JobsController::class, 'show'],          $auth);
$router->get('/admin/jobs/{id}/edit',                 [JobsController::class, 'edit'],          $auth);
$router->post('/admin/jobs/{id}/edit',                [JobsController::class, 'update'],        $auth);
$router->post('/admin/jobs/{id}/status',              [JobsController::class, 'updateStatus'],  $auth);
$router->post('/admin/jobs/{id}/delete',              [JobsController::class, 'destroy'],       $auth);
