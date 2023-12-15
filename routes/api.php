<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GiftController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ResourceUserController;
use App\Http\Controllers\Api\V1\BStatsController;
use App\Http\Controllers\Api\V1\DiscordAuthController;
use App\Http\Controllers\Api\V1\DiscordController;
use App\Http\Controllers\Api\V1\PreviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('/v1')->name('v1.')->group(function () {
    Route::middleware(['auth:sanctum', 'abilities:' . env('ABILITY_RESOURCE')])->get('/user', function (Request $request) {
        return $request->user();
    });

    Route::middleware('auth:sanctum')->post('auth/test', function () {
        return json_encode(['status' => true]);
    });

    // Allows you to create a token
    Route::post('/auth/login', [AuthController::class, "login"]);
    Route::get('/discord/authentication', [DiscordAuthController::class, 'authentication'])->name('discord');
    Route::get('/discord/{server_id}', [DiscordController::class, 'getDiscordInformation'])->name('discord.information');
    Route::post('/preview', [PreviewController::class, 'preview'])->name('preview');

    Route::prefix('/resources')->name('resources.')->group(function () {
        Route::get('users', [ResourceUserController::class, 'find'])->name('user');
    });

    Route::prefix('/bstats')->name('bstats.')->group(function () {
        Route::get('/url/{id}', [BStatsController::class, 'getUrl'])->name('url');
        Route::get('/{id}/{chart}', [BStatsController::class, 'getStats'])->name('stats');
    });

    Route::post('{payment}/notification/{id?}', [PaymentController::class, 'notification'])->name('notification');
    Route::get('gift/verify/{code}/{resource}/{user}', [GiftController::class, 'verify'])->name('gift');

});
