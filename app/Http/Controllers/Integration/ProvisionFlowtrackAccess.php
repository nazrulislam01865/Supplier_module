<?php

namespace App\Http\Controllers\Integration;

use App\Models\SupplierCompany;
use App\Models\SupplierUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Admin-only machine endpoint: an account never grants access to a different supplier. */
final class ProvisionFlowtrackAccess extends Controller
{
    use VerifiesFlowtrack;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->signedData($request);
        if ($data === null) return response()->json(['message' => 'Unauthorized.'], 401);
        $validated = Validator::make($data, [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'username' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]+$/', 'min:3', 'max:60'],
            'email' => ['required', 'email', 'max:254'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', Password::min(8)],
            'require_password_change' => ['sometimes', 'boolean'],
        ]);
        if ($validated->fails()) return response()->json(['message' => 'Invalid supplier credentials.'], 422);
        $email = strtolower(trim($data['email']));
        $username = strtolower(trim($data['username']));
        $company = SupplierCompany::query()->where('flowtrack_supplier_id', $data['supplier_id'])->where('active', true)->first();
        if (! $company) return response()->json(['message' => 'Supplier is not synchronized or inactive.'], 409);

        $result = DB::transaction(function () use ($company, $data, $email, $username): bool {
            $user = SupplierUser::query()->where('supplier_company_id', $company->id)
                ->where('role', 'admin')->lockForUpdate()->orderBy('id')->first();
            $collides = SupplierUser::query()->where(function ($query) use ($email, $username): void {
                $query->where('email', $email)->orWhere('username', $username);
            })->when($user, fn ($query) => $query->where('id', '!=', $user->id))->exists();
            if ($collides || (! $user && ! isset($data['password']))) return false;
            $values = [
                'name' => $data['name'], 'email' => $email, 'username' => $username,
                'active' => true,
            ];
            if (isset($data['password'])) {
                $values['password'] = Hash::make($data['password']);
                $values['must_change_password'] = (bool) ($data['require_password_change'] ?? false);
                $values['remember_token'] = null;
            }
            if ($user) {
                $user->forceFill($values)->save();
                if (isset($data['password'])) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            } else {
                $company->users()->create($values + ['role' => 'admin']);
            }
            return true;
        });
        return $result ? response()->json(['provisioned' => true, 'supplier_id' => (int) $data['supplier_id']])
            : response()->json(['message' => 'Credential already belongs to another account.'], 409);
    }
}
