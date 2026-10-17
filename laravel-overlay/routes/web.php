<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentAiController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePermission as Perm;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:10,1');
});

Route::middleware(['auth', EnsureUserIsActive::class, 'throttle:120,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware(Perm::class . ':dashboard.view');

    // Documents
    Route::middleware(Perm::class . ':documents.view')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}/preview', [DocumentFileController::class, 'preview'])->name('documents.preview');
        Route::get('/documents/{document}/download', [DocumentFileController::class, 'download'])->name('documents.download');
        Route::post('/documents/{document}/summarize', [DocumentAiController::class, 'summarize'])->name('documents.summarize')->middleware('throttle:20,1');
        Route::get('/documents/{document}/related', [DocumentAiController::class, 'related'])->name('documents.related');
    });
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create')->middleware(Perm::class . ':documents.upload');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store')->middleware([Perm::class . ':documents.upload', 'throttle:30,1']);
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show')->middleware(Perm::class . ':documents.view');
    Route::middleware(Perm::class . ':documents.edit')->group(function () {
        Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::post('/documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
    });
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy')->middleware(Perm::class . ':documents.delete');
    Route::post('/documents/{document}/reprocess', [DocumentController::class, 'reprocess'])->name('documents.reprocess')->middleware(Perm::class . ':documents.reprocess');

    // Search & AI chat
    Route::get('/search', [SearchController::class, 'index'])->name('search.index')->middleware(Perm::class . ':search.use');
    Route::middleware(Perm::class . ':chat.use')->group(function () {
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
        Route::delete('/chat/{chatSession}', [ChatController::class, 'destroy'])->name('chat.destroy');
        Route::post('/chat/{chatSession}/ask', [ChatController::class, 'ask'])->name('chat.ask')->middleware('throttle:20,1');
    });

    // Human verification
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index')->middleware(Perm::class . ':reviews.view');
    Route::post('/messages/{message}/review', [ReviewController::class, 'store'])->name('reviews.store')->middleware(Perm::class . ':reviews.submit');
    Route::post('/reviews/{review}/decide', [ReviewController::class, 'decide'])->name('reviews.decide')->middleware(Perm::class . ':reviews.decide');

    // Administration
    Route::middleware(Perm::class . ':users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });
    Route::middleware(Perm::class . ':departments.manage')->group(function () {
        Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
    });
    Route::middleware(Perm::class . ':organizations.manage')->group(function () {
        Route::resource('organizations', OrganizationController::class)->except(['show', 'destroy']);
    });
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index')->middleware(Perm::class . ':audit.view');
});
