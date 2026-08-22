<?php

use App\Http\Controllers\Admin\BenchmarkController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\SearchScopeController;
use App\Http\Controllers\Admin\StoreApiTokenController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportController as ApiImportController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.api_token')->get('/health', [HealthController::class, 'check']);

// Store-token-authenticated bulk import. The bearer token identifies the store;
// the {store} id in the URL must match it (enforced in the controller).
Route::middleware('auth.api_token')->prefix('import')->group(function () {
    Route::post('{store}/categories', [ApiImportController::class, 'categories'])->name('import.categories');
    Route::post('{store}/products', [ApiImportController::class, 'products'])->name('import.products');
    Route::get('{store}/status/{requestId}', [ApiImportController::class, 'status'])->name('import.status');
});

Route::middleware('auth.api_token')->prefix('search')->group(function () {
    Route::get('/', [SearchController::class, 'search'])->name('search');
    Route::get('/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::post('/click', [SearchController::class, 'trackClick'])->name('search.click');
});

Route::middleware(['web', 'auth', 'search.admin'])->prefix('admin/search')->group(function () {

    // manage synonyms — search_admin, merchandiser
    Route::middleware('permission:manage synonyms')->group(function () {
        // Literal routes MUST precede the apiResource, or `synonyms/export` and
        // `synonyms/import` are captured by the `synonyms/{synonym}` wildcard.
        Route::post('synonyms/import', [\App\Http\Controllers\Admin\SynonymController::class, 'import'])
            ->name('admin.synonyms.import');
        Route::get('synonyms/export', [\App\Http\Controllers\Admin\SynonymController::class, 'export'])
            ->name('admin.synonyms.export');
        Route::apiResource('synonyms', \App\Http\Controllers\Admin\SynonymController::class);
    });

    // manage query rules — search_admin, merchandiser
    Route::middleware('permission:manage query rules')->group(function () {
        // Literal routes MUST precede the apiResource, or `query-rules/export` and
        // `query-rules/import` are captured by the `query-rules/{query_rule}` wildcard.
        Route::post('query-rules/import', [\App\Http\Controllers\Admin\QueryRuleController::class, 'import'])
            ->name('api.admin.query-rules.import');
        Route::get('query-rules/export', [\App\Http\Controllers\Admin\QueryRuleController::class, 'export'])
            ->name('api.admin.query-rules.export');
        Route::apiResource('query-rules', \App\Http\Controllers\Admin\QueryRuleController::class)
            ->names([
                'index'   => 'api.admin.query-rules.index',
                'store'   => 'api.admin.query-rules.store',
                'show'    => 'api.admin.query-rules.show',
                'update'  => 'api.admin.query-rules.update',
                'destroy' => 'api.admin.query-rules.destroy',
            ]);
    });

    // manage campaigns — search_admin, merchandiser
    Route::middleware('permission:manage campaigns')->group(function () {
        Route::apiResource('campaigns', \App\Http\Controllers\Admin\CampaignController::class)
            ->names([
                'index'   => 'api.admin.campaigns.index',
                'store'   => 'api.admin.campaigns.store',
                'show'    => 'api.admin.campaigns.show',
                'update'  => 'api.admin.campaigns.update',
                'destroy' => 'api.admin.campaigns.destroy',
            ]);
    });

    // view analytics — search_admin, merchandiser, analyst, read_only
    Route::middleware('permission:view analytics')->group(function () {
        Route::get('analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'dashboard']);
        Route::get('analytics/grafana', [\App\Http\Controllers\Admin\AnalyticsController::class, 'grafana']);
        Route::get('analytics/top-queries', [\App\Http\Controllers\Admin\AnalyticsController::class, 'topQueries']);
        Route::get('analytics/zero-results', [\App\Http\Controllers\Admin\AnalyticsController::class, 'zeroResults']);
    });

    // view search preview — search_admin, merchandiser, analyst, read_only
    Route::middleware('permission:view search preview')->group(function () {
        Route::post('preview', [\App\Http\Controllers\Admin\SearchPreviewController::class, 'preview']);
        Route::post('preview/explain', [\App\Http\Controllers\Admin\SearchPreviewController::class, 'explain']);
    });

    // view audit log — search_admin, analyst
    Route::middleware('permission:view audit log')->group(function () {
        Route::get('audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index']);
    });

    // Accessible to all admin roles (used by store switcher and scope pickers)
    Route::get('stores', [\App\Http\Controllers\Admin\StoreController::class, 'index']);
    Route::get('scope/categories', [SearchScopeController::class, 'categories']);
    Route::get('scope/brands', [SearchScopeController::class, 'brands']);
});

Route::middleware(['web', 'auth', 'search.admin'])->prefix('admin')->group(function () {

    // manage stores — search_admin only
    Route::middleware('permission:manage stores')->group(function () {
        Route::get('stores', [\App\Http\Controllers\Admin\StoreController::class, 'index']);
        Route::post('stores', [\App\Http\Controllers\Admin\StoreController::class, 'store']);
        Route::put('stores/{store}', [\App\Http\Controllers\Admin\StoreController::class, 'update']);
        Route::delete('stores/{store}', [\App\Http\Controllers\Admin\StoreController::class, 'destroy']);
        Route::post('stores/{id}/restore', [\App\Http\Controllers\Admin\StoreController::class, 'restore']);
        Route::get('stores/{store}/categories', [\App\Http\Controllers\Admin\StoreCategoryController::class, 'index']);
        Route::put('stores/{store}/categories', [\App\Http\Controllers\Admin\StoreCategoryController::class, 'sync']);

        Route::get('stores/{store}/tokens', [StoreApiTokenController::class, 'index']);
        Route::post('stores/{store}/tokens', [StoreApiTokenController::class, 'store']);
        Route::delete('stores/{store}/tokens/{apiToken}', [StoreApiTokenController::class, 'destroy']);

        Route::get('imports', [ImportController::class, 'index']);
        Route::get('imports/{store}/history', [ImportController::class, 'history']);
        Route::post('imports/{store}/run', [ImportController::class, 'run']);
        Route::post('imports/{store}/cancel', [ImportController::class, 'cancel']);

        Route::post('benchmarks/run', [BenchmarkController::class, 'run']);
        Route::get('benchmarks/history', [BenchmarkController::class, 'history']);
        Route::get('benchmarks/dataset', [BenchmarkController::class, 'downloadDataset']);
        Route::post('benchmarks/dataset', [BenchmarkController::class, 'uploadDataset']);
    });

    // manage users — search_admin only
    Route::middleware('permission:manage users')->group(function () {
        Route::get('users/roles', [\App\Http\Controllers\Admin\UserController::class, 'roles']);
        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
        Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store']);
        Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update']);
        Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy']);
    });
});
