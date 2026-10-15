<?php

namespace App\Http\Controllers\Integration;

use App\Models\SupplierCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/** Issue a one-use reset token by email; never include it in the API response. */
final class SendFlowtrackPasswordReset extends Controller
{
    use VerifiesFlowtrack;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->signedData($request);
        if ($data === null) return response()->json(['message' => 'Unauthorized.'], 401);
        if (Validator::make($data, ['supplier_id' => ['required', 'integer', 'min:1']])->fails()) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }
        $company = SupplierCompany::query()
            ->where('flowtrack_supplier_id', $data['supplier_id'])->where('active', true)->first();
        $user = $company?->users()->where('role', 'admin')->where('active', true)->orderBy('id')->first();
        if (! $user) return response()->json(['message' => 'Supplier account is unavailable.'], 409);

        $status = Password::broker('supplier_users')->sendResetLink(['email' => $user->email]);
        if ($status === Password::RESET_THROTTLED) return response()->json(['message' => 'Too many reset requests.'], 429);
        if ($status !== Password::RESET_LINK_SENT) return response()->json(['message' => 'Unable to send reset link.'], 503);
        return response()->json(['sent' => true]);
    }
}
