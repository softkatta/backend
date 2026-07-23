<?php

use App\Http\Controllers\Api\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Api\Admin\ChatbotAnalyticsController;
use App\Http\Controllers\Api\Admin\ChatbotCategoryController;
use App\Http\Controllers\Api\Admin\ChatbotConversationController;
use App\Http\Controllers\Api\Admin\ChatbotDashboardController;
use App\Http\Controllers\Api\Admin\ChatbotFaqController;
use App\Http\Controllers\Api\Admin\ChatbotLeadController;
use App\Http\Controllers\Api\Admin\ChatbotSettingsController;
use App\Http\Controllers\Api\Admin\HrManagerController;
use App\Http\Controllers\Api\Admin\AccessRoleController;
use App\Http\Controllers\Api\Admin\CompanyRoleController;
use App\Http\Controllers\Api\Admin\PortalMenuController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\CareerController as AdminCareerController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\ContactMessageController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
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
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\JobApplicationController;
use App\Http\Controllers\Api\Admin\LeaveRequestController;
use App\Http\Controllers\Api\Admin\AttendanceRecordController;
use App\Http\Controllers\Api\Admin\LicenseController as AdminLicenseController;
use App\Http\Controllers\Api\Admin\ProductIntegrationController as AdminProductIntegrationController;
use App\Http\Controllers\Api\Central\LicenseController as CentralLicenseController;
use App\Http\Controllers\Api\Central\ProductController as CentralProductController;
use App\Http\Controllers\Api\Central\ProductIntegrationController as CentralProductIntegrationController;
use App\Http\Controllers\Api\Company\LicenseController as CompanyLicenseController;
use App\Http\Controllers\Api\Client\LicenseController as ClientLicenseController;
use App\Http\Controllers\Api\Webhook\PaymentWebhookController;
use App\Http\Controllers\Api\Client\CouponController as ClientCouponController;
use App\Http\Controllers\Api\Client\DashboardController;
use App\Http\Controllers\Api\Client\InvoiceController as ClientInvoiceController;
use App\Http\Controllers\Api\Client\NotificationController as ClientNotificationController;
use App\Http\Controllers\Api\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Api\Client\ProductController as ClientProductController;
use App\Http\Controllers\Api\Client\PaymentController as ClientPaymentController;
use App\Http\Controllers\Api\Client\ProfileController;
use App\Http\Controllers\Api\Client\PurchaseController as ClientPurchaseController;
use App\Http\Controllers\Api\Client\SubscriptionController as ClientSubscriptionController;
use App\Http\Controllers\Api\Client\SupportController;
use App\Http\Controllers\Api\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Api\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Api\Employee\DocumentController as EmployeeDocumentController;
use App\Http\Controllers\Api\Employee\LeaveController as EmployeeLeaveController;
use App\Http\Controllers\Api\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Api\Employee\ResignationController as EmployeeResignationController;
use App\Http\Controllers\Api\Employee\TaskController as EmployeeTaskController;
use App\Http\Controllers\Api\Employee\ProjectController as EmployeeProjectController;
use App\Http\Controllers\Api\Employee\TimesheetController as EmployeeTimesheetController;
use App\Http\Controllers\Api\Employee\CalendarEventController as EmployeeCalendarEventController;
use App\Http\Controllers\Api\Employee\AnnouncementController as EmployeeAnnouncementController;
use App\Http\Controllers\Api\Employee\AssetController as EmployeeAssetController;
use App\Http\Controllers\Api\Employee\TrainingController as EmployeeTrainingController;
use App\Http\Controllers\Api\Employee\PerformanceReviewController as EmployeePerformanceReviewController;
use App\Http\Controllers\Api\Employee\HelpdeskTicketController as EmployeeHelpdeskTicketController;
use App\Http\Controllers\Api\Admin\CompanyAssetController as AdminCompanyAssetController;
use App\Http\Controllers\Api\Admin\TrainingController as AdminTrainingController;
use App\Http\Controllers\Api\Admin\PerformanceReviewController as AdminPerformanceReviewController;
use App\Http\Controllers\Api\Admin\HelpdeskTicketController as AdminHelpdeskTicketController;
use App\Http\Controllers\Api\Public\AuthController;
use App\Http\Controllers\Api\Public\AuthSecurityController;
use App\Http\Controllers\Api\Public\TwoFactorMethodController;
use App\Http\Controllers\Api\Public\BlogController;
use App\Http\Controllers\Api\Public\CareerController;
use App\Http\Controllers\Api\Public\ChatbotController;
use App\Http\Controllers\Api\Public\ContactController;
use App\Http\Controllers\Api\Public\PricingController;
use App\Http\Controllers\Api\Public\ProductController;
use App\Http\Controllers\Api\Public\ReviewController;
use App\Http\Controllers\Api\Public\ServiceController;
use App\Http\Controllers\Api\HrDocumentController;
use App\Http\Controllers\Api\InboxNotificationController;
use App\Http\Controllers\Api\Public\SiteAboutController;
use App\Http\Controllers\Api\Public\SiteBroadcastingController;
use App\Http\Controllers\Api\Public\SiteBrandingController;
use App\Http\Controllers\Api\Public\SiteContentController;
use App\Http\Controllers\Api\Public\SiteController;
use App\Http\Controllers\Api\Public\SiteHomeSectionsController;
use App\Http\Controllers\Api\Public\SiteMaintenanceController;
use App\Http\Controllers\Api\Public\SiteOffersController;
use App\Http\Controllers\Api\Public\SitePageContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // Public routes (always available)
    Route::get('site/maintenance', [SiteMaintenanceController::class, 'show']);
    Route::get('site/branding', [SiteBrandingController::class, 'show']);
    Route::get('site/about', [SiteAboutController::class, 'show']);
    Route::get('site/pages', [SitePageContentController::class, 'show']);
    Route::get('site/broadcasting', [SiteBroadcastingController::class, 'show']);
    Route::get('site/captcha', [SiteController::class, 'captcha']);
    Route::post('site/visit', [SiteController::class, 'visit'])->middleware('throttle:60,1');
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/login/identify', [AuthController::class, 'identifyLogin']);
    Route::post('auth/login/passkey/options', [TwoFactorMethodController::class, 'passkeyPrimaryLoginOptions']);
    Route::post('auth/login/passkey/verify', [TwoFactorMethodController::class, 'passkeyPrimaryLoginVerify']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware('maintenance')->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register']);
        });

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{slug}', [ProductController::class, 'show']);
        Route::get('products/{slug}/reviews', [ReviewController::class, 'productReviews']);
        Route::post('purchase', [ProductController::class, 'purchase']);

        Route::get('services', [ServiceController::class, 'index']);
        Route::get('services/{slug}', [ServiceController::class, 'show']);
        Route::get('services/{slug}/reviews', [ReviewController::class, 'serviceReviews']);

        Route::get('blogs', [BlogController::class, 'index']);
        Route::get('blogs/{slug}', [BlogController::class, 'show']);

        Route::get('careers', [CareerController::class, 'index']);
        Route::get('careers/{slug}', [CareerController::class, 'show']);
        Route::post('careers/{slug}/apply', [CareerController::class, 'apply']);
        Route::get('hr/documents/download', [HrDocumentController::class, 'download']);

        Route::post('contact', [ContactController::class, 'store']);

        Route::get('reviews/captcha', [ReviewController::class, 'captchaConfig']);
        Route::get('reviews/stats', [ReviewController::class, 'stats']);
        Route::get('reviews/home', [ReviewController::class, 'home']);
        Route::get('reviews/featured', [ReviewController::class, 'featured']);
        Route::get('reviews/latest', [ReviewController::class, 'latest']);
        Route::get('reviews', [ReviewController::class, 'index']);
        Route::post('reviews', [ReviewController::class, 'store'])->middleware('throttle:5,1');
        Route::post('reviews/{uuid}/helpful', [ReviewController::class, 'markHelpful'])->middleware('throttle:20,1');
        Route::post('reviews/{uuid}/report', [ReviewController::class, 'report'])->middleware('throttle:10,1');

        Route::prefix('chatbot')->group(function (): void {
            Route::get('settings', [ChatbotController::class, 'settings']);
            Route::get('quick-replies', [ChatbotController::class, 'quickReplies']);
            Route::post('message', [ChatbotController::class, 'sendMessage']);
            Route::post('search', [ChatbotController::class, 'searchFaq']);
            Route::post('conversations', [ChatbotController::class, 'saveConversation']);
            Route::post('leads', [ChatbotController::class, 'saveLead']);
        });

        Route::get('pricing', [PricingController::class, 'index']);
        Route::get('site/offers', [SiteOffersController::class, 'index']);
        Route::get('site/home-sections', [SiteHomeSectionsController::class, 'show']);

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
        Route::post('subscriptions/{subscription}/renew', [ClientSubscriptionController::class, 'renew']);
        Route::get('subscriptions/{subscription}/domains', [ClientSubscriptionController::class, 'domainStatus']);
        Route::post('subscriptions/{subscription}/domains', [ClientSubscriptionController::class, 'submitDomains']);
        Route::post('subscriptions/{subscription}/domains/skip', [ClientSubscriptionController::class, 'skipDomains']);

        Route::get('invoices', [ClientInvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [ClientInvoiceController::class, 'show']);
        Route::get('invoices/{invoice}/download', [ClientInvoiceController::class, 'download']);

        Route::get('orders', [ClientOrderController::class, 'index']);
        Route::get('orders/{order}', [ClientOrderController::class, 'show']);

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
        Route::post('purchase/batch', [ClientPurchaseController::class, 'storeBatch']);
        Route::post('coupons/validate', [ClientCouponController::class, 'validateCode']);
        Route::post('payments/verify', [ClientPaymentController::class, 'verify']);
    });

    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy', 'maintenance', 'role:employee', 'employee.portal.menu'])->prefix('employee')->group(function (): void {
        Route::get('dashboard', [EmployeeDashboardController::class, 'index'])->middleware('permission:employee.dashboard.view');
        Route::get('profile', [EmployeeProfileController::class, 'show'])->middleware('permission:employee.profile.view');
        Route::put('profile', [EmployeeProfileController::class, 'update'])->middleware('permission:employee.profile.update');
        Route::patch('profile', [EmployeeProfileController::class, 'update'])->middleware('permission:employee.profile.update');

        Route::get('documents', [EmployeeDocumentController::class, 'index'])->middleware('permission:employee.documents.view');
        Route::post('documents', [EmployeeDocumentController::class, 'store'])->middleware('permission:employee.documents.upload');
        Route::get('documents/id-card', [EmployeeDocumentController::class, 'downloadIdCard'])->middleware('permission:employee.documents.view');
        Route::get('documents/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('permission:employee.documents.download');

        Route::get('leave', [EmployeeLeaveController::class, 'index'])->middleware('permission:employee.leave.view');
        Route::post('leave', [EmployeeLeaveController::class, 'store'])->middleware('permission:employee.leave.apply');
        Route::post('leave/{leaveRequest}/cancel', [EmployeeLeaveController::class, 'cancel'])->middleware('permission:employee.leave.cancel');

        Route::get('attendance', [EmployeeAttendanceController::class, 'index'])->middleware('permission:employee.attendance.view');
        Route::post('attendance', [EmployeeAttendanceController::class, 'store'])->middleware('permission:employee.attendance.submit');

        Route::get('tasks', [EmployeeTaskController::class, 'index'])->middleware('permission:employee.tasks.view');
        Route::post('tasks', [EmployeeTaskController::class, 'store'])->middleware('permission:employee.tasks.create');
        Route::get('tasks/{task}', [EmployeeTaskController::class, 'show'])->middleware('permission:employee.tasks.view');
        Route::put('tasks/{task}', [EmployeeTaskController::class, 'update'])->middleware('permission:employee.tasks.update');
        Route::patch('tasks/{task}', [EmployeeTaskController::class, 'update'])->middleware('permission:employee.tasks.update');
        Route::delete('tasks/{task}', [EmployeeTaskController::class, 'destroy'])->middleware('permission:employee.tasks.delete');

        Route::get('projects', [EmployeeProjectController::class, 'index'])->middleware('permission:employee.projects.view');
        Route::post('projects', [EmployeeProjectController::class, 'store'])->middleware('permission:employee.projects.create');
        Route::get('projects/{project}', [EmployeeProjectController::class, 'show'])->middleware('permission:employee.projects.view');
        Route::put('projects/{project}', [EmployeeProjectController::class, 'update'])->middleware('permission:employee.projects.update');
        Route::patch('projects/{project}', [EmployeeProjectController::class, 'update'])->middleware('permission:employee.projects.update');
        Route::delete('projects/{project}', [EmployeeProjectController::class, 'destroy'])->middleware('permission:employee.projects.delete');

        Route::get('timesheets', [EmployeeTimesheetController::class, 'index'])->middleware('permission:employee.timesheets.view');
        Route::post('timesheets', [EmployeeTimesheetController::class, 'store'])->middleware('permission:employee.timesheets.create');
        Route::get('timesheets/{timesheet}', [EmployeeTimesheetController::class, 'show'])->middleware('permission:employee.timesheets.view');
        Route::put('timesheets/{timesheet}', [EmployeeTimesheetController::class, 'update'])->middleware('permission:employee.timesheets.update');
        Route::patch('timesheets/{timesheet}', [EmployeeTimesheetController::class, 'update'])->middleware('permission:employee.timesheets.update');
        Route::delete('timesheets/{timesheet}', [EmployeeTimesheetController::class, 'destroy'])->middleware('permission:employee.timesheets.delete');

        Route::get('calendar', [EmployeeCalendarEventController::class, 'index'])->middleware('permission:employee.calendar.view');
        Route::post('calendar', [EmployeeCalendarEventController::class, 'store'])->middleware('permission:employee.calendar.create');
        Route::get('calendar/{calendar_event}', [EmployeeCalendarEventController::class, 'show'])->middleware('permission:employee.calendar.view');
        Route::put('calendar/{calendar_event}', [EmployeeCalendarEventController::class, 'update'])->middleware('permission:employee.calendar.update');
        Route::patch('calendar/{calendar_event}', [EmployeeCalendarEventController::class, 'update'])->middleware('permission:employee.calendar.update');
        Route::delete('calendar/{calendar_event}', [EmployeeCalendarEventController::class, 'destroy'])->middleware('permission:employee.calendar.delete');

        Route::get('announcements', [EmployeeAnnouncementController::class, 'index'])->middleware('permission:employee.announcements.view');
        Route::get('announcements/{announcement}', [EmployeeAnnouncementController::class, 'show'])->middleware('permission:employee.announcements.view');
        Route::post('announcements/{announcement}/read', [EmployeeAnnouncementController::class, 'markRead'])->middleware('permission:employee.announcements.view');

        Route::get('assets', [EmployeeAssetController::class, 'index'])->middleware('permission:employee.assets.view');
        Route::get('assets/{company_asset}', [EmployeeAssetController::class, 'show'])->middleware('permission:employee.assets.view');

        Route::get('training', [EmployeeTrainingController::class, 'index'])->middleware('permission:employee.training.view');
        Route::get('training/{training}', [EmployeeTrainingController::class, 'show'])->middleware('permission:employee.training.view');
        Route::put('training/{training}', [EmployeeTrainingController::class, 'update'])->middleware('permission:employee.training.update');
        Route::patch('training/{training}', [EmployeeTrainingController::class, 'update'])->middleware('permission:employee.training.update');

        Route::get('performance', [EmployeePerformanceReviewController::class, 'index'])->middleware('permission:employee.performance.view');
        Route::get('performance/{performance_review}', [EmployeePerformanceReviewController::class, 'show'])->middleware('permission:employee.performance.view');
        Route::post('performance/{performance_review}/acknowledge', [EmployeePerformanceReviewController::class, 'acknowledge'])->middleware('permission:employee.performance.acknowledge');

        Route::get('helpdesk', [EmployeeHelpdeskTicketController::class, 'index'])->middleware('permission:employee.helpdesk.view');
        Route::post('helpdesk', [EmployeeHelpdeskTicketController::class, 'store'])->middleware('permission:employee.helpdesk.create');
        Route::get('helpdesk/{helpdesk_ticket}', [EmployeeHelpdeskTicketController::class, 'show'])->middleware('permission:employee.helpdesk.view');
        Route::put('helpdesk/{helpdesk_ticket}', [EmployeeHelpdeskTicketController::class, 'update'])->middleware('permission:employee.helpdesk.update');
        Route::patch('helpdesk/{helpdesk_ticket}', [EmployeeHelpdeskTicketController::class, 'update'])->middleware('permission:employee.helpdesk.update');

        Route::get('resignation', [EmployeeResignationController::class, 'show'])->middleware('permission:employee.resignation.view');
        Route::post('resignation', [EmployeeResignationController::class, 'store'])->middleware('permission:employee.resignation.submit');
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

    // HR admin routes (super admin + HR manager)
    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy', 'role:super_admin,hr_manager'])->prefix('admin')->group(function (): void {
        Route::post('uploads', [UploadController::class, 'store']);
        Route::get('company-roles', [CompanyRoleController::class, 'index'])->middleware('permission:hr.company-roles.view');
        Route::post('company-roles', [CompanyRoleController::class, 'store'])->middleware('permission:hr.company-roles.manage');
        Route::get('company-roles/{company_role}', [CompanyRoleController::class, 'show'])->middleware('permission:hr.company-roles.view');
        Route::put('company-roles/{company_role}', [CompanyRoleController::class, 'update'])->middleware('permission:hr.company-roles.manage');
        Route::patch('company-roles/{company_role}', [CompanyRoleController::class, 'update'])->middleware('permission:hr.company-roles.manage');
        Route::put('company-roles/{company_role}/menus', [CompanyRoleController::class, 'updateMenus'])->middleware('permission:hr.company-roles.manage');
        Route::delete('company-roles/{company_role}', [CompanyRoleController::class, 'destroy'])->middleware('permission:hr.company-roles.manage');
        Route::get('portal-menus', [PortalMenuController::class, 'index'])->middleware('permission:hr.company-roles.view');
        Route::post('portal-menus', [PortalMenuController::class, 'store'])->middleware('permission:hr.company-roles.manage');
        Route::get('portal-menus/{portal_menu}', [PortalMenuController::class, 'show'])->middleware('permission:hr.company-roles.view');
        Route::put('portal-menus/{portal_menu}', [PortalMenuController::class, 'update'])->middleware('permission:hr.company-roles.manage');
        Route::patch('portal-menus/{portal_menu}', [PortalMenuController::class, 'update'])->middleware('permission:hr.company-roles.manage');
        Route::delete('portal-menus/{portal_menu}', [PortalMenuController::class, 'destroy'])->middleware('permission:hr.company-roles.manage');
        Route::get('announcements', [AdminAnnouncementController::class, 'index'])->middleware('permission:hr.announcements.view');
        Route::post('announcements', [AdminAnnouncementController::class, 'store'])->middleware('permission:hr.announcements.manage');
        Route::get('announcements/{announcement}', [AdminAnnouncementController::class, 'show'])->middleware('permission:hr.announcements.view');
        Route::put('announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->middleware('permission:hr.announcements.manage');
        Route::patch('announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->middleware('permission:hr.announcements.manage');
        Route::delete('announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->middleware('permission:hr.announcements.manage');
        Route::get('assets', [AdminCompanyAssetController::class, 'index'])->middleware('permission:hr.assets.view');
        Route::post('assets', [AdminCompanyAssetController::class, 'store'])->middleware('permission:hr.assets.manage');
        Route::get('assets/{company_asset}', [AdminCompanyAssetController::class, 'show'])->middleware('permission:hr.assets.view');
        Route::put('assets/{company_asset}', [AdminCompanyAssetController::class, 'update'])->middleware('permission:hr.assets.manage');
        Route::patch('assets/{company_asset}', [AdminCompanyAssetController::class, 'update'])->middleware('permission:hr.assets.manage');
        Route::delete('assets/{company_asset}', [AdminCompanyAssetController::class, 'destroy'])->middleware('permission:hr.assets.manage');
        Route::get('training', [AdminTrainingController::class, 'index'])->middleware('permission:hr.training.view');
        Route::post('training', [AdminTrainingController::class, 'store'])->middleware('permission:hr.training.manage');
        Route::get('training/{training}', [AdminTrainingController::class, 'show'])->middleware('permission:hr.training.view');
        Route::put('training/{training}', [AdminTrainingController::class, 'update'])->middleware('permission:hr.training.manage');
        Route::patch('training/{training}', [AdminTrainingController::class, 'update'])->middleware('permission:hr.training.manage');
        Route::delete('training/{training}', [AdminTrainingController::class, 'destroy'])->middleware('permission:hr.training.manage');
        Route::get('performance', [AdminPerformanceReviewController::class, 'index'])->middleware('permission:hr.performance.view');
        Route::post('performance', [AdminPerformanceReviewController::class, 'store'])->middleware('permission:hr.performance.manage');
        Route::get('performance/{performance_review}', [AdminPerformanceReviewController::class, 'show'])->middleware('permission:hr.performance.view');
        Route::put('performance/{performance_review}', [AdminPerformanceReviewController::class, 'update'])->middleware('permission:hr.performance.manage');
        Route::patch('performance/{performance_review}', [AdminPerformanceReviewController::class, 'update'])->middleware('permission:hr.performance.manage');
        Route::delete('performance/{performance_review}', [AdminPerformanceReviewController::class, 'destroy'])->middleware('permission:hr.performance.manage');
        Route::get('helpdesk', [AdminHelpdeskTicketController::class, 'index'])->middleware('permission:hr.helpdesk.view');
        Route::post('helpdesk', [AdminHelpdeskTicketController::class, 'store'])->middleware('permission:hr.helpdesk.manage');
        Route::get('helpdesk/{helpdesk_ticket}', [AdminHelpdeskTicketController::class, 'show'])->middleware('permission:hr.helpdesk.view');
        Route::put('helpdesk/{helpdesk_ticket}', [AdminHelpdeskTicketController::class, 'update'])->middleware('permission:hr.helpdesk.manage');
        Route::patch('helpdesk/{helpdesk_ticket}', [AdminHelpdeskTicketController::class, 'update'])->middleware('permission:hr.helpdesk.manage');
        Route::delete('helpdesk/{helpdesk_ticket}', [AdminHelpdeskTicketController::class, 'destroy'])->middleware('permission:hr.helpdesk.manage');
        Route::get('careers', [AdminCareerController::class, 'index'])->middleware('permission:hr.careers.view');
        Route::post('careers', [AdminCareerController::class, 'store'])->middleware('permission:hr.careers.manage');
        Route::get('careers/{career}', [AdminCareerController::class, 'show'])->middleware('permission:hr.careers.view');
        Route::put('careers/{career}', [AdminCareerController::class, 'update'])->middleware('permission:hr.careers.manage');
        Route::patch('careers/{career}', [AdminCareerController::class, 'update'])->middleware('permission:hr.careers.manage');
        Route::delete('careers/{career}', [AdminCareerController::class, 'destroy'])->middleware('permission:hr.careers.manage');
        Route::get('job-applications/export', [JobApplicationController::class, 'export'])->middleware('permission:hr.applications.export');
        Route::post('job-applications/{job_application}/convert-employee', [JobApplicationController::class, 'convertToEmployee'])->middleware('permission:hr.employees.manage');
        Route::get('job-applications/{job_application}/documents/{document}/download', [JobApplicationController::class, 'downloadDocument'])->middleware('permission:hr.applications.view');
        Route::get('job-applications', [JobApplicationController::class, 'index'])->middleware('permission:hr.applications.view');
        Route::get('job-applications/{job_application}', [JobApplicationController::class, 'show'])->middleware('permission:hr.applications.view');
        Route::put('job-applications/{job_application}', [JobApplicationController::class, 'update'])->middleware('permission:hr.applications.manage');
        Route::patch('job-applications/{job_application}', [JobApplicationController::class, 'update'])->middleware('permission:hr.applications.manage');
        Route::delete('job-applications/{job_application}', [JobApplicationController::class, 'destroy'])->middleware('permission:hr.applications.manage');
        Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:hr.employees.view');
        Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:hr.employees.manage');
        Route::get('employees/id-cards', [EmployeeController::class, 'exportIdCards'])->middleware('permission:hr.employees.view');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:hr.employees.view');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:hr.employees.manage');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:hr.employees.manage');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:hr.employees.delete');
        Route::get('employees/{employee}/id-card', [EmployeeController::class, 'downloadIdCard'])->middleware('permission:hr.employees.view');
        Route::post('employees/{employee}/documents', [EmployeeController::class, 'uploadDocument'])->middleware('permission:hr.employees.documents');
        Route::get('employees/{employee}/documents/{document}/download', [EmployeeController::class, 'downloadDocument'])->middleware('permission:hr.employees.documents');
        Route::post('employees/{employee}/exit', [EmployeeController::class, 'initiateExit'])->middleware('permission:hr.employees.exit');
        Route::patch('employees/{employee}/exit', [EmployeeController::class, 'updateExit'])->middleware('permission:hr.employees.exit');
        Route::post('employees/{employee}/exit-documents', [EmployeeController::class, 'uploadExitDocument'])->middleware('permission:hr.employees.exit');
        Route::post('employees/{employee}/portal-access', [EmployeeController::class, 'provisionPortal'])->middleware('permission:hr.employees.portal');
        Route::post('employees/{employee}/resend-portal-login', [EmployeeController::class, 'resendPortalLogin'])->middleware('permission:hr.employees.portal');
        Route::post('employees/{employee}/send-portal-login', [EmployeeController::class, 'sendPortalLogin'])->middleware('permission:hr.employees.portal');
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->middleware('permission:hr.leave.view');
        Route::patch('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->middleware('permission:hr.leave.manage');
        Route::get('attendance-records', [AttendanceRecordController::class, 'index'])->middleware('permission:hr.attendance.view');
        Route::patch('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update'])->middleware('permission:hr.attendance.manage');
        Route::get('users', [AdminUserController::class, 'index'])->middleware('permission:hr.users.view');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->middleware('permission:hr.users.view');
    });

    // Full super admin routes
    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy', 'role:super_admin'])->prefix('admin')->group(function (): void {
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/subscriptions', [ReportController::class, 'subscriptions']);
        Route::get('reports/products', [ReportController::class, 'products']);
        Route::get('reports/export', [ReportController::class, 'export']);

        Route::get('notifications', [AdminNotificationController::class, 'index']);
        Route::post('notifications', [AdminNotificationController::class, 'store']);
        Route::delete('notifications/{notification}', [AdminNotificationController::class, 'destroy']);

        Route::get('tenants/pending-domains', [TenantController::class, 'pendingDomains']);
        Route::post('tenants/{tenant}/pending-domains/{subscription}/approve', [TenantController::class, 'approvePendingDomain']);
        Route::post('tenants/{tenant}/pending-domains/{subscription}/reject', [TenantController::class, 'rejectPendingDomain']);
        Route::apiResource('tenants', TenantController::class);
        Route::apiResource('users', AdminUserController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('products', AdminProductController::class);
        Route::apiResource('plans', AdminPlanController::class);
        Route::apiResource('coupons', AdminCouponController::class);
        Route::apiResource('services', AdminServiceController::class);

        Route::post('subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel']);
        Route::post('subscriptions/{subscription}/renew', [AdminSubscriptionController::class, 'renew']);
        Route::post('subscriptions/{subscription}/create-billing', [AdminSubscriptionController::class, 'createBilling']);
        Route::apiResource('subscriptions', AdminSubscriptionController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'destroy']);
        Route::apiResource('invoices', AdminInvoiceController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::get('invoices/{invoice}/download', [AdminInvoiceController::class, 'download']);
        Route::apiResource('payments', AdminPaymentController::class)->only(['index', 'show', 'destroy']);
        Route::post('payments/record', [AdminPaymentController::class, 'record']);

        Route::apiResource('support-tickets', AdminSupportTicketController::class)->only(['index', 'show', 'update']);
        Route::post('support-tickets/{support_ticket}/replies', [AdminSupportTicketController::class, 'reply']);
        Route::apiResource('blogs', AdminBlogController::class);
        Route::post('hr-managers', [HrManagerController::class, 'store']);
        Route::get('access-roles', [AccessRoleController::class, 'index'])->middleware('permission:hr.permissions.view');
        Route::post('access-roles/sync', [AccessRoleController::class, 'sync'])->middleware('permission:hr.permissions.manage');
        Route::put('access-roles/{role}', [AccessRoleController::class, 'update'])->middleware('permission:hr.permissions.manage');
        Route::apiResource('careers', AdminCareerController::class);
        Route::get('job-applications/export', [JobApplicationController::class, 'export']);
        Route::post('job-applications/{job_application}/convert-employee', [JobApplicationController::class, 'convertToEmployee']);
        Route::get('job-applications/{job_application}/documents/{document}/download', [JobApplicationController::class, 'downloadDocument']);
        Route::apiResource('job-applications', JobApplicationController::class)->except(['store']);
        Route::get('employees/id-cards', [EmployeeController::class, 'exportIdCards']);
        Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::get('employees/{employee}/id-card', [EmployeeController::class, 'downloadIdCard']);
        Route::post('employees/{employee}/documents', [EmployeeController::class, 'uploadDocument']);
        Route::get('employees/{employee}/documents/{document}/download', [EmployeeController::class, 'downloadDocument']);
        Route::post('employees/{employee}/exit', [EmployeeController::class, 'initiateExit']);
        Route::patch('employees/{employee}/exit', [EmployeeController::class, 'updateExit']);
        Route::post('employees/{employee}/exit-documents', [EmployeeController::class, 'uploadExitDocument']);
        Route::post('employees/{employee}/portal-access', [EmployeeController::class, 'provisionPortal']);
        Route::post('employees/{employee}/resend-portal-login', [EmployeeController::class, 'resendPortalLogin']);
        Route::post('employees/{employee}/send-portal-login', [EmployeeController::class, 'sendPortalLogin']);
        Route::get('leave-requests', [LeaveRequestController::class, 'index']);
        Route::patch('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
        Route::get('attendance-records', [AttendanceRecordController::class, 'index']);
        Route::patch('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::apiResource('testimonials', AdminTestimonialController::class)->except(['show']);
        Route::get('reviews/stats', [AdminReviewController::class, 'stats']);
        Route::get('reviews/export', [AdminReviewController::class, 'export']);
        Route::post('reviews/{review}/approve', [AdminReviewController::class, 'approve']);
        Route::post('reviews/{review}/reject', [AdminReviewController::class, 'reject']);
        Route::post('reviews/{review}/reply', [AdminReviewController::class, 'reply']);
        Route::post('reviews/{review}/feature', [AdminReviewController::class, 'feature']);
        Route::post('reviews/{review}/verify', [AdminReviewController::class, 'verify']);
        Route::apiResource('reviews', AdminReviewController::class)->except(['store']);
        Route::apiResource('hero-slides', HeroSlideController::class)->except(['show']);
        Route::post('uploads', [UploadController::class, 'store']);
        Route::apiResource('faqs', AdminFaqController::class)->except(['show']);
        Route::apiResource('contact-messages', ContactMessageController::class)->except(['store']);

        Route::prefix('chatbot')->middleware('permission:chatbot.view')->group(function (): void {
            Route::get('dashboard', [ChatbotDashboardController::class, 'index']);
            Route::get('analytics', [ChatbotAnalyticsController::class, 'index'])->middleware('permission:chatbot.analytics');
            Route::get('settings', [ChatbotSettingsController::class, 'show']);
            Route::put('settings', [ChatbotSettingsController::class, 'update'])->middleware('permission:chatbot.edit');
            Route::get('conversations', [ChatbotConversationController::class, 'index']);
            Route::apiResource('faqs', ChatbotFaqController::class)->except(['show']);
            Route::apiResource('categories', ChatbotCategoryController::class)->except(['show']);
            Route::apiResource('leads', ChatbotLeadController::class)->only(['index', 'update', 'destroy']);
        });

        Route::apiResource('settings', SettingController::class)->except(['show']);
        Route::post('settings/bulk', [SettingController::class, 'bulkUpdate']);
        Route::post('integrations/{integration}/test-email', [IntegrationController::class, 'sendTestEmail']);
        Route::apiResource('integrations', IntegrationController::class)->except(['show']);

        // License Management
        Route::get('licenses', [AdminLicenseController::class, 'index']);
        Route::post('licenses', [AdminLicenseController::class, 'store']);
        Route::get('licenses/{license}', [AdminLicenseController::class, 'show']);
        Route::put('licenses/{license}', [AdminLicenseController::class, 'update']);
        Route::delete('licenses/{license}', [AdminLicenseController::class, 'destroy']);
        Route::post('licenses/{license}/suspend', [AdminLicenseController::class, 'suspend']);
        Route::post('licenses/{license}/revoke', [AdminLicenseController::class, 'revoke']);
        Route::post('licenses/{license}/activate', [AdminLicenseController::class, 'activateLicense']);
        Route::post('licenses/{license}/reset-domains', [AdminLicenseController::class, 'resetDomains']);
        Route::post('licenses/{license}/force-logout', [AdminLicenseController::class, 'forceLogout']);
        Route::post('licenses/{license}/regenerate', [AdminLicenseController::class, 'regenerate']);
        Route::post('licenses/{license}/notify-ready', [AdminLicenseController::class, 'notifyReady']);
        Route::get('licenses/{license}/activity', [AdminLicenseController::class, 'activity']);
        Route::get('licenses/{license}/history', [AdminLicenseController::class, 'history']);
        Route::get('licenses/{license}/installations', [AdminLicenseController::class, 'installations']);
        Route::post('licenses/{license}/installations/reset', [AdminLicenseController::class, 'resetInstallations']);
        Route::post('licenses/{license}/installations/{installation}/revoke', [AdminLicenseController::class, 'revokeInstallation']);

        Route::get('product-integrations', [AdminProductIntegrationController::class, 'index']);
        Route::post('product-integrations', [AdminProductIntegrationController::class, 'store']);
        Route::get('product-integrations/api-logs', [AdminProductIntegrationController::class, 'apiLogs']);
        Route::get('product-integrations/domain-reset-requests', [AdminProductIntegrationController::class, 'domainResetRequests']);
        Route::post('product-integrations/domain-reset-requests/{domainResetRequest}/review', [AdminProductIntegrationController::class, 'reviewDomainReset']);
        Route::get('product-integrations/{productIntegration}', [AdminProductIntegrationController::class, 'show']);
        Route::put('product-integrations/{productIntegration}', [AdminProductIntegrationController::class, 'update']);
        Route::delete('product-integrations/{productIntegration}', [AdminProductIntegrationController::class, 'destroy']);
        Route::post('product-integrations/{productIntegration}/regenerate-keys', [AdminProductIntegrationController::class, 'regenerateKeys']);
        Route::get('product-integrations/{productIntegration}/guide', [AdminProductIntegrationController::class, 'guide']);
    });

    // ---------------------------------------------------------------
    // Company API — canonical product licensing contract
    // Rate limited: 300 requests/minute per IP (homepage parallel verifies)
    // ---------------------------------------------------------------
    Route::middleware(['throttle:300,1'])->prefix('company')->group(function (): void {
        Route::middleware(['company.api'])->group(function (): void {
            Route::post('activate', [CompanyLicenseController::class, 'activate']);
            Route::post('refresh-token', [CompanyLicenseController::class, 'refreshToken']);
        });

        Route::middleware(['company.api:token'])->group(function (): void {
            Route::post('verify', [CompanyLicenseController::class, 'verify']);
            Route::get('modules', [CompanyLicenseController::class, 'modules']);
            Route::get('limits', [CompanyLicenseController::class, 'limits']);
            Route::get('addons', [CompanyLicenseController::class, 'addons']);
            Route::post('heartbeat', [CompanyLicenseController::class, 'heartbeat']);
        });
    });

    // ---------------------------------------------------------------
    // Central API — legacy product licensing (deprecated; prefer /company)
    // Rate limited: 60 requests/minute per IP
    // ---------------------------------------------------------------
    Route::middleware(['throttle:60,1'])->prefix('central')->group(function (): void {
        // Legacy license verification (unsigned)
        Route::post('license/verify', [CentralLicenseController::class, 'verify']);
        Route::post('license/activate-domain', [CentralLicenseController::class, 'activateDomain']);
        Route::post('license/deactivate-domain', [CentralLicenseController::class, 'deactivateDomain']);

        // Signed product integration API
        Route::middleware(['product.api'])->group(function (): void {
            Route::post('license/check', [CentralProductIntegrationController::class, 'check']);
            Route::post('license/activate', [CentralProductIntegrationController::class, 'activate']);
            Route::post('license/deactivate', [CentralProductIntegrationController::class, 'deactivate']);
            Route::get('subscription', [CentralProductIntegrationController::class, 'subscription']);
            Route::get('modules', [CentralProductIntegrationController::class, 'modules']);
            Route::get('limits', [CentralProductIntegrationController::class, 'limits']);
            Route::get('addons', [CentralProductIntegrationController::class, 'addons']);
            Route::post('heartbeat', [CentralProductIntegrationController::class, 'heartbeat']);
        });

        // Product & plan catalogue
        Route::get('products', [CentralProductController::class, 'index']);
        Route::get('products/{slug}/plans', [CentralProductController::class, 'plans']);
    });

    // ---------------------------------------------------------------
    // Client License endpoints (under existing client middleware)
    // ---------------------------------------------------------------
    Route::middleware(['session.timeout', 'auth:sanctum', 'security.policy', 'maintenance', 'role:client', 'tenant'])->prefix('client')->group(function (): void {
        Route::get('licenses', [ClientLicenseController::class, 'index']);
        Route::get('licenses/{license}', [ClientLicenseController::class, 'show']);
        Route::post('licenses/{license}/domains', [ClientLicenseController::class, 'registerDomain']);
        Route::delete('licenses/{license}/domains', [ClientLicenseController::class, 'removeDomain']);
        Route::post('licenses/{license}/domain-reset-request', [ClientLicenseController::class, 'requestDomainReset']);
        Route::post('licenses/{license}/activate-product', [ClientLicenseController::class, 'activateProduct']);
        Route::post('licenses/{license}/deactivate-product', [ClientLicenseController::class, 'deactivateProduct']);
        Route::get('licenses/{license}/activity', [ClientLicenseController::class, 'activity']);
        Route::get('licenses/{license}/history', [ClientLicenseController::class, 'history']);
        Route::get('licenses/{license}/installations', [ClientLicenseController::class, 'installations']);
        Route::post('licenses/{license}/installations/{installation}/deactivate', [ClientLicenseController::class, 'deactivateInstallation']);
        Route::post('licenses/{license}/extra-seats', [ClientLicenseController::class, 'purchaseExtraSeats']);
    });

    // ---------------------------------------------------------------
    // Payment Webhooks — CSRF exempt (handled by route placement)
    // ---------------------------------------------------------------
    Route::prefix('webhooks')->group(function (): void {
        Route::post('razorpay', [PaymentWebhookController::class, 'razorpay']);
    });
});
