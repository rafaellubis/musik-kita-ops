<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstrumentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
	Route::resource('instruments', InstrumentController::class)
        ->except(['show']);
    Route::post('instruments/{instrument}/toggle-active',
        [InstrumentController::class, 'toggleActive'])
        ->name('instruments.toggle-active');
	Route::resource('packages', \App\Http\Controllers\PackageController::class);
	Route::resource('teachers', \App\Http\Controllers\TeacherController::class);
	Route::resource('payroll-configs', \App\Http\Controllers\PayrollConfigController::class)
        ->parameters(['payroll-configs' => 'payrollConfig']);
	Route::resource('holidays', \App\Http\Controllers\HolidayController::class);
	Route::resource('invoice-components', \App\Http\Controllers\InvoiceComponentController::class)
        ->parameters(['invoice-components' => 'InvoiceComponent']);
	Route::resource('rooms', \App\Http\Controllers\RoomController::class);
	
	// =====M02====
	Route::resource('students', \App\Http\Controllers\StudentController::class);
});

require __DIR__.'/auth.php';
