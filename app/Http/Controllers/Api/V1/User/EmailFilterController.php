<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\EmailFilter;
use App\Models\SpamSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class EmailFilterController extends Controller
{
    /**
     * List email filters for the authenticated user
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $filters = EmailFilter::where('account_id', $account->id)
            ->orderBy('priority')
            ->get();

        return $this->success($filters);
    }

    /**
     * Create a new email filter
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email_account' => 'nullable|email',
            'priority' => 'integer|min:0',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|in:' . implode(',', array_keys(EmailFilter::conditionFields())),
            'conditions.*.match' => 'required|in:' . implode(',', array_keys(EmailFilter::matchTypes())),
            'conditions.*.value' => 'required|string',
            'actions' => 'required|array|min:1',
            'actions.*.action' => 'required|in:' . implode(',', array_keys(EmailFilter::availableActions())),
            'actions.*.destination' => 'nullable|string',
            'stop_processing' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $filter = EmailFilter::create([
            'account_id' => $account->id,
            'email_account' => $request->email_account,
            'name' => $request->name,
            'priority' => $request->input('priority', 0),
            'conditions' => $request->conditions,
            'actions' => $request->actions,
            'stop_processing' => $request->boolean('stop_processing'),
        ]);

        $this->syncSieveFilters($account);

        return $this->success($filter, 'Email filter created');
    }

    /**
     * Show a specific email filter
     */
    public function show(Request $request, EmailFilter $emailFilter)
    {
        $account = $request->user()->account;

        if ($emailFilter->account_id !== $account->id) {
            return $this->error('Filter not found', 404);
        }

        return $this->success($emailFilter);
    }

    /**
     * Update an email filter
     */
    public function update(Request $request, EmailFilter $emailFilter)
    {
        $account = $request->user()->account;

        if ($emailFilter->account_id !== $account->id) {
            return $this->error('Filter not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email_account' => 'nullable|email',
            'priority' => 'integer|min:0',
            'conditions' => 'array|min:1',
            'conditions.*.field' => 'in:' . implode(',', array_keys(EmailFilter::conditionFields())),
            'conditions.*.match' => 'in:' . implode(',', array_keys(EmailFilter::matchTypes())),
            'conditions.*.value' => 'string',
            'actions' => 'array|min:1',
            'actions.*.action' => 'in:' . implode(',', array_keys(EmailFilter::availableActions())),
            'actions.*.destination' => 'nullable|string',
            'stop_processing' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $emailFilter->update($request->only([
            'name',
            'email_account',
            'priority',
            'conditions',
            'actions',
            'stop_processing',
            'is_active',
        ]));

        $this->syncSieveFilters($account);

        return $this->success($emailFilter, 'Email filter updated');
    }

    /**
     * Delete an email filter
     */
    public function destroy(Request $request, EmailFilter $emailFilter)
    {
        $account = $request->user()->account;

        if ($emailFilter->account_id !== $account->id) {
            return $this->error('Filter not found', 404);
        }

        $emailFilter->delete();
        $this->syncSieveFilters($account);

        return $this->success(null, 'Email filter deleted');
    }

    /**
     * Toggle filter active state
     */
    public function toggle(Request $request, EmailFilter $emailFilter)
    {
        $account = $request->user()->account;

        if ($emailFilter->account_id !== $account->id) {
            return $this->error('Filter not found', 404);
        }

        $emailFilter->update(['is_active' => !$emailFilter->is_active]);
        $this->syncSieveFilters($account);

        $status = $emailFilter->is_active ? 'enabled' : 'disabled';
        return $this->success($emailFilter, "Filter {$status}");
    }

    /**
     * Reorder filters
     */
    public function reorder(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'filters' => 'required|array',
            'filters.*.id' => 'required|exists:email_filters,id',
            'filters.*.priority' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        foreach ($request->filters as $filterData) {
            EmailFilter::where('id', $filterData['id'])
                ->where('account_id', $account->id)
                ->update(['priority' => $filterData['priority']]);
        }

        $this->syncSieveFilters($account);

        return $this->success(null, 'Filter order updated');
    }

    /**
     * Get available condition fields and match types
     */
    public function options()
    {
        return $this->success([
            'fields' => EmailFilter::conditionFields(),
            'match_types' => EmailFilter::matchTypes(),
            'actions' => EmailFilter::availableActions(),
        ]);
    }

    /**
     * Get spam settings
     */
    public function getSpamSettings(Request $request)
    {
        $account = $request->user()->account;

        $settings = SpamSettings::firstOrCreate(
            ['account_id' => $account->id],
            [
                'spam_filter_enabled' => true,
                'spam_threshold' => 5,
                'auto_delete_spam' => false,
                'auto_delete_score' => 10,
                'spam_box_enabled' => true,
            ]
        );

        return $this->success([
            'settings' => $settings,
            'threshold_levels' => SpamSettings::thresholdLevels(),
        ]);
    }

    /**
     * Update spam settings
     */
    public function updateSpamSettings(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'spam_filter_enabled' => 'boolean',
            'spam_threshold' => 'integer|min:1|max:10',
            'auto_delete_spam' => 'boolean',
            'auto_delete_score' => 'integer|min:1|max:20',
            'spam_box_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $settings = SpamSettings::updateOrCreate(
            ['account_id' => $account->id],
            $request->only([
                'spam_filter_enabled',
                'spam_threshold',
                'auto_delete_spam',
                'auto_delete_score',
                'spam_box_enabled',
            ])
        );

        $this->syncSpamAssassinConfig($account, $settings);

        return $this->success($settings, 'Spam settings updated');
    }

    /**
     * Get spam whitelist
     */
    public function getWhitelist(Request $request)
    {
        $account = $request->user()->account;
        $settings = SpamSettings::where('account_id', $account->id)->first();

        return $this->success([
            'whitelist' => $settings->whitelist ?? [],
        ]);
    }

    /**
     * Add to spam whitelist
     */
    public function addToWhitelist(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $settings = SpamSettings::firstOrCreate(['account_id' => $account->id]);
        $settings->addToWhitelist($request->email);

        $this->syncSpamAssassinConfig($account, $settings);

        return $this->success(['whitelist' => $settings->whitelist], 'Address whitelisted');
    }

    /**
     * Remove from spam whitelist
     */
    public function removeFromWhitelist(Request $request, string $email)
    {
        $account = $request->user()->account;

        $settings = SpamSettings::where('account_id', $account->id)->first();

        if ($settings) {
            $settings->removeFromWhitelist($email);
            $this->syncSpamAssassinConfig($account, $settings);
        }

        return $this->success(null, 'Address removed from whitelist');
    }

    /**
     * Get spam blacklist
     */
    public function getBlacklist(Request $request)
    {
        $account = $request->user()->account;
        $settings = SpamSettings::where('account_id', $account->id)->first();

        return $this->success([
            'blacklist' => $settings->blacklist ?? [],
        ]);
    }

    /**
     * Add to spam blacklist
     */
    public function addToBlacklist(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $settings = SpamSettings::firstOrCreate(['account_id' => $account->id]);
        $settings->addToBlacklist($request->email);

        $this->syncSpamAssassinConfig($account, $settings);

        return $this->success(['blacklist' => $settings->blacklist], 'Address blacklisted');
    }

    /**
     * Remove from spam blacklist
     */
    public function removeFromBlacklist(Request $request, string $email)
    {
        $account = $request->user()->account;

        $settings = SpamSettings::where('account_id', $account->id)->first();

        if ($settings) {
            $settings->removeFromBlacklist($email);
            $this->syncSpamAssassinConfig($account, $settings);
        }

        return $this->success(null, 'Address removed from blacklist');
    }

    /**
     * Sync Sieve filters to the mail server
     */
    protected function syncSieveFilters($account): void
    {
        $filters = EmailFilter::where('account_id', $account->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $sieveScript = "require [\"fileinto\", \"reject\", \"vacation\", \"copy\", \"imap4flags\"];\n\n";

        foreach ($filters as $filter) {
            $sieveScript .= $filter->toSieve() . "\n";
        }

        // Write sieve script
        $sievePath = "/var/mail/vhosts/{$account->domain}/{$account->username}/.dovecot.sieve";
        $tempFile = tempnam('/tmp', 'sieve_');
        file_put_contents($tempFile, $sieveScript);

        Process::run("sudo mv {$tempFile} {$sievePath}");
        Process::run("sudo chown vmail:vmail {$sievePath}");
        Process::run("sudo chmod 644 {$sievePath}");

        // Compile sieve script
        Process::run("sudo sievec {$sievePath}");
    }

    /**
     * Sync SpamAssassin config for account
     */
    protected function syncSpamAssassinConfig($account, SpamSettings $settings): void
    {
        $configPath = "/etc/spamassassin/users/{$account->username}.cf";
        $config = $settings->toSpamAssassinConfig();

        $tempFile = tempnam('/tmp', 'sa_');
        file_put_contents($tempFile, $config);

        Process::run("sudo mkdir -p /etc/spamassassin/users");
        Process::run("sudo mv {$tempFile} {$configPath}");
        Process::run("sudo chmod 644 {$configPath}");
    }
}
