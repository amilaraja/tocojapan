<?php

namespace App\Modules\Mailer\Models;

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

    /** @return HasMany<ContactImport, $this> */
    public function contactImports(): HasMany
    {
        return $this->hasMany(ContactImport::class, 'approved_sender_id');
    }
}
