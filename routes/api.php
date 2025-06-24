<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPhotoController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MessageController;

// 🔓 Routes publiques
Route::post('/social-login', [UserController::class, 'socialLogin']);
Route::post('/social-register', [UserController::class, 'socialRegister']);

// ✅ Route d’accès direct aux images (évite les 403)
Route::get('/user/photo/{filename}', function ($filename) {
    $path = 'images/' . $filename;

    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['error' => 'Image not found'], 404);
    }

    $file = Storage::disk('public')->get($path);
    $type = Storage::disk('public')->mimeType($path);

    return response($file, 200)->header('Content-Type', $type);
});

// 🔐 Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // 🔐 UserController
    Route::get('/me', fn(Request $request) => $request->user());

    Route::get('/user', [UserController::class, 'me']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/upload-photo', [UserController::class, 'uploadPhoto']);
    Route::post('/set-preferences', [UserController::class, 'setPreferences']);
    Route::post('/set-location', [UserController::class, 'setLocation']);
    Route::post('/report-user', [UserController::class, 'reportUser']);
    Route::post('/block-user', [UserController::class, 'blockUser']);
    Route::post('/unblock-user', [UserController::class, 'unblockUser']);
    Route::get('/blocked-users', [UserController::class, 'getBlockedUsers']);
    Route::post('/boost-profile', [UserController::class, 'boostProfile']);
    Route::post('/purchase-premium', [UserController::class, 'purchasePremium']);
    Route::post('/send-feedback', [UserController::class, 'sendFeedback']);

    // 🔄 Gestion des photos
    Route::get('/photos', [UserPhotoController::class, 'index']);
    Route::post('/photos/upload', [UserPhotoController::class, 'upload']);
    Route::post('/photos/set-main/{id}', [UserPhotoController::class, 'setMain']);
    Route::delete('/photos/{id}', [UserPhotoController::class, 'destroy']);

    // ❤️ Match system (MatchController)
    Route::get('/discover', [MatchController::class, 'getPotentialMatches']);
    Route::post('/matches', [MatchController::class, 'createMatch']);
    Route::post('/reject', [MatchController::class, 'rejectUser']);
    Route::get('/matches', [MatchController::class, 'getMatches']);
    Route::delete('/unmatch/{matchId}', [MatchController::class, 'unmatch']);

    // 💬 Gestion des messages
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
    Route::get('/messages/{userId}', [MessageController::class, 'getMessagesWithUser']);
    Route::get('/conversations', [MessageController::class, 'getConversations']);
});
