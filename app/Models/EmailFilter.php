<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'email_account',
        'name',
        'priority',
        'is_active',
        'conditions',
        'actions',
        'stop_processing',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'conditions' => 'array',
        'actions' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Available condition fields
     */
    public static function conditionFields(): array
    {
        return [
            'from' => 'From Address',
            'to' => 'To Address',
            'subject' => 'Subject',
            'body' => 'Body',
            'headers' => 'Any Header',
            'size' => 'Message Size (KB)',
            'spam_score' => 'Spam Score',
        ];
    }

    /**
     * Available condition match types
     */
    public static function matchTypes(): array
    {
        return [
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'equals' => 'Equals',
            'not_equals' => 'Does Not Equal',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With',
            'regex' => 'Matches Regex',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
        ];
    }

    /**
     * Available actions
     */
    public static function availableActions(): array
    {
        return [
            'deliver' => 'Deliver to Inbox',
            'move' => 'Move to Folder',
            'copy' => 'Copy to Folder',
            'forward' => 'Forward to Address',
            'delete' => 'Delete Message',
            'discard' => 'Discard Silently',
            'mark_read' => 'Mark as Read',
            'mark_spam' => 'Mark as Spam',
            'add_header' => 'Add Header',
            'pipe' => 'Pipe to Program',
        ];
    }

    /**
     * Convert filter to Sieve format
     */
    public function toSieve(): string
    {
        $sieve = "# Filter: {$this->name}\n";
        $sieve .= "if ";

        $conditionParts = [];
        foreach ($this->conditions as $condition) {
            $conditionParts[] = $this->conditionToSieve($condition);
        }

        $sieve .= "allof(\n  " . implode(",\n  ", $conditionParts) . "\n) {\n";

        foreach ($this->actions as $action) {
            $sieve .= "  " . $this->actionToSieve($action) . "\n";
        }

        if ($this->stop_processing) {
            $sieve .= "  stop;\n";
        }

        $sieve .= "}\n";

        return $sieve;
    }

    /**
     * Convert condition to Sieve syntax
     */
    protected function conditionToSieve(array $condition): string
    {
        $field = $condition['field'] ?? '';
        $match = $condition['match'] ?? 'contains';
        $value = $condition['value'] ?? '';

        $headerMap = [
            'from' => 'From',
            'to' => 'To',
            'subject' => 'Subject',
        ];

        $header = $headerMap[$field] ?? $field;

        switch ($match) {
            case 'contains':
                return "header :contains \"{$header}\" \"{$value}\"";
            case 'equals':
                return "header :is \"{$header}\" \"{$value}\"";
            case 'regex':
                return "header :regex \"{$header}\" \"{$value}\"";
            case 'greater_than':
                return "size :over {$value}K";
            case 'less_than':
                return "size :under {$value}K";
            default:
                return "header :contains \"{$header}\" \"{$value}\"";
        }
    }

    /**
     * Convert action to Sieve syntax
     */
    protected function actionToSieve(array $action): string
    {
        $type = $action['action'] ?? '';
        $destination = $action['destination'] ?? '';

        switch ($type) {
            case 'move':
                return "fileinto \"{$destination}\";";
            case 'copy':
                return "fileinto :copy \"{$destination}\";";
            case 'forward':
                return "redirect \"{$destination}\";";
            case 'delete':
            case 'discard':
                return "discard;";
            case 'mark_read':
                return "addflag \"\\\\Seen\";";
            case 'add_header':
                $header = $action['header'] ?? 'X-Custom';
                return "addheader \"{$header}\" \"{$destination}\";";
            default:
                return "keep;";
        }
    }
}
