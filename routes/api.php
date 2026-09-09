<?php

use App\Http\Controllers\NotificationDeliveryController;
use Illuminate\Support\Facades\Route;

Route::post('/notification-deliveries/quiz-receipt', [
    NotificationDeliveryController::class,
    'dispatchQuizReceipt',
])->middleware('throttle:600,1');
