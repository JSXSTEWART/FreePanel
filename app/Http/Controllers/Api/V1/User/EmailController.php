<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\EmailAutoresponder;
use App\Services\Email\EmailInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    protected EmailInterface $email;

    public function __construct(EmailInterface $email)
    {
        $this->email = $email;
    }

    // Email Accounts
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $emails = EmailAccount::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->with('domain:id,name')
            ->get()
            ->map(function ($email) {
                $email->address = $email->username . '@' . $email->domain->name;
                $email->usage_percent = $email->quota > 0
                    ? round(($email->quota_used / $email->quota) * 100, 1)
                    : 0;
                return $email;
            });

        return $this->success($emails);
    }

    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|integer|exists:domains,id',
            'username' => 'required|string|max:64|regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/',
            'password' => 'required|string|min:8',
            'quota' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Verify domain ownership
        $domain = Domain::where('account_id', $account->id)->findOrFail($request->domain_id);

        // Check email quota
        $currentEmails = EmailAccount::whereHas('domain', fn($q) => $q->where('account_id', $account->id))->count();
        if ($account->package->max_email_accounts != -1 && $currentEmails >= $account->package->max_email_accounts) {
            return $this->error('Email account quota exceeded', 403);
        }

        // Check uniqueness
        $username = strtolower($request->username);
        if (EmailAccount::where('domain_id', $domain->id)->where('username', $username)->exists()) {
            return $this->error('Email account already exists', 422);
        }

        DB::beginTransaction();
        try {
            $emailAccount = EmailAccount::create([
                'domain_id' => $domain->id,
                'username' => $username,
                'password' => Hash::make($request->password),
                'quota' => $request->quota ?? 1024, // Default 1GB in MB
            ]);

            // Create mailbox
            $this->email->createMailbox($emailAccount, $request->password);

            DB::commit();

            $emailAccount->address = $username . '@' . $domain->name;
            return $this->success($emailAccount, 'Email account created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create email account: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $email = EmailAccount::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->with('domain:id,name')
            ->findOrFail($id);

        $email->address = $email->username . '@' . $email->domain->name;
        return $this->success($email);
    }

    public function update(Request $request, int $id)
    {
        $account = $request->user()->account;

        $email = EmailAccount::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:8',
            'quota' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $updates = [];

            if ($request->has('quota')) {
                $updates['quota'] = $request->quota;
                $this->email->updateQuota($email, $request->quota);
            }

            if ($request->filled('password')) {
                $updates['password'] = Hash::make($request->password);
                $this->email->updatePassword($email, $request->password);
            }

            $email->update($updates);

            DB::commit();
            return $this->success($email, 'Email account updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update email account: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;

        $email = EmailAccount::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            $this->email->deleteMailbox($email);
            $email->delete();

            DB::commit();
            return $this->success(null, 'Email account deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete email account: ' . $e->getMessage(), 500);
        }
    }

    // Forwarders
    public function forwarders(Request $request)
    {
        $account = $request->user()->account;

        $forwarders = EmailForwarder::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->with('domain:id,name')
            ->get();

        return $this->success($forwarders);
    }

    public function storeForwarder(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|integer|exists:domains,id',
            'source' => 'required|string|max:64',
            'destination' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $domain = Domain::where('account_id', $account->id)->findOrFail($request->domain_id);

        DB::beginTransaction();
        try {
            $forwarder = EmailForwarder::create([
                'domain_id' => $domain->id,
                'source' => strtolower($request->source),
                'destination' => strtolower($request->destination),
            ]);

            $this->email->createForwarder($forwarder);

            DB::commit();
            return $this->success($forwarder, 'Email forwarder created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create forwarder: ' . $e->getMessage(), 500);
        }
    }

    public function destroyForwarder(Request $request, int $id)
    {
        $account = $request->user()->account;

        $forwarder = EmailForwarder::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            $this->email->deleteForwarder($forwarder);
            $forwarder->delete();

            DB::commit();
            return $this->success(null, 'Email forwarder deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete forwarder: ' . $e->getMessage(), 500);
        }
    }

    // Autoresponders
    public function autoresponders(Request $request)
    {
        $account = $request->user()->account;

        $autoresponders = EmailAutoresponder::whereHas('emailAccount.domain', fn($q) => $q->where('account_id', $account->id))
            ->with('emailAccount.domain:id,name')
            ->get();

        return $this->success($autoresponders);
    }

    public function storeAutoresponder(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'email_account_id' => 'required|integer|exists:email_accounts,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:65535',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $emailAccount = EmailAccount::whereHas('domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($request->email_account_id);

        DB::beginTransaction();
        try {
            $autoresponder = EmailAutoresponder::create([
                'email_account_id' => $emailAccount->id,
                'subject' => $request->subject,
                'body' => $request->body,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'is_active' => true,
            ]);

            $this->email->createAutoresponder($autoresponder);

            DB::commit();
            return $this->success($autoresponder, 'Autoresponder created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create autoresponder: ' . $e->getMessage(), 500);
        }
    }

    public function updateAutoresponder(Request $request, int $id)
    {
        $account = $request->user()->account;

        $autoresponder = EmailAutoresponder::whereHas('emailAccount.domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:65535',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $autoresponder->update($request->only(['subject', 'body', 'start_time', 'end_time', 'is_active']));
            $this->email->updateAutoresponder($autoresponder);

            DB::commit();
            return $this->success($autoresponder, 'Autoresponder updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update autoresponder: ' . $e->getMessage(), 500);
        }
    }

    public function destroyAutoresponder(Request $request, int $id)
    {
        $account = $request->user()->account;

        $autoresponder = EmailAutoresponder::whereHas('emailAccount.domain', fn($q) => $q->where('account_id', $account->id))
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            $this->email->deleteAutoresponder($autoresponder);
            $autoresponder->delete();

            DB::commit();
            return $this->success(null, 'Autoresponder deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete autoresponder: ' . $e->getMessage(), 500);
        }
    }
}
