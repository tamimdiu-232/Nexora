<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PlatformOperationsController;
use App\Http\Controllers\RelatedDocumentsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated Core Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile.show');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    /*
    |--------------------------------------------------------------------------
    | Documents (Order matters: create before {document})
    |--------------------------------------------------------------------------
    */
    Route::get('/documents', [DocumentsController::class, 'index'])
        ->name('documents.index')
        ->middleware('permission:documents.view');

    Route::get('/documents/create', [DocumentsController::class, 'create'])
        ->name('documents.create')
        ->middleware('permission:documents.upload');

    Route::post('/documents', [DocumentsController::class, 'store'])
        ->name('documents.store')
        ->middleware('permission:documents.upload');

    Route::get('/documents/{document}', [DocumentsController::class, 'show'])
        ->name('documents.show')
        ->middleware('permission:documents.view');

    Route::get('/documents/{document}/edit', [DocumentsController::class, 'edit'])
        ->name('documents.edit')
        ->middleware('permission:documents.edit');

    Route::put('/documents/{document}', [DocumentsController::class, 'update'])
        ->name('documents.update')
        ->middleware('permission:documents.edit');

    Route::delete('/documents/{document}', [DocumentsController::class, 'destroy'])
        ->name('documents.destroy')
        ->middleware('permission:documents.delete');

    Route::get('/documents/{document}/preview', [DocumentsController::class, 'preview'])
        ->name('documents.preview')
        ->middleware('permission:documents.view');

    Route::get('/documents/{document}/download', [DocumentsController::class, 'download'])
        ->name('documents.download')
        ->middleware('permission:documents.view');

    Route::post('/documents/{document}/reprocess', [DocumentsController::class, 'reprocess'])
        ->name('documents.reprocess')
        ->middleware('permission:documents.edit');

    Route::post('/documents/{document}/archive', [DocumentsController::class, 'toggleArchive'])
        ->name('documents.archive')
        ->middleware('permission:documents.edit');

    Route::get('/documents/{document}/summary', [SummaryController::class, 'show'])
        ->name('documents.summary')
        ->middleware('permission:documents.view');

    Route::get('/documents/{document}/related', [RelatedDocumentsController::class, 'show'])
        ->name('documents.related')
        ->middleware('permission:documents.view');

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    Route::get('/search', [SearchController::class, 'index'])
        ->name('search.index')
        ->middleware('permission:search.use');

    /*
    |--------------------------------------------------------------------------
    | Ask AI Chat
    |--------------------------------------------------------------------------
    */
    Route::get('/chat', [ChatController::class, 'index'])
        ->name('chat.index')
        ->middleware('permission:chat.use');

    Route::post('/chat', [ChatController::class, 'store'])
        ->name('chat.store')
        ->middleware('permission:chat.use');

    Route::get('/chat/{session}', [ChatController::class, 'show'])
        ->name('chat.show')
        ->middleware('permission:chat.use');

    Route::post('/chat/{session}/ask', [ChatController::class, 'ask'])
        ->name('chat.ask')
        ->middleware('permission:chat.use');

    Route::delete('/chat/{session}', [ChatController::class, 'destroy'])
        ->name('chat.destroy')
        ->middleware('permission:chat.use');

    /*
    |--------------------------------------------------------------------------
    | Human Review Workflow
    |--------------------------------------------------------------------------
    */
    Route::get('/reviews', [ReviewController::class, 'index'])
        ->name('reviews.index')
        ->middleware('permission:reviews.view');

    Route::post('/messages/{message}/review', [ReviewController::class, 'submit'])
        ->name('reviews.submit')
        ->middleware('permission:reviews.submit');

    Route::post('/reviews/{review}/decide', [ReviewController::class, 'decide'])
        ->name('reviews.decide')
        ->middleware('permission:reviews.decide');

    /*
    |--------------------------------------------------------------------------
    | Administration (Organizations, Departments, Users)
    |--------------------------------------------------------------------------
    */
    Route::resource('departments', DepartmentController::class)
        ->except(['show'])
        ->middleware('permission:departments.manage');

    Route::resource('users', UserController::class)
        ->except(['show'])
        ->middleware('permission:users.manage');

    Route::resource('organizations', OrganizationController::class)
        ->except(['show', 'destroy'])
        ->middleware('permission:organizations.manage');

    /*
    |--------------------------------------------------------------------------
    | Platform Operations (Super Admin Only)
    |--------------------------------------------------------------------------
    */
    Route::prefix('platform')->name('platform.')->group(function () {
        Route::get('/', [PlatformOperationsController::class, 'dashboard'])
            ->name('dashboard')
            ->middleware('permission:system.view');

        Route::get('/health', [PlatformOperationsController::class, 'health'])
            ->name('health')
            ->middleware('permission:system.view');

        Route::get('/recovery', [PlatformOperationsController::class, 'recovery'])
            ->name('recovery')
            ->middleware('permission:security.manage');

        Route::post('/recovery/{targetUser}/reset-link', [PlatformOperationsController::class, 'sendRecoveryResetLink'])
            ->name('recovery.reset_link')
            ->middleware('permission:security.manage');

        Route::post('/recovery/{targetUser}/revoke-sessions', [PlatformOperationsController::class, 'revokeSessions'])
            ->name('recovery.revoke_sessions')
            ->middleware('permission:security.manage');

        Route::post('/recovery/{targetUser}/toggle-status', [PlatformOperationsController::class, 'toggleUserStatus'])
            ->name('recovery.toggle_status')
            ->middleware('permission:security.manage');

        Route::get('/incidents', [PlatformOperationsController::class, 'incidents'])
            ->name('incidents')
            ->middleware('permission:security.manage');

        Route::post('/incidents', [PlatformOperationsController::class, 'storeIncident'])
            ->name('incidents.store')
            ->middleware('permission:security.manage');

        Route::put('/incidents/{incident}', [PlatformOperationsController::class, 'updateIncident'])
            ->name('incidents.update')
            ->middleware('permission:security.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Audit Log Viewer
    |--------------------------------------------------------------------------
    */
    Route::get('/audit', [AuditController::class, 'index'])
        ->name('audit.index')
        ->middleware('permission:audit.view');
});
