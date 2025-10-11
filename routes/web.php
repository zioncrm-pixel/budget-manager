<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetManagementController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CashFlowSourceController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/cashflow', [DashboardController::class, 'cashflow'])->name('cashflow.index');
    Route::get('/budgets/overview', [DashboardController::class, 'budgets'])->name('budgets.overview');
    Route::get('/cashflow/sources', [DashboardController::class, 'cashflowSources'])->name('cashflow.sources.index');

    Route::post('/budgets/manage', [BudgetManagementController::class, 'store'])->name('budgets.manage.store');
    Route::put('/budgets/manage/{budget}', [BudgetManagementController::class, 'update'])->whereNumber('budget')->name('budgets.manage.update');
    Route::delete('/budgets/manage/{budget}', [BudgetManagementController::class, 'destroy'])->whereNumber('budget')->name('budgets.manage.destroy');
    Route::delete('/budgets/manage/category/{category}', [BudgetManagementController::class, 'destroyCategory'])->whereNumber('category')->name('budgets.manage.destroy_category');
    Route::get('/budgets/manage/category/{category}/transactions', [BudgetManagementController::class, 'transactions'])->whereNumber('category')->name('budgets.manage.transactions');
    Route::get('/budgets/manage/category/{category}/transactions/available', [BudgetManagementController::class, 'availableTransactions'])->whereNumber('category')->name('budgets.manage.transactions.available');
    Route::post('/budgets/manage/category/{category}/transactions/assign', [BudgetManagementController::class, 'assignTransactions'])->whereNumber('category')->name('budgets.manage.transactions.assign');
    Route::delete('/budgets/manage/category/{category}/transactions/{transaction}', [BudgetManagementController::class, 'unassignTransaction'])->whereNumber('category')->whereNumber('transaction')->name('budgets.manage.transactions.unassign');
    Route::post('/budgets/manage/category/{category}/duplicate', [BudgetManagementController::class, 'duplicateCategory'])->whereNumber('category')->name('budgets.manage.category.duplicate');

    Route::post('/cashflow/sources', [CashFlowSourceController::class, 'store'])->name('cashflow.sources.store');
    Route::put('/cashflow/sources/{cashFlowSource}', [CashFlowSourceController::class, 'update'])->whereNumber('cashFlowSource')->name('cashflow.sources.update');
    Route::delete('/cashflow/sources/{cashFlowSource}', [CashFlowSourceController::class, 'destroy'])->whereNumber('cashFlowSource')->name('cashflow.sources.destroy');
    Route::delete('/cashflow/sources/{cashFlowSource}/budget', [CashFlowSourceController::class, 'destroyBudget'])->whereNumber('cashFlowSource')->name('cashflow.sources.budget.destroy');
    Route::get('/cashflow/sources/{cashFlowSource}/transactions', [CashFlowSourceController::class, 'transactions'])->whereNumber('cashFlowSource')->name('cashflow.sources.transactions');
    Route::get('/cashflow/sources/{cashFlowSource}/transactions/available', [CashFlowSourceController::class, 'availableTransactions'])->whereNumber('cashFlowSource')->name('cashflow.sources.transactions.available');
    Route::post('/cashflow/sources/{cashFlowSource}/transactions/assign', [CashFlowSourceController::class, 'assignTransactions'])->whereNumber('cashFlowSource')->name('cashflow.sources.transactions.assign');
    Route::delete('/cashflow/sources/{cashFlowSource}/transactions/{transaction}', [CashFlowSourceController::class, 'unassignTransaction'])->whereNumber('cashFlowSource')->whereNumber('transaction')->name('cashflow.sources.transactions.unassign');
    Route::post('/cashflow/sources/{cashFlowSource}/duplicate', [CashFlowSourceController::class, 'duplicate'])->whereNumber('cashFlowSource')->name('cashflow.sources.duplicate');
});

// נתיב קצר להוספת תזרים מהדשבורד
Route::post('/transactions', [TransactionController::class, 'store'])->middleware(['auth', 'verified'])->name('transactions.store.dashboard');

// נתיבים לעריכת תקציב
Route::prefix('budgets')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/{budget}', [BudgetController::class, 'show'])->whereNumber('budget')->name('budgets.show');
    Route::post('/{budget}/update', [BudgetController::class, 'update'])->whereNumber('budget')->name('budgets.update');
});

// נתיבים לניהול תזרימים
Route::prefix('transactions')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/', [TransactionController::class, 'store'])->name('transactions.store');
    Route::post('/duplicate/bulk', [TransactionController::class, 'duplicateBulk'])->name('transactions.duplicate.bulk');
    Route::post('/delete/bulk', [TransactionController::class, 'deleteBulk'])->name('transactions.delete.bulk');
    Route::get('/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::put('/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::post('/{transaction}/duplicate', [TransactionController::class, 'duplicate'])->whereNumber('transaction')->name('transactions.duplicate');
    Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
