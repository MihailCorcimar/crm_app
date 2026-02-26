<?php

use App\Http\Controllers\Access\PermissionGroupController;
use App\Http\Controllers\Access\UserManagementController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\TenantBillingController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantMemberController;
use App\Http\Controllers\TenantOnboardingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function (): void {
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::post('tenants/{tenant}/switch', [TenantController::class, 'switchTenant'])->name('tenants.switch');

    Route::middleware('tenant.active')->group(function (): void {
        Route::get('tenants/onboarding', [TenantOnboardingController::class, 'show'])->name('tenants.onboarding.show');
        Route::put('tenants/onboarding/branding', [TenantOnboardingController::class, 'updateBranding'])->name('tenants.onboarding.branding');
        Route::put('tenants/onboarding/permissions', [TenantOnboardingController::class, 'updatePermissions'])->name('tenants.onboarding.permissions');
        Route::post('tenants/onboarding/members', [TenantOnboardingController::class, 'addMember'])->name('tenants.onboarding.members');

        Route::get('tenants/billing', [TenantBillingController::class, 'show'])->name('tenants.billing.show');
        Route::post('tenants/billing/plans/{plan}/change', [TenantBillingController::class, 'changePlan'])->name('tenants.billing.change-plan');
        Route::post('tenants/billing/cancel', [TenantBillingController::class, 'cancel'])->name('tenants.billing.cancel');
        Route::post('tenants/billing/resume', [TenantBillingController::class, 'resume'])->name('tenants.billing.resume');
    });

    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/members', [TenantMemberController::class, 'store'])->name('tenants.members.store');
    Route::delete('tenants/{tenant}/members/{user}', [TenantMemberController::class, 'destroy'])->name('tenants.members.destroy');

    Route::middleware('tenant.active')->group(function (): void {
        Route::resource('access/users', UserManagementController::class)
            ->except(['show'])
            ->names('access.users');
        Route::resource('access/permission-groups', PermissionGroupController::class)
            ->except(['show'])
            ->names('access.permission-groups');

        Route::get('logs', [LogController::class, 'index'])->name('logs.index');

        Route::resource('calendar', CalendarController::class)
            ->except(['show'])
            ->parameters(['calendar' => 'calendar']);

        Route::get('people', fn () => to_route('contacts.index'))->name('people.index');
        Route::get('deals', function () {
            return Inertia::render('shared/Placeholder', [
                'title' => 'Negocios',
                'description' => 'Modulo em desenvolvimento.',
            ]);
        })->name('deals.index');

        Route::resource('contacts', ContactController::class);
        Route::post('entities/vies', [EntityController::class, 'lookupVat'])->name('entities.vies');
        Route::resource('entities', EntityController::class);
    });
});

require __DIR__.'/settings.php';
