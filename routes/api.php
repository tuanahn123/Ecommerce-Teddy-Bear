<?php

use App\Http\Controllers\AttributeTypeController;
use App\Http\Controllers\AttributeValueController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;

//TODO Routes công khai
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::prefix('attribute-types')->group(function () {
    Route::get('/', [AttributeTypeController::class, 'index']);
    Route::get('/{id}', [AttributeTypeController::class, 'show']);
});
Route::get('/attribute-values', [AttributeValueController::class, 'index']);
Route::get('attribute-values/{id}', [AttributeValueController::class, 'show']);
Route::get('/vnpay-return', [InvoiceController::class, 'vnpayReturn']);
Route::get('/products/{product_id}/reviews', [ReviewController::class, 'getReviewsByProduct']);
Route::get('/products/category/{id}', [ProductController::class, 'getProductsByCategoryId']);
Route::get('/products/category/slug/{slug}', [ProductController::class, 'getProductsByCategorySlug']); //TODO Routes cần xác thực
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    //TODO Routes cho customer
    Route::post('/auth/change-password', [UserController::class, 'changePassword']);
    Route::post('/user/profile', [UserController::class, 'updateProfile']);
    //TODO Quản lý giỏ hàng
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'addToCart']);
    Route::put('/cart/{cartItem}', [CartController::class, 'updateCart']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'removeFromCart']);
    Route::delete('/cart', [CartController::class, 'clearCart']);
    // TODO Đơn hàng
    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    Route::get('/orders', [OrderController::class, 'getUserOrders']);
    // TODO Hóa đơn
    Route::post('/invoices/{orderId}', [InvoiceController::class, 'createInvoice']);
    Route::get('/invoices/pay/{invoiceId}', [InvoiceController::class, 'payWithVnpay']);
    Route::get('/invoices/payment-status', [InvoiceController::class, 'getPaymentStatus']);
    // TODO Bình luận
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);


    //TODO Routes cho admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'getStatistics']);
        //TODO  Quản lý người dùng
        Route::get('/users', [UserController::class, 'getListUser']);
        //TODO  Attribute Type
        Route::prefix('attribute-types')->group(function () {
            // Route::get('/', [AttributeTypeController::class, 'index']);
            Route::post('/', [AttributeTypeController::class, 'store']);
            // Route::get('/{id}', [AttributeTypeController::class, 'show']);
            Route::put('/{id}', [AttributeTypeController::class, 'update']);
            Route::delete('/{id}', [AttributeTypeController::class, 'destroy']);
        });
        // In the admin middleware group, add these routes:
        // Existing admin routes

        // Admin review management
        Route::get('/reviews', [ReviewController::class, 'getAllReviews']);
        Route::patch('/reviews/{id}/status', [ReviewController::class, 'updateReviewStatus']);
        Route::post('/reviews/{review_id}/reply', [ReviewController::class, 'replyToReview']);
        Route::put('/reviews/replies/{reply_id}', [ReviewController::class, 'updateReply']);
        Route::delete('/reviews/replies/{reply_id}', [ReviewController::class, 'deleteReply']);

        //TODO Attribute Values
        Route::prefix('attribute-values')->group(function () {
            // Route::get('/', [AttributeValueController::class, 'index']);
            Route::post('/', [AttributeValueController::class, 'store']);
            // Route::get('/{id}', [AttributeValueController::class, 'show']);
            Route::put('/{id}', [AttributeValueController::class, 'update']);
            Route::delete('/{id}', [AttributeValueController::class, 'destroy']);
            Route::get('/by-type/{attributeTypeId}', [AttributeValueController::class, 'getByAttributeType']);
        });
        //TODO Danh mục
        // Route::apiResource('categories', CategoryController::class);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        //TODO Sản phẩm
        Route::post('/product', [ProductController::class, 'store']);
        Route::post('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        // TODO Quản lý  đơn hàng
        Route::get('/orders', [OrderController::class, 'getAllOrders']);
        // In routes/api.php (or wherever your existing routes are defined)
        // Inside the admin middleware group

        // Admin Order Management
        Route::get('/orders/search', [OrderController::class, 'searchOrders']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'deleteOrder']);
        Route::put('/orders/{orderId}', [OrderController::class, 'updateOrder']);
        Route::patch('/orders/{orderId}/status', [OrderController::class, 'updateOrderStatus']);
        Route::get('/orders/{orderId}', [OrderController::class, 'getOrderDetail']);
    });
});
