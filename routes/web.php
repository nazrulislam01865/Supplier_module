<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\CompanyDocumentController;
use App\Http\Middleware\EnsureSupplierActive;
use App\Http\Middleware\EnforceSupplierSessionTimeout;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login',[AuthController::class,'loginForm'])->name('supplier.login');
    Route::post('/login',[AuthController::class,'login'])->name('supplier.login.post'); // Failed attempts are limited per account and IP in AuthController.
    Route::get('/forgot-password',[AuthController::class,'forgotForm'])->name('password.request');
    Route::post('/forgot-password',[AuthController::class,'sendReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}',[AuthController::class,'resetForm'])->name('password.reset');
    Route::post('/reset-password',[AuthController::class,'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware(['auth',EnforceSupplierSessionTimeout::class,EnsureSupplierActive::class])->group(function () {
    Route::post('/session/activity', fn () => response()->noContent())
        ->middleware('throttle:12,1')->name('supplier.session.activity');
    Route::get('/change-password', [AuthController::class, 'changeRequiredForm'])->name('supplier.password.change');
    Route::post('/change-password', [AuthController::class, 'changeRequiredPassword'])->middleware('throttle:5,1')->name('supplier.password.update');
    Route::get('/',[PortalController::class,'index'])->name('supplier.home');
    Route::get('/dashboard/revision', [PortalController::class, 'dashboardRevision'])
        ->middleware('throttle:30,1')->name('supplier.dashboard.revision');
    Route::get('/dashboard/refresh', [PortalController::class, 'dashboardRefresh'])
        ->middleware('throttle:30,1')->name('supplier.dashboard.refresh');
    Route::post('/dashboard/realtime-auth', [\App\Http\Controllers\SupplierDashboardRealtimeController::class, 'authorize'])
        ->middleware('throttle:20,1')->name('supplier.dashboard.realtime.auth');
    Route::get('/dashboard/notifications', [PortalController::class, 'notificationCenter'])
        ->middleware('throttle:30,1')->name('supplier.dashboard.notifications.center');
    Route::post('/dashboard/notifications/read', [PortalController::class, 'markNotificationsRead'])
        ->middleware('throttle:20,1')->name('supplier.dashboard.notifications.read');
    Route::get('/products/prices', [\App\Http\Controllers\SupplierProductPriceController::class, 'index'])->name('supplier.products.prices');
    Route::get('/products/prices/realtime-revision', [\App\Http\Controllers\SupplierCatalogRealtimeController::class, 'revision'])
        ->middleware('throttle:15,1')->name('supplier.products.prices.realtime.revision');
    Route::post('/products/prices/realtime-auth', [\App\Http\Controllers\SupplierCatalogRealtimeController::class, 'authorize'])
        ->middleware('throttle:15,1')->name('supplier.products.prices.realtime.auth');
    Route::get('/products/prices/{id}/image', \App\Http\Controllers\SupplierProductImageController::class)
        ->whereNumber('id')->middleware('throttle:120,1')->name('supplier.products.prices.image');
    Route::get('/products/prices/{id}', [\App\Http\Controllers\SupplierProductPriceController::class, 'show'])
        ->whereNumber('id')->name('supplier.products.prices.show');
    Route::get('/products/prices/{id}/edit', [\App\Http\Controllers\SupplierProductPriceController::class, 'edit'])
        ->whereNumber('id')->name('supplier.products.prices.edit');
    Route::post('/products/prices/{id}', [\App\Http\Controllers\SupplierProductPriceController::class, 'submit'])
        ->whereNumber('id')->middleware('throttle:10,1')->name('supplier.products.prices.submit');
    Route::get('/orders/{id}',[\App\Http\Controllers\SupplierOrderController::class,'show'])->whereNumber('id')->name('supplier.orders.show');
    Route::get('/orders/{id}/realtime-revision', [\App\Http\Controllers\SupplierOrderRealtimeController::class, 'revision'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('supplier.orders.realtime.revision');
    Route::post('/orders/{id}/realtime-auth', [\App\Http\Controllers\SupplierOrderRealtimeController::class, 'authorize'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('supplier.orders.realtime.auth');
    Route::post('/orders/{id}/workflow/tasks/{task}/action', \App\Http\Controllers\SupplierWorkflowActionController::class)
        ->whereNumber('id')->whereNumber('task')->middleware('throttle:10,1')->name('supplier.orders.workflow.action');
    Route::get('/orders/{id}/artwork/{document}', \App\Http\Controllers\SupplierOrderArtworkController::class)->whereNumber('id')->whereNumber('document')->middleware('throttle:30,1')->name('supplier.orders.artwork');
    Route::post('/logout',[AuthController::class,'logout'])->name('supplier.logout');
    // These signed-bridge task actions must not consume the dashboard's
    // shared API throttle bucket (nor other RFQ actions' per-user quota).
    Route::post('/api/v1/rfqs/{id}/supplier-task', \App\Http\Controllers\SupplierInquiryTaskController::class)
        ->whereNumber('id')->middleware('throttle:supplier-inquiry-task');
    Route::get('/api/v1/rfqs/{id}/supplier-task/documents/{document}', \App\Http\Controllers\SupplierInquiryTaskDocumentController::class)
        ->whereNumber('id')->whereNumber('document')->middleware('throttle:supplier-inquiry-task-file');
    Route::prefix('api/v1')->middleware('throttle:60,1')->group(function () {
        Route::put('/profile',[ProfileController::class,'update']);
        Route::get('/dashboard/rfqs/{id}', [PortalController::class, 'rfqSummary'])
            ->whereNumber('id')->name('supplier.dashboard.rfq.summary');
        Route::get('/rfqs/{id}/quotation',[QuotationController::class,'show'])->whereNumber('id');
        Route::get('/rfqs/{id}/buyer-documents/{document}', \App\Http\Controllers\BuyerRfqDocumentController::class)->whereNumber('id')->whereNumber('document');
        Route::put('/rfqs/{id}/quotation',[QuotationController::class,'save'])->whereNumber('id');
        Route::post('/rfqs/{id}/submit',[QuotationController::class,'submit'])->whereNumber('id')->middleware('throttle:10,1');
        Route::post('/rfqs/{id}/decline',[QuotationController::class,'decline'])->whereNumber('id');
        Route::post('/rfqs/{id}/documents',[DocumentController::class,'upload'])->whereNumber('id')->middleware('throttle:10,1');
        Route::get('/documents/{id}/download',[DocumentController::class,'download'])->whereNumber('id');
        Route::delete('/documents/{id}',[DocumentController::class,'remove'])->whereNumber('id');
        Route::post('/support',[SupportController::class,'store']);
        Route::get('/company-documents',[CompanyDocumentController::class,'index']);
        Route::post('/company-documents',[CompanyDocumentController::class,'upload'])->middleware('throttle:10,1');
        Route::delete('/company-documents/{id}',[CompanyDocumentController::class,'remove'])->whereNumber('id');
    });
});
