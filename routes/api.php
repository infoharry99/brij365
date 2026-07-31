<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ChatApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mobile App
|--------------------------------------------------------------------------
|
| These routes are for the Builder360 mobile app.
| Authentication is handled via Laravel Sanctum (Bearer tokens).
|
| Base URL: /api
|
*/

// =========================================================================
// Public routes — No authentication required
// =========================================================================
Route::prefix('auth')->name('api.auth.')->group(function (): void {
    Route::post('/login', [AuthApiController::class, 'login'])->name('login');
});

// =========================================================================
// Protected routes — Sanctum Bearer token required
// =========================================================================
Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {

    // Auth
    Route::prefix('auth')->name('api.auth.')->group(function (): void {
        Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthApiController::class, 'me'])->name('me');
    });

    // Chat Connect
    Route::prefix('chat')->name('api.chat.')->group(function (): void {

        // Users list (for starting new DMs)
        Route::get('/users', [ChatApiController::class, 'users'])->name('users.index');

        // Conversations
        Route::get('/conversations', [ChatApiController::class, 'conversations'])->name('conversations.index');
        Route::post('/conversations', [ChatApiController::class, 'storeConversation'])->name('conversations.store');
        Route::get('/conversations/{conversation}', [ChatApiController::class, 'showConversation'])->name('conversations.show');
        Route::patch('/conversations/{conversation}/read', [ChatApiController::class, 'markRead'])->name('conversations.read');

        // Messages
        // Messages & Reactions
        Route::get('/conversations/{conversation}/messages', [ChatApiController::class, 'messages'])->name('conversations.messages.index');
        Route::post('/conversations/{conversation}/messages', [ChatApiController::class, 'sendMessage'])->name('conversations.messages.store');
        Route::delete('/messages/{message}', [ChatApiController::class, 'deleteMessage'])->name('messages.destroy');
        Route::patch('/messages/{message}/reaction', [ChatApiController::class, 'reaction'])->name('messages.reaction');

        // Attachments
        Route::get('/attachments/{attachment}/download', [ChatApiController::class, 'downloadAttachment'])->name('attachments.download');
        Route::get('/attachments/{attachment}/preview', [ChatApiController::class, 'previewAttachment'])->name('attachments.preview');
        Route::get('/conversations/{conversation}/attachments/{attachment}/download', [ChatApiController::class, 'downloadAttachment'])->name('conversations.attachments.download');
    });
});
