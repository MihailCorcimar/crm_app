<?php

use App\Http\Controllers\Settings\ContactRoleController;
use App\Http\Controllers\Settings\CountryController;
use App\Http\Controllers\Settings\CompanySettingController;
use App\Http\Controllers\Settings\CalendarActionController;
use App\Http\Controllers\Settings\CalendarTypeController;
use App\Http\Controllers\Settings\DealStageController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'tenant.active'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/entities/countries', [CountryController::class, 'index'])
        ->middleware('module.permission:settings,read')
        ->name('settings.countries.index');
    Route::post('settings/entities/countries', [CountryController::class, 'store'])
        ->middleware('module.permission:settings,create')
        ->name('settings.countries.store');
    Route::put('settings/entities/countries/{country}', [CountryController::class, 'update'])
        ->middleware('module.permission:settings,update')
        ->name('settings.countries.update');
    Route::delete('settings/entities/countries/{country}', [CountryController::class, 'destroy'])
        ->middleware('module.permission:settings,delete')
        ->name('settings.countries.destroy');

    Route::get('settings/contacts/roles', [ContactRoleController::class, 'index'])
        ->middleware('module.permission:settings,read')
        ->name('settings.contact-roles.index');
    Route::post('settings/contacts/roles', [ContactRoleController::class, 'store'])
        ->middleware('module.permission:settings,create')
        ->name('settings.contact-roles.store');
    Route::put('settings/contacts/roles/{contactRole}', [ContactRoleController::class, 'update'])
        ->middleware('module.permission:settings,update')
        ->name('settings.contact-roles.update');
    Route::delete('settings/contacts/roles/{contactRole}', [ContactRoleController::class, 'destroy'])
        ->middleware('module.permission:settings,delete')
        ->name('settings.contact-roles.destroy');

    Route::get('settings/company', [CompanySettingController::class, 'index'])
        ->middleware('module.permission:settings,read')
        ->name('settings.company.index');
    Route::put('settings/company', [CompanySettingController::class, 'update'])
        ->middleware('module.permission:settings,update')
        ->name('settings.company.update');
    Route::get('settings/company/logo', [CompanySettingController::class, 'logo'])
        ->middleware('module.permission:settings,read')
        ->name('settings.company.logo');

    Route::get('settings/calendar/types', [CalendarTypeController::class, 'index'])
        ->middleware('module.permission:settings,read')
        ->name('settings.calendar-types.index');
    Route::post('settings/calendar/types', [CalendarTypeController::class, 'store'])
        ->middleware('module.permission:settings,create')
        ->name('settings.calendar-types.store');
    Route::put('settings/calendar/types/{calendarType}', [CalendarTypeController::class, 'update'])
        ->middleware('module.permission:settings,update')
        ->name('settings.calendar-types.update');
    Route::delete('settings/calendar/types/{calendarType}', [CalendarTypeController::class, 'destroy'])
        ->middleware('module.permission:settings,delete')
        ->name('settings.calendar-types.destroy');

    Route::get('settings/calendar/actions', [CalendarActionController::class, 'index'])
        ->middleware('module.permission:settings,read')
        ->name('settings.calendar-actions.index');
    Route::post('settings/calendar/actions', [CalendarActionController::class, 'store'])
        ->middleware('module.permission:settings,create')
        ->name('settings.calendar-actions.store');
    Route::put('settings/calendar/actions/{calendarAction}', [CalendarActionController::class, 'update'])
        ->middleware('module.permission:settings,update')
        ->name('settings.calendar-actions.update');
    Route::delete('settings/calendar/actions/{calendarAction}', [CalendarActionController::class, 'destroy'])
        ->middleware('module.permission:settings,delete')
        ->name('settings.calendar-actions.destroy');

    Route::get('settings/deals/stages', [DealStageController::class, 'index'])
        ->middleware('module.permission:settings,read')
        ->name('settings.deal-stages.index');
    Route::put('settings/deals/stages', [DealStageController::class, 'update'])
        ->middleware('module.permission:settings,update')
        ->name('settings.deal-stages.update');

});

Route::middleware(['auth'])->group(function () {
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
