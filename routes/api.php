<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('google-login', [AuthController::class, 'googleLogin']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

//Profile Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/profile', [UserController::class, 'profile']);
    Route::post('user/profile', [UserController::class, 'updateProfile']);
    Route::put('user/profile', [UserController::class, 'updateProfile']);
    Route::post('user/avatar', [UserController::class, 'updateAvatar']);
    Route::post('user/change-password', [UserController::class, 'changePassword']);
    
    // Picker settings
    Route::get('picker/settings', [UserController::class, 'getPickerSettings']);
    Route::put('picker/settings', [UserController::class, 'updatePickerSettings']);
    
    // Orderer settings
    Route::get('orderer/settings', [UserController::class, 'getOrdererSettings']);
    Route::put('orderer/settings', [UserController::class, 'updateOrdererSettings']);
    
    //endpoints (only kept for backward compatibility)
    Route::get('user/settings', [UserController::class, 'getSettings']);
    Route::put('user/settings', [UserController::class, 'updateSettings']);
    
    Route::post('user/languages', [UserController::class, 'addLanguage']);
    Route::put('user/languages', [UserController::class, 'updateLanguages']);
    Route::delete('user/languages/{languageId}', [UserController::class, 'removeLanguage']);
    Route::get('users/{userId}', [UserController::class, 'getPublicProfile']);
    Route::get('users/{userId}/picker-profile', [UserController::class, 'getPickerProfile']);
    Route::get('users/{userId}/orderer-profile', [UserController::class, 'getOrdererProfile']);
});
//Travel Journey Apis for Pickers
Route::middleware('auth:sanctum')->group(function () {
    Route::get('travel-journeys', [\App\Http\Controllers\Api\TravelJourneyController::class, 'index']);
    Route::post('travel-journeys', [\App\Http\Controllers\Api\TravelJourneyController::class, 'store']);
    Route::put('travel-journeys/{id}', [\App\Http\Controllers\Api\TravelJourneyController::class, 'update']);
});

// Order Management & Discovery
Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders/available', [\App\Http\Controllers\Api\OrderDiscoveryController::class, 'getAvailableOrders']);
    Route::get('orders/search', [\App\Http\Controllers\Api\OrderDiscoveryController::class, 'searchOrders']);
    Route::get('orders/picker/history', [\App\Http\Controllers\Api\OrderController::class, 'getPickerOrders']);
    Route::post('orders', [\App\Http\Controllers\Api\OrderController::class, 'store']);
    Route::get('orders', [\App\Http\Controllers\Api\OrderController::class, 'index']);
    Route::get('orders/{order}', [\App\Http\Controllers\Api\OrderController::class, 'show']);
    Route::put('orders/{order}', [\App\Http\Controllers\Api\OrderController::class, 'update']);
    Route::post('orders/{order}/items', [\App\Http\Controllers\Api\OrderController::class, 'storeItems']);
    Route::delete('orders/{order}/items', [\App\Http\Controllers\Api\OrderController::class, 'deleteItems']);
    Route::put('orders/{order}/reward', [\App\Http\Controllers\Api\OrderController::class, 'setReward']);
    Route::put('orders/{order}/finalize', [\App\Http\Controllers\Api\OrderController::class, 'finalize']);
    Route::put('orders/{order}/accept', [\App\Http\Controllers\Api\OrderController::class, 'acceptDelivery']);
    Route::delete('orders/{order}', [\App\Http\Controllers\Api\OrderController::class, 'destroy']);
});

// Picker Discovery (for Orderers)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('pickers/search', [\App\Http\Controllers\Api\PickerDiscoveryController::class, 'searchPickers']);
    Route::get('orders/{orderId}/pickers', [\App\Http\Controllers\Api\PickerDiscoveryController::class, 'getAvailablePickers']);
    Route::get('pickers/{pickerId}', [\App\Http\Controllers\Api\PickerDiscoveryController::class, 'getPickerDetails']);
});

//Countwe Offer
Route::middleware('auth:sanctum')->group(function () {
    Route::post('offers', [\App\Http\Controllers\Api\OfferController::class, 'store']);
    Route::put('offers/{offerId}/accept', [\App\Http\Controllers\Api\OfferController::class, 'accept']);
    Route::put('offers/{offerId}/reject', [\App\Http\Controllers\Api\OfferController::class, 'reject']);
    Route::get('orders/{orderId}/offers', [\App\Http\Controllers\Api\OfferController::class, 'getHistory']);
});

