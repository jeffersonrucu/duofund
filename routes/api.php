<?php

use App\Http\Controllers\Api\DuofundMcpController;
use App\Http\Middleware\DuofundMcp;
use Illuminate\Support\Facades\Route;

Route::middleware([DuofundMcp::class, 'throttle:60,1'])->prefix('mcp')->group(function () {
    // Summary
    Route::get('/summary', [DuofundMcpController::class, 'summary']);

    // Categories
    Route::get('/categories', [DuofundMcpController::class, 'listCategories']);
    Route::post('/categories', [DuofundMcpController::class, 'createCategory']);
    Route::delete('/categories/{id}', [DuofundMcpController::class, 'deleteCategory']);

    // Goals
    Route::get('/goals', [DuofundMcpController::class, 'listGoals']);
    Route::post('/goals', [DuofundMcpController::class, 'createGoal']);
    Route::post('/goals/{id}/deposit', [DuofundMcpController::class, 'depositGoal']);
    Route::delete('/goals/{id}', [DuofundMcpController::class, 'deleteGoal']);

    // Transactions
    Route::get('/transactions', [DuofundMcpController::class, 'listTransactions']);
    Route::post('/transactions', [DuofundMcpController::class, 'createTransaction']);
    Route::delete('/transactions/{id}', [DuofundMcpController::class, 'deleteTransaction']);

    // Wishlist
    Route::get('/wishlist', [DuofundMcpController::class, 'listWishlist']);
    Route::post('/wishlist', [DuofundMcpController::class, 'createWishlistItem']);
    Route::delete('/wishlist/{id}', [DuofundMcpController::class, 'deleteWishlistItem']);
});
