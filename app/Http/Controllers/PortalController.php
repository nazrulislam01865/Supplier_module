<?php
namespace App\Http\Controllers;

use App\Models\SupplierResource;
use App\Services\SupplierDashboardData;
use App\Services\SupplierOrderListing;
use App\Services\SupplierScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function dashboardRevision(Request $request): JsonResponse
    {
        return response()->json(['revision' => (string) $request->user()->company->dashboard_revision])
            ->header('Cache-Control', 'private, no-store');
    }

    public function dashboardRefresh(Request $request, SupplierDashboardData $data): JsonResponse
    {
        $dashboard = $data->forUser($request->user());
        $props = $dashboard + ['user' => $request->user()];
        return response()->json([
            'revision' => (string) $request->user()->company->dashboard_revision,
            'branding' => [
                'companyName' => (string) $request->user()->company->name,
                'name' => (string) ($request->user()->company->flowtrack_brand_name ?: 'FlowTrack'),
                'logoUrl' => $request->user()->company->flowtrack_brand_logo_url,
                'faviconUrl' => $request->user()->company->flowtrack_brand_favicon_url,
            ],
            'dashboard' => view('portal.partials.dashboard', $props)->render(),
            'notifications' => view('portal.partials.notifications', $props)->render(),
            'unread' => $dashboard['unreadNotifications'],
            'rfqActionCount' => $dashboard['rfqActionCount'],
            'rfqs' => $this->quotations($request->user()->supplier_company_id),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Only the notification center uses this paginated endpoint; the bell preview stays lightweight. */
    public function notificationCenter(Request $request, SupplierDashboardData $data): JsonResponse
    {
        [$notificationType, $notificationState] = $this->notificationFilters($request);
        $notificationPage = $data->notificationPage($request->user(), $notificationType, $notificationState);

        return response()->json([
            'html' => view('portal.partials.notification-center', [
                'notificationPage' => $notificationPage,
                'notificationType' => $notificationType,
                'notificationState' => $notificationState,
                'user' => $request->user(),
            ])->render(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function notificationFilters(Request $request): array
    {
        $type = $request->query('notification_type');
        $state = $request->query('notification_state');
        return [
            in_array($type, ['order', 'rfq'], true) ? $type : 'all',
            $state === 'unread' ? 'unread' : 'all',
        ];
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->forceFill(['notifications_read_at' => now()])->save();
        return response()->json(['unread' => 0])->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request, SupplierDashboardData $data, SupplierOrderListing $orderListing)
    {
        $user = $request->user();
        abort_unless($user->active && $user->company?->active, 403);
        // Select the requested workspace on the server, even if a browser script fails.
        $requestedView = $request->query('view', 'dashboard');
        $initialView = in_array($requestedView, ['dashboard', 'orders', 'quotations', 'profile', 'notifications', 'help'], true)
            ? $requestedView : 'dashboard';
        if ($request->has('orders_page')) $initialView = 'orders';
        if ($request->has('notifications_page')) $initialView = 'notifications';
        $company = $user->company;
        $quotations = $this->quotations($company->id);
        $dashboard = $data->forUser($user);
        [$notificationType, $notificationState] = $this->notificationFilters($request);
        $notificationPage = $initialView === 'notifications'
            ? $data->notificationPage($user, $notificationType, $notificationState) : null;
        $realtimeConfig = [
            'url' => route('supplier.dashboard.refresh'),
            'markReadUrl' => route('supplier.dashboard.notifications.read'),
            'revisionUrl' => route('supplier.dashboard.revision'),
            'authUrl' => route('supplier.dashboard.realtime.auth'),
            'supplierId' => (int) $company->flowtrack_supplier_id,
            'key' => (string) config('supplier_realtime.key'),
            'host' => (string) config('supplier_realtime.host'),
            'port' => (int) config('supplier_realtime.port'),
            'scheme' => (string) config('supplier_realtime.scheme'),
        ];
        $orderListingData = $orderListing->forCompany((int) $company->id, $request, $initialView === 'orders');
        $orderPage = $orderListingData['page'];
        $orders = $orderListingData['rows'];
        $orderFilters = $orderListingData['filters'];
        $orderStages = SupplierOrderListing::STAGES;
        $orderStageCards = $orderListingData['stage_cards'];
        $profile = $company->profile ?? [];
        $maskedBank = $user->role === 'admin' ? ($company->encrypted_banking ?? []) : [];
        return view('portal.index', compact('user','company','quotations','orders','orderPage','profile','maskedBank','dashboard','realtimeConfig','initialView','notificationType','notificationState','notificationPage','orderFilters','orderStages','orderStageCards'));
    }

    /** Load a single older RFQ on demand; never query outside the logged-in company. */
    public function rfqSummary(Request $request, int $id, SupplierScope $scope): JsonResponse
    {
        $rfq = $scope->resource($request, $id, 'rfq');
        $rfq->load('quotation:id,supplier_resource_id,sync_status,sync_attempts');
        return response()->json(['rfq' => $this->quotationRow($rfq)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function quotations(int $companyId): \Illuminate\Support\Collection
    {
        $resources = SupplierResource::query()
            ->where('supplier_company_id', $companyId)
            ->where('type', 'rfq')
            ->with('quotation:id,supplier_resource_id,sync_status,sync_attempts')
            ->orderByRaw("CASE WHEN status IN ('open', 'draft') THEN 0 ELSE 1 END")
            ->orderBy('due_at')->limit(100)->get(['id','external_reference','status','payload','due_at','updated_at']);
        return $resources->map(fn (SupplierResource $resource) => $this->quotationRow($resource))->values();
    }

    private function quotationRow(SupplierResource $resource): array
    {
        $p = $resource->payload ?? [];
        return [
            'id'=>$resource->id,
            'product'=>(string) ($p['product_name'] ?? 'Product'),
            'ref'=>$resource->external_reference,
            'code'=>(string) ($p['product_code'] ?? ''),
            'items' => $p['items'] ?? [],
            'documents' => $p['documents'] ?? [],
            'supplierTask' => $p['supplier_task'] ?? null,
            'message' => (string) ($p['request_message'] ?? ''),
            'supplierDetails' => (string) ($p['supplier_details'] ?? ''),
            'deliveryDate' => (string) ($p['delivery_date'] ?? ''),
            'currency' => (string) ($p['currency'] ?? 'USD'),
            'inquiryRef'=>(string) ($p['inquiry_reference'] ?? ''),
            'requirements'=>(string) ($p['buyer_requirements'] ?? ''),
            'buyer'=>(string) ($p['buyer_label'] ?? 'Buyer'),
            'qty'=>number_format((float) ($p['quantity'] ?? 0), 2, '.', ',').' units',
            'quantity'=>(float) ($p['quantity'] ?? 0),
            'due'=>$resource->due_at?->format('M j, Y') ?? 'Not set',
            'dueShort'=>$resource->due_at?->format('M j') ?? 'Not set',
            'updated'=>$resource->updated_at?->format('M j, H:i') ?? '',
            'updatedIso' => $resource->updated_at?->toIso8601String() ?? '',
            'status'=>$resource->status,
            'syncStatus'=>$resource->quotation?->sync_status,
            'syncAttempts'=>(int) ($resource->quotation?->sync_attempts ?? 0),
            'urgent'=>$resource->due_at && $resource->due_at->isFuture() && $resource->due_at->diffInDays(now()) <= 3,
        ];
    }
}
