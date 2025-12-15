<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DnsZone;
use App\Models\DnsRecord;
use App\Services\Dns\DnsInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DnsController extends Controller
{
    protected DnsInterface $dns;

    public function __construct(DnsInterface $dns)
    {
        $this->dns = $dns;
    }

    public function zones(Request $request)
    {
        $account = $request->user()->account;

        $zones = DnsZone::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->with('domain')
            ->get();

        return $this->success($zones);
    }

    public function records(Request $request, int $zoneId)
    {
        $account = $request->user()->account;

        $zone = DnsZone::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($zoneId);

        return $this->success($zone->records);
    }

    public function storeRecord(Request $request, int $zoneId)
    {
        $account = $request->user()->account;

        $zone = DnsZone::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($zoneId);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA',
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:65535',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate record type specific rules
        $validationError = $this->validateRecordContent($request->type, $request->content);
        if ($validationError) {
            return $this->error($validationError, 422);
        }

        DB::beginTransaction();
        try {
            $record = DnsRecord::create([
                'dns_zone_id' => $zone->id,
                'type' => $request->type,
                'name' => $request->name,
                'content' => $request->content,
                'ttl' => $request->ttl ?? 3600,
                'priority' => $request->priority,
            ]);

            // Update zone file
            $this->dns->addRecord($zone, $record->toArray());

            DB::commit();
            return $this->success($record, 'DNS record created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create DNS record: ' . $e->getMessage(), 500);
        }
    }

    public function updateRecord(Request $request, int $zoneId, int $recordId)
    {
        $account = $request->user()->account;

        $zone = DnsZone::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($zoneId);

        $record = DnsRecord::where('dns_zone_id', $zone->id)->findOrFail($recordId);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:65535',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $validationError = $this->validateRecordContent($record->type, $request->content);
        if ($validationError) {
            return $this->error($validationError, 422);
        }

        DB::beginTransaction();
        try {
            $oldRecord = $record->toArray();

            $record->update([
                'content' => $request->content,
                'ttl' => $request->ttl ?? $record->ttl,
                'priority' => $request->priority ?? $record->priority,
            ]);

            // Update zone file
            $this->dns->updateRecord($zone, $oldRecord, $record->toArray());

            DB::commit();
            return $this->success($record, 'DNS record updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update DNS record: ' . $e->getMessage(), 500);
        }
    }

    public function destroyRecord(Request $request, int $zoneId, int $recordId)
    {
        $account = $request->user()->account;

        $zone = DnsZone::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($zoneId);

        $record = DnsRecord::where('dns_zone_id', $zone->id)->findOrFail($recordId);

        // Prevent deletion of essential records
        if ($record->type === 'SOA' || ($record->type === 'NS' && $record->name === '@')) {
            return $this->error('Cannot delete essential DNS records', 403);
        }

        DB::beginTransaction();
        try {
            $this->dns->removeRecord($zone, $record->name, $record->type);
            $record->delete();

            DB::commit();
            return $this->success(null, 'DNS record deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete DNS record: ' . $e->getMessage(), 500);
        }
    }

    public function resetZone(Request $request, int $zoneId)
    {
        $account = $request->user()->account;

        $zone = DnsZone::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($zoneId);

        DB::beginTransaction();
        try {
            // Delete all non-essential records
            DnsRecord::where('dns_zone_id', $zone->id)
                ->where('type', '!=', 'SOA')
                ->whereNot(fn($q) => $q->where('type', 'NS')->where('name', '@'))
                ->delete();

            // Recreate default records
            $this->dns->resetZone($zone);

            DB::commit();
            return $this->success($zone->fresh()->load('records'), 'DNS zone reset successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to reset DNS zone: ' . $e->getMessage(), 500);
        }
    }

    protected function validateRecordContent(string $type, string $content): ?string
    {
        switch ($type) {
            case 'A':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return 'Invalid IPv4 address';
                }
                break;
            case 'AAAA':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return 'Invalid IPv6 address';
                }
                break;
            case 'CNAME':
            case 'NS':
            case 'MX':
                if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.?$/i', $content)) {
                    return 'Invalid hostname';
                }
                break;
        }
        return null;
    }
}
