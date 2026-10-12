<a href="{{ \App\Modules\Crm\Filament\Resources\Cases\CaseResource::getUrl('view', ['record' => $case]) }}"
   style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;background:rgba(31,35,86,.1);border:1px solid rgba(31,35,86,.3)">
    CRM Case {{ $case->reference }} · {{ $case->progressLabel() }}
</a>
