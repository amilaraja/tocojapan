<?php

namespace App\Modules\Mailer\Models;

use App\Modules\Mailer\Domain\Importer\SenderMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovedSender extends Model
{
    public const MATCH_ADDRESS = 'address';

    public const MATCH_DOMAIN = 'domain';

    public const CONSENT_DIRECT = 'direct';

    public const CONSENT_CONFIRM = 'confirm';

    protected $table = 'mailer_approved_senders';

    protected $guarded = [];

    protected $casts = [
        'active' => 'bool',
        'use_reply_to' => 'bool',
        'brevo_list_ids' => 'array',
        'field_rules' => 'array',
        'max_per_message' => 'integer',
        'doi_template_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // match_type always follows the value: "@domain" or an exact address.
        static::saving(function (ApprovedSender $sender): void {
            $sender->match_value = strtolower(trim((string) $sender->match_value));
            $sender->match_type = SenderMatcher::typeOf($sender->match_value) ?? self::MATCH_ADDRESS;
        });
    }

    /** @return HasMany<ContactImport, $this> */
    public function contactImports(): HasMany
    {
        return $this->hasMany(ContactImport::class, 'approved_sender_id');
    }
}
