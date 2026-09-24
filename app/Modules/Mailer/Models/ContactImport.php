<?php

namespace App\Modules\Mailer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One audit row per extracted address (TOC-LOG-002). */
class ContactImport extends Model
{
    public const OUTCOME_ADDED = 'added';

    public const OUTCOME_UPDATED = 'updated';

    public const OUTCOME_SKIPPED = 'skipped';

    public const OUTCOME_FAILED = 'failed';

    public const UPDATED_AT = null;

    protected $table = 'mailer_contact_imports';

    protected $guarded = [];

    protected $casts = ['fields' => 'array'];

    /** @return BelongsTo<ApprovedSender, $this> */
    public function approvedSender(): BelongsTo
    {
        return $this->belongsTo(ApprovedSender::class, 'approved_sender_id');
    }
}
