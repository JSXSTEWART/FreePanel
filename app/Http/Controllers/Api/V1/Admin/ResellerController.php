<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResellerController extends Controller
{
    public function index(Request $request)
    {
        $query = Reseller::with(['user']);

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('is_active', $request->status === 'active');
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 25), 100);
        $resellers = $query->paginate($perPage);

        // Add computed attributes
        $resellers->getCollection()->transform(function ($reseller) {
            $reseller->account_count = $reseller->account_count;
            $reseller->total_disk_used = $reseller->total_disk_used;
            $reseller->total_bandwidth_used = $reseller->total_bandwidth_used;
            return $reseller;
        });

        return $this->success($resellers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:16|regex:/^[a-z][a-z0-9]*$/|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'max_accounts' => 'required|integer|min:0',
            'disk_limit' => 'required|integer|min:0',
            'bandwidth_limit' => 'required|integer|min:0',
            'nameservers' => 'nullable|array',
            'nameservers.*' => 'string|max:255',
            'branding' => 'nullable|array',
            'branding.company_name' => 'nullable|string|max:255',
            'branding.logo_url' => 'nullable|url',
            'allowed_packages' => 'nullable|array',
            'allowed_packages.*' => 'integer|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Create user with reseller role
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'reseller',
                'is_active' => true,
            ]);

            // Create reseller configuration
            $reseller = Reseller::create([
                'user_id' => $user->id,
                'max_accounts' => $request->max_accounts,
                'disk_limit' => $request->disk_limit,
                'bandwidth_limit' => $request->bandwidth_limit,
                'nameservers' => $request->nameservers ?? [],
                'branding' => $request->branding ?? [],
                'allowed_packages' => $request->allowed_packages ?? [],
            ]);

            DB::commit();

            return $this->success(
                $reseller->load('user'),
                'Reseller created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create reseller: ' . $e->getMessage(), 500);
        }
    }

    public function show(int $id)
    {
        $reseller = Reseller::with(['user'])->findOrFail($id);

        // Add computed attributes
        $reseller->account_count = $reseller->account_count;
        $reseller->total_disk_used = $reseller->total_disk_used;
        $reseller->total_bandwidth_used = $reseller->total_bandwidth_used;
        $reseller->disk_usage_percent = $reseller->disk_usage_percent;
        $reseller->bandwidth_usage_percent = $reseller->bandwidth_usage_percent;

        // Get available packages for this reseller
        if (empty($reseller->allowed_packages)) {
            $reseller->available_packages = Package::all(['id', 'name']);
        } else {
            $reseller->available_packages = Package::whereIn('id', $reseller->allowed_packages)
                ->get(['id', 'name']);
        }

        return $this->success($reseller);
    }

    public function update(Request $request, int $id)
    {
        $reseller = Reseller::with('user')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|unique:users,email,' . $reseller->user_id,
            'max_accounts' => 'nullable|integer|min:0',
            'disk_limit' => 'nullable|integer|min:0',
            'bandwidth_limit' => 'nullable|integer|min:0',
            'nameservers' => 'nullable|array',
            'nameservers.*' => 'string|max:255',
            'branding' => 'nullable|array',
            'allowed_packages' => 'nullable|array',
            'allowed_packages.*' => 'integer|exists:packages,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            // Update user fields
            if ($request->has('email')) {
                $reseller->user->update(['email' => $request->email]);
            }
            if ($request->has('is_active')) {
                $reseller->user->update(['is_active' => $request->is_active]);
            }

            // Update reseller configuration
            $reseller->update($request->only([
                'max_accounts',
                'disk_limit',
                'bandwidth_limit',
                'nameservers',
                'branding',
                'allowed_packages',
            ]));

            DB::commit();

            return $this->success(
                $reseller->fresh()->load('user'),
                'Reseller updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update reseller: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(int $id)
    {
        $reseller = Reseller::with('user')->findOrFail($id);

        // Check if reseller has any customer accounts
        $customerCount = User::where('parent_id', $reseller->user_id)->count();
        if ($customerCount > 0) {
            return $this->error(
                "Cannot delete reseller with {$customerCount} customer account(s). " .
                "Migrate or terminate customer accounts first.",
                422
            );
        }

        DB::beginTransaction();
        try {
            // Delete reseller configuration
            $reseller->delete();

            // Downgrade user to regular user or delete
            $reseller->user->update(['role' => 'user']);

            DB::commit();

            return $this->success(null, 'Reseller deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete reseller: ' . $e->getMessage(), 500);
        }
    }

    public function accounts(Request $request, int $id)
    {
        $reseller = Reseller::findOrFail($id);

        $query = Account::with(['user', 'package'])
            ->whereHas('user', function ($q) use ($reseller) {
                $q->where('parent_id', $reseller->user_id);
            });

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 25), 100);
        $accounts = $query->paginate($perPage);

        return $this->success($accounts);
    }

    public function changePassword(Request $request, int $id)
    {
        $reseller = Reseller::with('user')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $reseller->user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->success(null, 'Password changed successfully');
    }

    public function suspend(int $id)
    {
        $reseller = Reseller::with('user')->findOrFail($id);

        if (!$reseller->user->is_active) {
            return $this->error('Reseller is already suspended', 422);
        }

        DB::beginTransaction();
        try {
            // Suspend the reseller user
            $reseller->user->update(['is_active' => false]);

            // Optionally suspend all customer accounts
            $customerAccounts = Account::whereHas('user', function ($q) use ($reseller) {
                $q->where('parent_id', $reseller->user_id);
            })->get();

            foreach ($customerAccounts as $account) {
                if ($account->status === 'active') {
                    $account->update([
                        'status' => 'suspended',
                        'suspend_reason' => 'Reseller account suspended',
                        'suspended_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return $this->success(null, 'Reseller suspended successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to suspend reseller: ' . $e->getMessage(), 500);
        }
    }

    public function unsuspend(int $id)
    {
        $reseller = Reseller::with('user')->findOrFail($id);

        if ($reseller->user->is_active) {
            return $this->error('Reseller is not suspended', 422);
        }

        $reseller->user->update(['is_active' => true]);

        return $this->success(null, 'Reseller unsuspended successfully');
    }

    public function stats()
    {
        $stats = [
            'total' => Reseller::count(),
            'active' => Reseller::whereHas('user', fn($q) => $q->where('is_active', true))->count(),
            'suspended' => Reseller::whereHas('user', fn($q) => $q->where('is_active', false))->count(),
            'total_disk_allocated' => Reseller::sum('disk_limit'),
            'total_bandwidth_allocated' => Reseller::sum('bandwidth_limit'),
            'total_account_slots' => Reseller::sum('max_accounts'),
        ];

        return $this->success($stats);
    }
}
