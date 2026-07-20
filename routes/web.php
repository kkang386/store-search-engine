<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BenchmarkController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::prefix('admin')->middleware(['auth', 'search.admin'])->name('admin.')->group(function () {

    // All authenticated admin roles
    Route::get('/', [AdminDashboardController::class, 'dashboard'])->name('dashboard');

    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('admin.auth.login');
    })->name('logout');

    // Catch GET requests to /logout (stale links, browser back, session-expired redirects).
    Route::get('/logout', fn () => redirect()->route('admin.auth.login'));

    // view analytics — search_admin, merchandiser, analyst, read_only
    Route::middleware('permission:view analytics')->group(function () {
        Route::get('/analytics', [AdminDashboardController::class, 'analytics'])->name('analytics');
    });

    // manage query rules — search_admin, merchandiser
    Route::middleware('permission:manage query rules')->group(function () {
        Route::get('/query-rules', [AdminDashboardController::class, 'queryRules'])->name('query-rules.index');
    });

    // manage synonyms — search_admin, merchandiser
    Route::middleware('permission:manage synonyms')->group(function () {
        Route::get('/synonyms', [AdminDashboardController::class, 'synonyms'])->name('synonyms.index');
    });

    // manage campaigns — search_admin, merchandiser
    Route::middleware('permission:manage campaigns')->group(function () {
        Route::get('/campaigns', [AdminDashboardController::class, 'campaigns'])->name('campaigns.index');
    });

    // view search preview — search_admin, merchandiser, analyst, read_only
    Route::middleware('permission:view search preview')->group(function () {
        Route::get('/preview', [AdminDashboardController::class, 'preview'])->name('preview');
        Route::get('/ranking', [AdminDashboardController::class, 'ranking'])->name('ranking');
    });

    // view audit log — search_admin, analyst
    Route::middleware('permission:view audit log')->group(function () {
        Route::get('/audit-log', [AdminDashboardController::class, 'auditLog'])->name('audit-log');
    });

    // manage users — search_admin only
    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', fn () => view('admin.users.index', ['stores' => \App\Models\Store::active()->get()]))->name('users.index');
    });

    // manage stores — search_admin only
    Route::middleware('permission:manage stores')->group(function () {
        Route::get('/stores', [AdminDashboardController::class, 'stores'])->name('stores.index');
        Route::get('/stores/{store}/categories', [AdminDashboardController::class, 'storeCategories'])->name('stores.categories');
        Route::get('/imports', [AdminDashboardController::class, 'imports'])->name('imports.index');
        Route::get('/benchmarks', [AdminDashboardController::class, 'benchmarks'])->name('benchmarks.index');
        Route::get('/benchmarks/{runId}/report', [BenchmarkController::class, 'serveHtmlReport'])->name('benchmarks.report');
    });
});

Route::prefix('admin')->name('admin.auth.')->group(function () {
    Route::get('/login', [AdminDashboardController::class, 'login'])->name('login');
    Route::post('/login', [AdminDashboardController::class, 'authenticate'])->name('authenticate');
});
