<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::withCount('accounts');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $packages = $query->orderBy('name')->get();

        return $this->success($packages);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:64|unique:packages,name',
            'disk_quota' => 'required|integer|min:-1',
            'bandwidth' => 'required|integer|min:-1',
            'max_addon_domains' => 'required|integer|min:-1',
            'max_subdomains' => 'required|integer|min:-1',
            'max_email_accounts' => 'required|integer|min:-1',
            'max_databases' => 'required|integer|min:-1',
            'max_ftp_accounts' => 'required|integer|min:-1',
            'max_parked_domains' => 'nullable|integer|min:-1',
            'is_default' => 'nullable|boolean',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // If this is set as default, unset other defaults
        if ($request->is_default) {
            Package::where('is_default', true)->update(['is_default' => false]);
        }

        $package = Package::create([
            'name' => $request->name,
            'disk_quota' => $request->disk_quota,
            'bandwidth' => $request->bandwidth,
            'max_addon_domains' => $request->max_addon_domains,
            'max_subdomains' => $request->max_subdomains,
            'max_email_accounts' => $request->max_email_accounts,
            'max_databases' => $request->max_databases,
            'max_ftp_accounts' => $request->max_ftp_accounts,
            'max_parked_domains' => $request->max_parked_domains ?? 0,
            'is_default' => $request->is_default ?? false,
            'features' => $request->features ?? [],
        ]);

        return $this->success($package, 'Package created successfully', 201);
    }

    public function show(int $id)
    {
        $package = Package::withCount('accounts')->findOrFail($id);

        return $this->success($package);
    }

    public function update(Request $request, int $id)
    {
        $package = Package::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:64|unique:packages,name,' . $id,
            'disk_quota' => 'nullable|integer|min:-1',
            'bandwidth' => 'nullable|integer|min:-1',
            'max_addon_domains' => 'nullable|integer|min:-1',
            'max_subdomains' => 'nullable|integer|min:-1',
            'max_email_accounts' => 'nullable|integer|min:-1',
            'max_databases' => 'nullable|integer|min:-1',
            'max_ftp_accounts' => 'nullable|integer|min:-1',
            'max_parked_domains' => 'nullable|integer|min:-1',
            'is_default' => 'nullable|boolean',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // If setting as default, unset other defaults
        if ($request->is_default && !$package->is_default) {
            Package::where('is_default', true)->update(['is_default' => false]);
        }

        $package->update($request->only([
            'name',
            'disk_quota',
            'bandwidth',
            'max_addon_domains',
            'max_subdomains',
            'max_email_accounts',
            'max_databases',
            'max_ftp_accounts',
            'max_parked_domains',
            'is_default',
            'features',
        ]));

        return $this->success($package, 'Package updated successfully');
    }

    public function destroy(int $id)
    {
        $package = Package::withCount('accounts')->findOrFail($id);

        if ($package->accounts_count > 0) {
            return $this->error('Cannot delete package with active accounts. Move accounts to another package first.', 422);
        }

        if ($package->is_default) {
            return $this->error('Cannot delete the default package. Set another package as default first.', 422);
        }

        $package->delete();

        return $this->success(null, 'Package deleted successfully');
    }

    public function duplicate(int $id)
    {
        $package = Package::findOrFail($id);

        $newPackage = $package->replicate();
        $newPackage->name = $package->name . ' (Copy)';
        $newPackage->is_default = false;
        $newPackage->save();

        return $this->success($newPackage, 'Package duplicated successfully', 201);
    }

    public function setDefault(int $id)
    {
        $package = Package::findOrFail($id);

        // Unset current default
        Package::where('is_default', true)->update(['is_default' => false]);

        // Set new default
        $package->update(['is_default' => true]);

        return $this->success($package, 'Default package updated successfully');
    }

    public function compare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:2',
            'ids.*' => 'integer|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $packages = Package::whereIn('id', $request->ids)->get();

        $comparison = [
            'packages' => $packages->pluck('name'),
            'features' => [
                'disk_quota' => $packages->pluck('disk_quota', 'name'),
                'bandwidth' => $packages->pluck('bandwidth', 'name'),
                'max_addon_domains' => $packages->pluck('max_addon_domains', 'name'),
                'max_subdomains' => $packages->pluck('max_subdomains', 'name'),
                'max_email_accounts' => $packages->pluck('max_email_accounts', 'name'),
                'max_databases' => $packages->pluck('max_databases', 'name'),
                'max_ftp_accounts' => $packages->pluck('max_ftp_accounts', 'name'),
            ],
        ];

        return $this->success($comparison);
    }
}