// Chat
Route::middleware('auth:sanctum')->group(function () {
    Route::post('chat-rooms/get-or-create', [\App\Http\Controllers\Api\ChatController::class, 'getOrCreateChatRoom']);
    Route::get('chat-rooms', [\App\Http\Controllers\Api\ChatController::class, 'getRooms']);
    Route::get('chat-rooms/{roomId}', [\App\Http\Controllers\Api\ChatController::class, 'getChatRoom']);
    Route::get('chat-rooms/{roomId}/messages', [\App\Http\Controllers\Api\ChatController::class, 'getMessages']);
    Route::post('chat-rooms/{roomId}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
    Route::put('chat-messages/{messageId}/read', [\App\Http\Controllers\Api\ChatController::class, 'markAsRead']);
});

// Delivery
Route::middleware('auth:sanctum')->group(function () {
    Route::put('orders/{order}/mark-delivered', [\App\Http\Controllers\Api\DeliveryController::class, 'markDelivered']);
    Route::put('orders/{order}/confirm-delivery', [\App\Http\Controllers\Api\DeliveryController::class, 'confirmDelivery']);
    Route::put('orders/{order}/report-issue', [\App\Http\Controllers\Api\DeliveryController::class, 'reportIssue']);
    Route::get('orders/{order}/delivery-status', [\App\Http\Controllers\Api\DeliveryController::class, 'getStatus']);
});

// Reviews
Route::middleware('auth:sanctum')->group(function () {
    Route::post('reviews', [\App\Http\Controllers\Api\ReviewController::class, 'store']);
    Route::get('orders/{order}/review', [\App\Http\Controllers\Api\ReviewController::class, 'getForOrder']);
    Route::get('users/{user}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'getUserReviews']);
    Route::get('users/{user}/rating', [\App\Http\Controllers\Api\ReviewController::class, 'getUserRating']);
});

// Tips
Route::middleware('auth:sanctum')->group(function () {
    Route::post('tips', [\App\Http\Controllers\Api\TipController::class, 'store']);
    Route::get('orders/{order}/tips', [\App\Http\Controllers\Api\TipController::class, 'getOrderTips']);
    Route::get('users/{userId}/tips-received', [\App\Http\Controllers\Api\TipController::class, 'getUserTipsReceived']);
});

// Payment Methods
Route::middleware('auth:sanctum')->group(function () {
    Route::post('payment-methods', [\App\Http\Controllers\Api\PaymentMethodController::class, 'store']);
    Route::get('payment-methods', [\App\Http\Controllers\Api\PaymentMethodController::class, 'index']);
    Route::get('payment-methods/{method}', [\App\Http\Controllers\Api\PaymentMethodController::class, 'show']);
    Route::put('payment-methods/{method}', [\App\Http\Controllers\Api\PaymentMethodController::class, 'update']);
    Route::delete('payment-methods/{method}', [\App\Http\Controllers\Api\PaymentMethodController::class, 'destroy']);
    Route::put('payment-methods/{method}/set-default', [\App\Http\Controllers\Api\PaymentMethodController::class, 'setDefault']);
});

// Payout Methods
Route::middleware('auth:sanctum')->group(function () {
    Route::post('payout-methods', [\App\Http\Controllers\Api\PayoutMethodController::class, 'store']);
    Route::get('payout-methods', [\App\Http\Controllers\Api\PayoutMethodController::class, 'index']);
    Route::get('payout-methods/{id}', [\App\Http\Controllers\Api\PayoutMethodController::class, 'show']);
    Route::put('payout-methods/{id}', [\App\Http\Controllers\Api\PayoutMethodController::class, 'update']);
    Route::delete('payout-methods/{id}', [\App\Http\Controllers\Api\PayoutMethodController::class, 'destroy']);
    Route::put('payout-methods/{id}/set-default', [\App\Http\Controllers\Api\PayoutMethodController::class, 'setDefault']);
});

// Search
Route::middleware('auth:sanctum')->group(function () {
    Route::get('search/users', [\App\Http\Controllers\Api\SearchController::class, 'searchUsers']);
    Route::get('search/orders', [\App\Http\Controllers\Api\SearchController::class, 'searchOrders']);
    Route::get('search/pickers', [\App\Http\Controllers\Api\SearchController::class, 'searchPickers']);
});

// Notifications
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::put('notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::delete('notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
});

// Dashboard
Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/picker', [\App\Http\Controllers\Api\OrderDiscoveryController::class, 'getPickerDashboard']);
    Route::get('dashboard/orderer', [\App\Http\Controllers\Api\OrderDiscoveryController::class, 'getOrdererDashboard']);
});

// Locations (Countries & Cities)
Route::get('locations/countries', [\App\Http\Controllers\Api\LocationController::class, 'getCountries']);
Route::post('locations/cities', [\App\Http\Controllers\Api\LocationController::class, 'getCities']);
