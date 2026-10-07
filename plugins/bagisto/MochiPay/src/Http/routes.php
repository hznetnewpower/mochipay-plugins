<?php
use Illuminate\Support\Facades\Route;
use MochiPay\Bagisto\Http\Controller;
Route::middleware('web')->group(function(){
    Route::get('/mochipay/redirect',[Controller::class,'redirect'])->name('mochipay.redirect');
    // This capability-scoped endpoint uses its own per-attempt POST nonce.
    Route::match(['GET','POST'],'/mochipay/checkout',[Controller::class,'checkout'])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class,\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('mochipay.checkout');
});
