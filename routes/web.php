<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
	if (Auth::check()) {
		return redirect('/dashboard');
	}

	return Inertia::render('Landing');
})->name('login');

Route::middleware('guest')->group(function () {
	Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
	Route::get('/dashboard', fn () => Inertia::render('Dashboard'));
	Route::get('/products', fn () => Inertia::render('Products'));
	Route::get('/inventories', fn () => Inertia::render('Inventories'));
	Route::get('/sales-report', fn () => Inertia::render('SalesReport'));
	Route::get('/product-profit-analysis', fn () => Inertia::render('ProductProfitAnalysis'));
	Route::get('/trucking', fn () => Inertia::render('Trucking'));
	Route::get('/employees', fn () => Inertia::render('Employees'));
	Route::get('/branches', fn () => Inertia::render('Branches'));
	Route::get('/users', fn () => Inertia::render('Users'));
	Route::get('/settings', fn () => Inertia::render('Settings'));

	Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
