<?php

declare(strict_types=1);

use App\Middleware\CareersAuthMiddleware;
use App\Middleware\CareersGuestMiddleware;
use App\Modules\Careers\CareersAuthController;
use App\Modules\Careers\CareersPortalController;
use App\Modules\Careers\CareersProfileController;

$router = $app->router();

// ------------------------------------------------------------------ //
//  Public — no auth needed
// ------------------------------------------------------------------ //
$router->get('/careers',                         [CareersPortalController::class, 'board']);
$router->get('/careers/jobs/{slug}',             [CareersPortalController::class, 'showJob']);

// Auth (guests only for login/register)
$router->get('/careers/register',                [CareersAuthController::class, 'showRegister'],  [CareersGuestMiddleware::class]);
$router->post('/careers/register',               [CareersAuthController::class, 'register'],       [CareersGuestMiddleware::class]);
$router->get('/careers/login',                   [CareersAuthController::class, 'showLogin'],      [CareersGuestMiddleware::class]);
$router->post('/careers/login',                  [CareersAuthController::class, 'login'],          [CareersGuestMiddleware::class]);
$router->get('/careers/otp',                     [CareersAuthController::class, 'showOtp']);
$router->post('/careers/otp',                    [CareersAuthController::class, 'verifyOtp']);
$router->post('/careers/otp/resend',             [CareersAuthController::class, 'resendOtp']);
$router->post('/careers/logout',                 [CareersAuthController::class, 'logout']);

// ------------------------------------------------------------------ //
//  Authenticated portal routes
// ------------------------------------------------------------------ //
$router->get('/careers/dashboard',               [CareersPortalController::class,  'dashboard'],          [CareersAuthMiddleware::class]);
$router->get('/careers/my-applications',         [CareersPortalController::class,  'myApplications'],     [CareersAuthMiddleware::class]);
$router->post('/careers/my-applications/{id}/withdraw', [CareersPortalController::class, 'withdrawApplication'], [CareersAuthMiddleware::class]);
$router->get('/careers/apply/{jobId}',           [CareersPortalController::class,  'showApply'],          [CareersAuthMiddleware::class]);
$router->post('/careers/apply/{jobId}',          [CareersPortalController::class,  'submitApply'],        [CareersAuthMiddleware::class]);
$router->post('/careers/job-bank',               [CareersPortalController::class,  'submitToJobBank'],    [CareersAuthMiddleware::class]);

// Profile overview + uploads
$router->get('/careers/profile',                 [CareersProfileController::class, 'index'],              [CareersAuthMiddleware::class]);
$router->post('/careers/profile/photo',          [CareersProfileController::class, 'uploadPhoto'],        [CareersAuthMiddleware::class]);
$router->post('/careers/profile/cv',             [CareersProfileController::class, 'uploadCv'],           [CareersAuthMiddleware::class]);

// Personal & professional
$router->get('/careers/profile/personal',        [CareersProfileController::class, 'showPersonal'],       [CareersAuthMiddleware::class]);
$router->post('/careers/profile/personal',       [CareersProfileController::class, 'savePersonal'],       [CareersAuthMiddleware::class]);
$router->get('/careers/profile/professional',    [CareersProfileController::class, 'showProfessional'],   [CareersAuthMiddleware::class]);
$router->post('/careers/profile/professional',   [CareersProfileController::class, 'saveProfessional'],   [CareersAuthMiddleware::class]);

// CV Sections (dynamic type: experience, education, skill, etc.)
$router->get('/careers/profile/{type}',                      [CareersProfileController::class, 'showSection'],    [CareersAuthMiddleware::class]);
$router->post('/careers/profile/{type}',                     [CareersProfileController::class, 'addSection'],     [CareersAuthMiddleware::class]);
$router->get('/careers/profile/{type}/{id}/edit',            [CareersProfileController::class, 'editSectionForm'],[CareersAuthMiddleware::class]);
$router->post('/careers/profile/{type}/{id}/edit',           [CareersProfileController::class, 'updateSection'],  [CareersAuthMiddleware::class]);
$router->post('/careers/profile/{type}/{id}/delete',         [CareersProfileController::class, 'deleteSection'],  [CareersAuthMiddleware::class]);
$router->post('/careers/profile/{type}/reorder',             [CareersProfileController::class, 'reorderSections'],[CareersAuthMiddleware::class]);
