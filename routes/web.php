<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentController;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/search', [StorefrontController::class, 'search'])->name('search');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('products.show');

// Cart routes (accessible by both guests and authenticated users)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.addItem');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem'])->name('cart.updateItem');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem'])->name('cart.removeItem');

// Address API (for form options)
Route::get('/api/provinces', [\App\Http\Controllers\AddressController::class, 'getProvinces'])->name('api.provinces');
Route::get('/api/cities/{province_id}', [\App\Http\Controllers\AddressController::class, 'getCities'])->name('api.cities');
Route::get('/api/districts/{city_id}', [\App\Http\Controllers\AddressController::class, 'getDistricts'])->name('api.districts');

// Checkout API
Route::post('/checkout/shipping-cost', [\App\Http\Controllers\CheckoutController::class, 'calculateShipping'])->name('checkout.shippingCost');

// Redirect legacy dashboard route to home since we unified login redirect
Route::get('/dashboard', function () {
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/checkout', [\App\Http\Controllers\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/process', [\App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success/{order_number}', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Payment routes
    Route::post('/payments/{order_number}/pay', [PaymentController::class, 'createPayment'])->name('payments.pay');
    Route::get('/payments/{order_number}/status', [PaymentController::class, 'checkStatus'])->name('payments.status');
    Route::post('/orders/{order_number}/cancel', [\App\Http\Controllers\CheckoutController::class, 'cancel'])->name('orders.cancel');

    // Customer Orders
    Route::get('/orders', [\App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order_number}', [\App\Http\Controllers\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order_number}/confirm', [\App\Http\Controllers\OrderController::class, 'confirm'])->name('orders.confirm');

    // Reviews
    Route::post('/orders/{order_number}/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/reviews/{review}/update', [\App\Http\Controllers\ReviewController::class, 'update'])->name('reviews.update');

    // Returns
    Route::post('/orders/{order_number}/return', [\App\Http\Controllers\ReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{return_number}', [\App\Http\Controllers\ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{return_number}/tracking', [\App\Http\Controllers\ReturnController::class, 'submitTracking'])->name('returns.tracking');

    // Address management
    Route::resource('addresses', \App\Http\Controllers\AddressController::class)->only(['store', 'update', 'destroy']);
    Route::patch('/addresses/{address}/set-default', [\App\Http\Controllers\AddressController::class, 'setDefault'])->name('addresses.setDefault');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');

    Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class);
    Route::resource('products', \App\Http\Controllers\Admin\ProductController::class);
    
    // Custom routes for variants
    Route::post('products/{product}/variants', [\App\Http\Controllers\Admin\ProductVariantController::class, 'store'])->name('products.variants.store');
    Route::put('variants/{variant}', [\App\Http\Controllers\Admin\ProductVariantController::class, 'update'])->name('products.variants.update');
    Route::delete('variants/{variant}', [\App\Http\Controllers\Admin\ProductVariantController::class, 'destroy'])->name('products.variants.destroy');

    // Custom routes for images
    Route::post('products/{product}/images', [\App\Http\Controllers\Admin\ProductImageController::class, 'store'])->name('products.images.store');
    Route::put('images/{image}', [\App\Http\Controllers\Admin\ProductImageController::class, 'update'])->name('products.images.update');
    Route::delete('images/{image}', [\App\Http\Controllers\Admin\ProductImageController::class, 'destroy'])->name('products.images.destroy');

    // Admin Profile
    Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');

    // Admin Orders
    Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/process', [\App\Http\Controllers\Admin\OrderController::class, 'process'])->name('orders.process');
    Route::post('orders/{order}/ship', [\App\Http\Controllers\Admin\OrderController::class, 'ship'])->name('orders.ship');
    Route::get('orders/{order}/print', [\App\Http\Controllers\Admin\OrderController::class, 'print'])->name('orders.print');

    // Admin Reviews
    Route::get('reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/bulk-moderate', [\App\Http\Controllers\Admin\ReviewController::class, 'bulkModerate'])->name('reviews.bulk');
    Route::patch('reviews/{review}/toggle', [\App\Http\Controllers\Admin\ReviewController::class, 'togglePublish'])->name('reviews.toggle');
    Route::post('reviews/{review}/reply', [\App\Http\Controllers\Admin\ReviewController::class, 'reply'])->name('reviews.reply');

    // Admin Returns
    Route::get('returns', [\App\Http\Controllers\Admin\ReturnController::class, 'index'])->name('returns.index');
    Route::get('returns/{returnRequest}', [\App\Http\Controllers\Admin\ReturnController::class, 'show'])->name('returns.show');
    Route::post('returns/{returnRequest}/approve', [\App\Http\Controllers\Admin\ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('returns/{returnRequest}/reject', [\App\Http\Controllers\Admin\ReturnController::class, 'reject'])->name('returns.reject');
    Route::post('returns/{returnRequest}/receive', [\App\Http\Controllers\Admin\ReturnController::class, 'receive'])->name('returns.receive');
    Route::post('returns/{returnRequest}/inspect', [\App\Http\Controllers\Admin\ReturnController::class, 'inspect'])->name('returns.inspect');
    Route::post('returns/{returnRequest}/refund', [\App\Http\Controllers\Admin\ReturnController::class, 'processRefund'])->name('returns.refund');
    Route::post('returns/{returnRequest}/complete', [\App\Http\Controllers\Admin\ReturnController::class, 'complete'])->name('returns.complete');
});

// Payment API (public — no auth required)
Route::get('/api/payment-channels', [PaymentController::class, 'getChannels'])->name('api.paymentChannels');
Route::post('/api/payments/webhook', [PaymentController::class, 'webhook'])->name('payments.webhook');

require __DIR__.'/auth.php';
