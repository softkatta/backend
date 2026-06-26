<?php

use App\Http\Controllers\Api\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Api\Admin\ContactMessageController;
use App\Http\Controllers\Api\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Api\Admin\HeroSlideController;
use App\Http\Controllers\Api\Admin\IntegrationController;
use App\Http\Controllers\Api\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Admin\ProductCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Api\Admin\TenantController;
use App\Http\Controllers\Api\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Client\DashboardController;
use App\Http\Controllers\Api\Client\InvoiceController as ClientInvoiceController;
use App\Http\Controllers\Api\Client\NotificationController as ClientNotificationController;
use App\Http\Controllers\Api\Client\ProductController as ClientProductController;
use App\Http\Controllers\Api\Client\PaymentController as ClientPaymentController;
use App\Http\Controllers\Api\Client\ProfileController;
use App\Http\Controllers\Api\Client\PurchaseController as ClientPurchaseController;
use App\Http\Controllers\Api\Client\SubscriptionController as ClientSubscriptionController;
use App\Http\Controllers\Api\Client\SupportController;
use App\Http\Controllers\Api\Public\AuthController;
use App\Http\Controllers\Api\Public\AuthSecurityController;
use App\Http\Controllers\Api\Public\TwoFactorMethodController;
use App\Http\Controllers\Api\Public\BlogController;
use App\Http\Controllers\Api\Public\ContactController;
use App\Http\Controllers\Api\Public\PricingController;
use App\Http\Controllers\Api\Public\ProductController;
use App\Http\Controllers\Api\Public\ServiceController;
use App\Http\Controllers\Api\InboxNotificationController;
use App\Http\Controllers\Api\Public\SiteAboutController;
use App\Http\Controllers\Api\Public\SiteBroadcastingController;
use App\Http\Controllers\Api\Public\SiteBrandingController;
use App\Http\Controllers\Api\Public\SiteContentController;
use App\Http\Controllers\Api\Public\SiteMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // Public routes (always available)
    Route::get('site/maintenance', [SiteMaintenanceController::class, 'show']);
    Route::get('site/branding', [SiteBrandingController::class, 'show']);
    Route::get('site/about', [SiteAboutController::class, 'show']);
    Route::get('site/broadcasting', [SiteBroadcastingController::class, 'show']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/login/identify', [AuthController::class, 'identifyLogin']);
    Route::post('auth/login/passkey/options', [TwoFactorMethodController::class, 'passkeyPrimaryLoginOptions']);
    Route::post('auth/login/passkey/verify', [TwoFactorMethodController::class, 'passkeyPrimaryLoginVerify']);

    Route::middleware('maintenance')->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register']);
        });

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{slug}', [ProductController::class, 'show']);
        Route::post('purchase', [ProductController::class, 'purchase']);

        Route::get('services', [ServiceController::class, 'index']);
        Route::get('services/{slug}', [ServiceController::class, 'show']);

        Route::get('blogs', [BlogController::class, 'index']);
        Route::get('blogs/{slug}', [BlogController::class, 'show']);

        Route::post('contact', [ContactController::class, 'store']);

        Route::get('pricing', [PricingController::class, 'index']);

        Route::get('hero-slides', [SiteContentController::class, 'heroSlides']);
        Route::get('testimonials', [SiteContentController::class, 'testimonials']);
        Route::get('faqs', [SiteContentController::class, 'faqs']);
    });

    // Authenticated user (any role)
    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy', 'maintenance', 'role:client', 'tenant'])->prefix('client')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('products', [ClientProductController::class, 'index']);
        Route::get('products/{slug}', [ClientProductController::class, 'show']);

        Route::get('subscriptions', [ClientSubscriptionController::class, 'index']);
        Route::get('subscriptions/{subscription}', [ClientSubscriptionController::class, 'show']);
        Route::post('subscriptions/{subscription}/cancel', [ClientSubscriptionController::class, 'cancel']);

        Route::get('invoices', [ClientInvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [ClientInvoiceController::class, 'show']);
        Route::get('invoices/{invoice}/download', [ClientInvoiceController::class, 'download']);

        Route::get('notifications', [ClientNotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [ClientNotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [ClientNotificationController::class, 'markAllAsRead']);

        Route::get('support', [SupportController::class, 'index']);
        Route::post('support', [SupportController::class, 'store']);
        Route::get('support/{ticket}', [SupportController::class, 'show']);
        Route::post('support/{ticket}/replies', [SupportController::class, 'reply']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::patch('profile', [ProfileController::class, 'update']);

        Route::post('purchase', [ClientPurchaseController::class, 'store']);
        Route::post('payments/verify', [ClientPaymentController::class, 'verify']);
    });

    Route::middleware(['auth:sanctum', 'security.policy'])->group(function (): void {
        Route::get('inbox/notifications', [InboxNotificationController::class, 'index']);
        Route::post('inbox/notifications/{notification}/read', [InboxNotificationController::class, 'markAsRead']);
        Route::post('inbox/notifications/read-all', [InboxNotificationController::class, 'markAllAsRead']);
    });

    // Authenticated user (any role)
    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/profile/avatar', [AuthController::class, 'uploadAvatar']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('auth/security', [AuthSecurityController::class, 'show']);
        Route::put('auth/security/preferences', [AuthSecurityController::class, 'updatePreferences']);
        Route::post('auth/security/setup/skip', [AuthSecurityController::class, 'skipSecuritySetup']);
        Route::post('auth/security/2fa/setup', [AuthSecurityController::class, 'setupTwoFactor']);
        Route::post('auth/security/2fa/confirm', [AuthSecurityController::class, 'confirmTwoFactor']);
        Route::post('auth/security/2fa/disable', [AuthSecurityController::class, 'disableTwoFactor']);
        Route::post('auth/security/2fa/email/send', [TwoFactorMethodController::class, 'sendEmailEnableOtp']);
        Route::post('auth/security/2fa/email/confirm', [TwoFactorMethodController::class, 'confirmEmailEnable']);
        Route::post('auth/security/2fa/email/disable/send', [TwoFactorMethodController::class, 'sendEmailDisableOtp']);
        Route::post('auth/security/2fa/email/disable', [TwoFactorMethodController::class, 'disableEmail']);
        Route::post('auth/security/webauthn/register/options', [TwoFactorMethodController::class, 'passkeyRegisterOptions']);
        Route::post('auth/security/webauthn/register/verify', [TwoFactorMethodController::class, 'passkeyRegisterVerify']);
        Route::post('auth/security/webauthn/disable', [TwoFactorMethodController::class, 'disableAllPasskeys']);
        Route::delete('auth/security/webauthn/credentials/{credential}', [TwoFactorMethodController::class, 'deletePasskey']);
        Route::get('auth/security/sessions', [AuthSecurityController::class, 'sessions']);
        Route::post('auth/security/sessions/revoke', [AuthSecurityController::class, 'revokeSessions']);
    });

    Route::post('auth/2fa/verify', [AuthSecurityController::class, 'verifyLogin']);
    Route::post('auth/2fa/email/send', [TwoFactorMethodController::class, 'sendLoginEmailOtp']);
    Route::post('auth/2fa/webauthn/options', [TwoFactorMethodController::class, 'passkeyLoginOptions']);
    Route::post('auth/2fa/webauthn/verify', [TwoFactorMethodController::class, 'passkeyLoginVerify']);

    // Admin routes
    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy', 'role:super_admin'])->prefix('admin')->group(function (): void {
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/subscriptions', [ReportController::class, 'subscriptions']);
        Route::get('reports/products', [ReportController::class, 'products']);
        Route::get('reports/export', [ReportController::class, 'export']);

        Route::get('notifications', [AdminNotificationController::class, 'index']);
        Route::post('notifications', [AdminNotificationController::class, 'store']);
        Route::delete('notifications/{notification}', [AdminNotificationController::class, 'destroy']);

        Route::apiResource('tenants', TenantController::class);
        Route::apiResource('users', AdminUserController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('products', AdminProductController::class);
        Route::apiResource('plans', AdminPlanController::class);
        Route::apiResource('services', AdminServiceController::class);

        Route::post('subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel']);
        Route::apiResource('subscriptions', AdminSubscriptionController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'destroy']);
        Route::apiResource('invoices', AdminInvoiceController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::get('invoices/{invoice}/download', [AdminInvoiceController::class, 'download']);
        Route::apiResource('payments', AdminPaymentController::class)->only(['index', 'show', 'destroy']);

        Route::apiResource('support-tickets', AdminSupportTicketController::class)->only(['index', 'show', 'update']);
        Route::post('support-tickets/{support_ticket}/replies', [AdminSupportTicketController::class, 'reply']);
        Route::apiResource('blogs', AdminBlogController::class);
        Route::apiResource('testimonials', AdminTestimonialController::class)->except(['show']);
        Route::apiResource('hero-slides', HeroSlideController::class)->except(['show']);
        Route::post('uploads', [UploadController::class, 'store']);
        Route::apiResource('faqs', AdminFaqController::class)->except(['show']);
        Route::apiResource('contact-messages', ContactMessageController::class)->except(['store']);

        Route::apiResource('settings', SettingController::class)->except(['show']);
        Route::post('settings/bulk', [SettingController::class, 'bulkUpdate']);
        Route::post('integrations/{integration}/test-email', [IntegrationController::class, 'sendTestEmail']);
        Route::apiResource('integrations', IntegrationController::class)->except(['show']);
    });
});
