<?php
// Read-only report of one locally persisted supplier order snapshot.
// Run: php scripts/diagnose-received-order.php ORDER-2026-00455 34785
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$reference = (string) ($argv[1] ?? '');
$supplierId = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($reference === '' || $supplierId === false) { fwrite(STDERR, "Usage: php scripts/diagnose-received-order.php ORDER-2026-00455 34785\n"); exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$company = \App\Models\SupplierCompany::query()->where('flowtrack_supplier_id', $supplierId)->first();
$order = $company ? \App\Models\SupplierResource::query()
    ->where('supplier_company_id', $company->id)->where('type', 'order')
    ->where('external_reference', $reference)->first() : null;
if (! $order) { fwrite(STDERR, "No received order for that company/reference.\n"); exit(2); }
$payload = (array) $order->payload;
$artwork = array_map(static fn (array $file) => [
    'id' => $file['id'] ?? null, 'size' => $file['size'] ?? null,
    'version' => $file['version'] ?? null,
], $payload['documents'] ?? []);
$stages = array_values(array_filter($payload['workflow']['stages'] ?? [],
    static fn (array $stage) => (int) ($stage['sequence'] ?? 0) === 2));
$tasks = [];
foreach ($stages as $stage) foreach ($stage['tasks'] ?? [] as $task) $tasks[] = [
    'id' => $task['id'] ?? null, 'status' => $task['status'] ?? null,
    'mode' => $task['mode'] ?? null, 'assigned_to_supplier' => $task['assigned_to_supplier'] ?? null,
    'can_act' => $task['can_act'] ?? null, 'action_key' => $task['action_key'] ?? null,
];
echo json_encode([
    'reference' => $order->external_reference, 'resource_status' => $order->status,
    'resource_updated_at' => (string) $order->updated_at,
    'payload_revision' => $payload['revision'] ?? null,
    'source_id' => $payload['source_id'] ?? null,
    'artwork_document_count' => count($artwork),
    'artwork' => $artwork,
    'artwork_tasks' => $tasks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
