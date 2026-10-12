<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A website record (quote, proforma, order, enquiry) linked to a Case (CRM-CAS-005). */
class CrmCaseLink extends Model
{
    protected $table = 'crm_case_links';

    protected $guarded = [];

    /** @return BelongsTo<CrmCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CrmCase::class, 'case_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
