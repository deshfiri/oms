<?php

use App\Http\Controllers\CartsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DamageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LostController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\ProcessingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnsController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreOtpController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Stores (admin)
    Route::middleware('role:admin')->group(function () {
        Route::resource('stores', StoreController::class)->except(['show']);
        Route::post('/stores/{store}/ping', [StoreController::class, 'ping'])->name('stores.ping');
        Route::post('/stores/{store}/sync', [StoreController::class, 'syncNow'])->name('stores.sync');
        // OTP management for the license-server integration
        Route::post('/stores/{store}/otp', [StoreOtpController::class, 'store'])->name('stores.otp.store');
        Route::delete('/stores/{store}/otp/{otp}', [StoreOtpController::class, 'destroy'])->name('stores.otp.destroy');
        Route::post('/stores/{store}/license-key/regenerate', [StoreOtpController::class, 'regenerateKey'])->name('stores.license-key.regenerate');
    });

    // Background auto-pull — pinged by the open-page poller so new orders
    // appear automatically without anyone clicking Sync. Throttled server-side.
    Route::get('/sync/pull', [StoreController::class, 'autoSync'])->name('sync.pull');

    // Instant change-detector: the open page polls this every few seconds and
    // reloads only when the queue actually changed. Also runs a throttled pull.
    Route::get('/poll/signature', [\App\Http\Controllers\PollController::class, 'signature'])->name('poll.signature');

    // All Orders — master browser with advanced filtering
    Route::get('/orders',          [\App\Http\Controllers\AllOrdersController::class, 'index'])->name('orders.index');
    Route::get('/orders/export',   [\App\Http\Controllers\AllOrdersController::class, 'exportCsv'])->name('orders.export');
    // AJAX partial for live table refresh — must come before the {order} wildcard
    Route::get('/orders/rows',     [\App\Http\Controllers\AllOrdersController::class, 'rows'])->name('orders.rows');

    // Inbox legacy + Order detail
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/status', [OrderController::class, 'setStatus'])->name('orders.status');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // ── Social Media Manager / Admin: place orders from inside OMS ──
    Route::middleware('role:social_media_manager,admin')->group(function () {
        Route::get('/orders-new',                  [\App\Http\Controllers\OrderCreationController::class, 'index'])->name('orders-new.index');
        Route::get('/orders-new/create',           [\App\Http\Controllers\OrderCreationController::class, 'create'])->name('orders-new.create');
        Route::post('/orders-new',                 [\App\Http\Controllers\OrderCreationController::class, 'store'])->name('orders-new.store');
        Route::delete('/orders-new/{order}',       [\App\Http\Controllers\OrderCreationController::class, 'destroy'])->name('orders-new.destroy');
        Route::get('/orders-new/product-search',   [\App\Http\Controllers\OrderCreationController::class, 'productSearch'])->name('orders-new.product-search');
        Route::get('/orders-new/shipping-quote',    [\App\Http\Controllers\OrderCreationController::class, 'shippingQuote'])->name('orders-new.shipping-quote');
    });

    // ── Customer Support: Verification ───────────────────────────────
    Route::middleware('role:customer_support,admin')->group(function () {
        Route::get('/verification',                    [VerificationController::class, 'index'])->name('verification.index');
        Route::get('/verification/rows',               [VerificationController::class, 'rows'])->name('verification.rows');
        Route::get('/verification/{order}',            [VerificationController::class, 'show'])->name('verification.show');
        Route::patch('/verification/{order}',          [VerificationController::class, 'update'])->name('verification.update');
        Route::post('/verification/{order}/items',     [VerificationController::class, 'addItem'])->name('verification.item.add');
        Route::patch('/verification/{order}/items/{item}', [VerificationController::class, 'updateItem'])->name('verification.item.update');
        Route::delete('/verification/{order}/items/{item}', [VerificationController::class, 'removeItem'])->name('verification.item.remove');
        Route::post('/verification/{order}/confirm',   [VerificationController::class, 'confirm'])->name('verification.confirm');
        Route::post('/verification/{order}/cancel',    [VerificationController::class, 'cancel'])->name('verification.cancel');
        // Bulk operations (§16)
        Route::post('/verification/bulk-confirm',      [VerificationController::class, 'bulkConfirm'])->name('verification.bulk-confirm');
        Route::post('/verification/bulk-cancel',       [VerificationController::class, 'bulkCancel'])->name('verification.bulk-cancel');
    });

    // ── Warehouse: Processing + Pack scanning + Labels ───────────────
    Route::middleware('role:warehouse_admin,packer,admin')->group(function () {
        Route::get('/processing',             [ProcessingController::class, 'index'])->name('processing.index');
        Route::get('/processing/rows',        [ProcessingController::class, 'rows'])->name('processing.rows');
        // Defensive: if a stale browser form posts to /processing itself, bounce
        // back to the list with a friendly message instead of a 405.
        Route::post('/processing', fn () => redirect()->route('processing.index')->with('error', 'Use the action buttons (Send to courier &amp; print labels).'));
        Route::post('/processing/bulk-pack',  [ProcessingController::class, 'bulkPack'])->name('processing.bulk-pack');
        Route::post('/processing/{order}/start-packing', [ProcessingController::class, 'startPacking'])->name('processing.start-packing');

        Route::get('/packing',                     [PackingController::class, 'index'])->name('packing.index');
        Route::get('/packing/rows',                [PackingController::class, 'rows'])->name('packing.rows');
        Route::post('/packing/{order}/mark-packed',[PackingController::class, 'markPacked'])->name('packing.mark-packed');
        Route::post('/packing/bulk-mark-packed',   [PackingController::class, 'bulkMarkPacked'])->name('packing.bulk-mark-packed');

        Route::post('/scan/pack',             [ScanController::class, 'pack'])->name('scan.pack');

        Route::get('/labels/{order}',         [LabelController::class, 'single'])->name('labels.single');
        Route::get('/labels',                 [LabelController::class, 'batch'])->name('labels.batch');
    });

    // ── Dispatch & Tracking ───────────────────────────────────────────
    Route::middleware('role:dispatcher,admin')->group(function () {
        Route::get('/dispatch',                   [DispatchController::class, 'index'])->name('dispatch.index');
        Route::get('/dispatch/rows',              [DispatchController::class, 'rows'])->name('dispatch.rows');
        Route::post('/dispatch/handover/{order}', [DispatchController::class, 'handover'])->name('dispatch.handover');
        Route::post('/dispatch/bulk-handover',    [DispatchController::class, 'bulkHandover'])->name('dispatch.bulk-handover');
        Route::get('/dispatch/csv',               [DispatchController::class, 'exportCsv'])->name('dispatch.csv');
        Route::post('/scan/dispatch',             [ScanController::class, 'dispatch'])->name('scan.dispatch');
        Route::get('/tracking',                   [TrackingController::class, 'index'])->name('tracking.index');
        Route::get('/tracking/rows',              [TrackingController::class, 'rows'])->name('tracking.rows');
        Route::get('/tracking/{consignment}',     [TrackingController::class, 'show'])->name('tracking.show');
    });

    // ── Returns / Exchanges / Damages / Lost ─────────────────────────
    Route::middleware('role:returns_clerk,admin')->group(function () {
        Route::get('/returns',                    [ReturnsController::class, 'index'])->name('returns.index');
        Route::get('/returns/rows',               [ReturnsController::class, 'rows'])->name('returns.rows');
        Route::post('/returns/start',             [ReturnsController::class, 'start'])->name('returns.start');
        Route::post('/returns/{order}/receive',   [ReturnsController::class, 'receive'])->name('returns.receive');
        Route::post('/returns/{order}/inspect',   [ReturnsController::class, 'inspect'])->name('returns.inspect');
        Route::post('/scan/return',               [ScanController::class, 'returnIntake'])->name('scan.return');
    });
    Route::middleware('role:returns_clerk,customer_support,admin')->group(function () {
        Route::get('/exchanges',                  [ExchangeController::class, 'index'])->name('exchanges.index');
        Route::get('/exchanges/{exchange}',       [ExchangeController::class, 'show'])->name('exchanges.show');
        Route::get('/orders/{order}/exchange/new',            [ExchangeController::class, 'create'])->name('exchanges.create');
        Route::get('/orders/{order}/exchange/product-search', [ExchangeController::class, 'productSearch'])->name('exchanges.product-search');
        Route::post('/orders/{order}/exchange',   [ExchangeController::class, 'open'])->name('exchanges.open');
        Route::post('/exchanges/{exchange}/complete', [ExchangeController::class, 'complete'])->name('exchanges.complete');
        Route::post('/exchanges/{exchange}/cancel',   [ExchangeController::class, 'cancel'])->name('exchanges.cancel');
    });
    Route::middleware('role:damage_clerk,returns_clerk,picker,packer,admin')->group(function () {
        Route::get('/damages',         [DamageController::class, 'index'])->name('damages.index');
        Route::get('/damages/create',  [DamageController::class, 'create'])->name('damages.create');
        Route::post('/damages',        [DamageController::class, 'store'])->name('damages.store');
    });
    Route::middleware('role:warehouse_admin,dispatcher,admin')->group(function () {
        Route::get('/lost',         [LostController::class, 'index'])->name('lost.index');
        Route::get('/lost/create',  [LostController::class, 'create'])->name('lost.create');
        Route::post('/lost',        [LostController::class, 'store'])->name('lost.store');
    });

    // ── Inventory + Stock count ──────────────────────────────────────
    Route::middleware('role:inventory_admin,stock_counter,admin')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
        Route::get('/stock-counts/create', [StockCountController::class, 'create'])->name('stock-counts.create');
        Route::post('/stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
        Route::get('/stock-counts/{count}', [StockCountController::class, 'show'])->name('stock-counts.show');
        Route::post('/stock-counts/{count}/line', [StockCountController::class, 'addLine'])->name('stock-counts.line');
        Route::post('/stock-counts/{count}/complete', [StockCountController::class, 'complete'])->name('stock-counts.complete');
    });

    // Customers + carts + reports
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/carts', [CartsController::class, 'index'])->name('carts.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Users
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Profile
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
