<?php

namespace App\Modules\Crm\Domain;

use App\Models\Order;
use App\Models\User;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCaseStep;
use App\Modules\Crm\Models\CrmStageHistory;
use App\Modules\Crm\Models\CrmStepTemplate;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The five TOCO stages and their mini steps (SRS §3, CRM-STG-001..009).
 * A stage advances by itself once its required, applicable steps are done;
 * moving forward by hand is refused while any is open; moving back needs a
 * reason and reopens the later stages' steps.
 */
class StageEngine
{
    /** Start a new Case: stage 01 (vehicle sale) or status New (support). */
    public static function initialize(CrmCase $case): void
    {
        if (! $case->isVehicleSale()) {
            $case->forceFill(['stage' => null, 'status' => $case->status ?: 'new'])->saveQuietly();

            return;
        }

        $case->forceFill(['stage' => 1, 'status' => null, 'stage_entered_at' => now()])->saveQuietly();
        CrmStageHistory::create(['case_id' => $case->id, 'stage' => 1, 'entered_at' => now()]);
        self::syncSteps($case);
    }

    /** Create step rows for every active template (conditions are applied when reading). */
    public static function syncSteps(CrmCase $case): void
    {
        if (! $case->isVehicleSale()) {
            return;
        }
        $existing = $case->steps()->pluck('step_template_id')->all();
        CrmStepTemplate::where('is_active', true)->whereNotIn('id', $existing)->get()
            ->each(fn (CrmStepTemplate $template) => $case->steps()->create(['step_template_id' => $template->id]));
        $case->unsetRelation('steps');
    }

    /** Does this conditional step apply to the Case (CRM-STG-006, CAS-009)? */
    public static function applies(CrmStepTemplate $template, CrmCase $case): bool
    {
        if (! $template->is_active) {
            return false;
        }

        return match ($template->condition) {
            'supplier_stock' => (bool) $case->vehicle?->isSupplierStock(),
            'lc' => $case->payment_mode === 'lc',
            'inspection' => (bool) ($case->destCountry?->pre_inspection_required ?? $case->destPort?->country?->pre_inspection_required ?? false),
            default => true,
        };
    }

    /** Applicable steps in order, optionally of one stage. @return Collection<int, CrmCaseStep> */
    public static function steps(CrmCase $case, ?int $stage = null): Collection
    {
        self::syncSteps($case);
        $case->loadMissing(['steps.template', 'vehicle', 'destCountry', 'destPort.country']);

        return $case->steps
            ->filter(fn (CrmCaseStep $step) => $step->template !== null && self::applies($step->template, $case))
            ->when($stage !== null, fn ($steps) => $steps->filter(fn (CrmCaseStep $step) => $step->template->stage === $stage))
            ->sortBy(fn (CrmCaseStep $step) => [$step->template->stage, $step->template->sort])
            ->values();
    }

    /** Required, applicable steps of a stage that are still open (CRM-STG-003). @return Collection<int, CrmCaseStep> */
    public static function missing(CrmCase $case, int $stage): Collection
    {
        return self::steps($case, $stage)->filter(fn (CrmCaseStep $step) => $step->template->required && ! $step->isDone())->values();
    }

    public static function step(CrmCase $case, string $key): ?CrmCaseStep
    {
        return self::steps($case)->first(fn (CrmCaseStep $step) => $step->template->key === $key);
    }

    /** Data captured on done steps, by step key: ['bl_issued' => ['bl_number' => …]]. @return array<string, array<string, mixed>> */
    public static function stepData(CrmCase $case): array
    {
        if (! $case->isVehicleSale()) {
            return [];
        }

        return $case->steps()->with('template')->whereNotNull('done_at')->get()
            ->filter(fn (CrmCaseStep $step) => $step->template !== null)
            ->mapWithKeys(fn (CrmCaseStep $step) => [$step->template->key => (array) ($step->data ?? [])])
            ->all();
    }

    /**
     * Tick a step. Data fields the step asks for are required (CRM-STG-004).
     * Returns false when the step does not apply, is already done, or the
     * Case is not a vehicle sale. Context: skip_customer (no customer email),
     * from_order (the order screen already changed the order).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    public static function complete(CrmCase $case, string $key, ?User $by = null, array $data = [], bool $auto = false, array $context = []): bool
    {
        if (! $case->isVehicleSale() || ! $case->isActive()) {
            return false;
        }
        $step = self::step($case, $key);
        if ($step === null || $step->isDone()) {
            return false;
        }

        $template = $step->template;
        $data = self::validateData($template, $data, $auto);

        StepEffects::before($case, $key, $data, $by, $auto, $context);

        $step->forceFill([
            'done_at' => now(),
            'done_by' => $by?->id,
            'done_automatically' => $auto,
            'data' => $data ?: null,
        ])->save();

        Timeline::log($case, 'step', ($auto ? 'Done automatically: ' : 'Done: ').$template->label.self::describeData($template, $data),
            ['step' => $key, 'stage' => $template->stage, 'auto' => $auto, 'data' => $data], userId: $by?->id, visibleToCustomer: $template->customer_visible);

        $case->unsetRelation('steps');
        StepEffects::after($case, $key, $data, $by, $auto, $context);

        self::tryAdvance($case, $by, $context);

        return true;
    }

    /** Untick a step (admins and owners, e.g. ticked by mistake). */
    public static function reopen(CrmCase $case, string $key, ?User $by = null): void
    {
        $step = self::step($case, $key);
        if ($step === null || ! $step->isDone()) {
            return;
        }
        $step->forceFill(['done_at' => null, 'done_by' => null, 'done_automatically' => false])->save();
        Timeline::log($case, 'step', 'Reopened: '.$step->template->label, ['step' => $key, 'reopened' => true], userId: $by?->id);
        $case->unsetRelation('steps');
    }

    /** Move on by itself while the current stage is complete (CRM-STG-005). @param array<string, mixed> $context */
    public static function tryAdvance(CrmCase $case, ?User $by = null, array $context = []): void
    {
        for ($guard = 0; $guard < 5 && $case->isActive() && $case->stage !== null; $guard++) {
            if (self::missing($case, $case->stage)->isNotEmpty()) {
                return;
            }
            if ($case->stage >= 5) {
                CaseActions::win($case, $by, $context);

                return;
            }
            self::moveTo($case, $case->stage + 1, $by, null, $context);
        }
    }

    /** "Move to next stage" button: refused with the list of what is missing (CRM-STG-003). */
    public static function advance(CrmCase $case, ?User $by = null): void
    {
        $missing = self::missing($case, (int) $case->stage);
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'stage' => 'Complete: '.$missing->map(fn (CrmCaseStep $step) => $step->template->label)->implode(', '),
            ]);
        }
        self::tryAdvance($case, $by);
    }

    /** Back one stage with a reason; later stages' steps reopen (CRM-STG-007). */
    public static function moveBack(CrmCase $case, string $reason, ?User $by = null): void
    {
        if (! $case->isVehicleSale() || (int) $case->stage <= 1) {
            throw ValidationException::withMessages(['stage' => 'This Case is already at the first stage.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to move a Case back.']);
        }

        $target = $case->stage - 1;
        self::steps($case)
            ->filter(fn (CrmCaseStep $step) => $step->template->stage > $target && $step->isDone())
            ->each(fn (CrmCaseStep $step) => $step->forceFill(['done_at' => null, 'done_by' => null, 'done_automatically' => false])->save());
        $case->unsetRelation('steps');

        self::moveTo($case, $target, $by, $reason, ['back' => true]);
    }

    /** @param array<string, mixed> $context */
    public static function moveTo(CrmCase $case, int $stage, ?User $by = null, ?string $reason = null, array $context = []): void
    {
        $from = $case->stage;
        CrmStageHistory::where('case_id', $case->id)->whereNull('left_at')->update(['left_at' => now()]);
        CrmStageHistory::create(['case_id' => $case->id, 'stage' => $stage, 'entered_at' => now(), 'user_id' => $by?->id, 'reason' => $reason]);

        $case->forceFill(['stage' => $stage, 'stage_entered_at' => now()])->save();

        $text = ($context['back'] ?? false)
            ? 'Moved back to '.Crm::stageLabel($stage).' from '.Crm::stageLabel($from).". Reason: {$reason}"
            : 'Moved to '.Crm::stageLabel($stage);
        Timeline::log($case, 'stage', $text, ['from' => $from, 'to' => $stage, 'reason' => $reason], userId: $by?->id, visibleToCustomer: true);

        StepEffects::enteredStage($case, $stage, $by, $context);

        if (! ($context['back'] ?? false)) {
            Notifier::event('stage_changed', $case, $context);
        }
    }

    /** Seconds spent per stage, the current one up to now (CRM-STG-008). @return array<int, int> */
    public static function timeInStages(CrmCase $case): array
    {
        $totals = [];
        foreach (CrmStageHistory::where('case_id', $case->id)->get() as $row) {
            $end = $row->left_at ?? ($case->isActive() ? now() : ($case->closed_at ?? now()));
            $totals[$row->stage] = ($totals[$row->stage] ?? 0) + max(0, $end->getTimestamp() - $row->entered_at->getTimestamp());
        }
        ksort($totals);

        return $totals;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function validateData(CrmStepTemplate $template, array $data, bool $auto): array
    {
        $fields = (array) ($template->fields ?? []);
        $clean = [];
        $missing = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $value = $data[$name] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if (($value === null || $value === '') && ($field['required'] ?? false) && ! $auto) {
                $missing[] = $field['label'];
            }
            if ($value !== null && $value !== '') {
                $clean[$name] = $value;
            }
        }
        if ($missing) {
            throw ValidationException::withMessages(['data' => 'Please enter: '.implode(', ', $missing)]);
        }

        // Extra keys from automatic sources (order no., hold id …) are kept too.
        foreach ($data as $key => $value) {
            if (! array_key_exists($key, $clean) && $value !== null && $value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $data */
    private static function describeData(CrmStepTemplate $template, array $data): string
    {
        $parts = [];
        foreach ((array) ($template->fields ?? []) as $field) {
            if (isset($data[$field['name']]) && $data[$field['name']] !== '') {
                $value = $data[$field['name']];
                $value = $field['type'] === 'select' ? ($field['options'][$value] ?? $value) : $value;
                $parts[] = $field['label'].': '.$value;
            }
        }

        return $parts ? ' ('.implode(', ', $parts).')' : '';
    }

    /** The order behind a vehicle-sale Case, if any. */
    public static function order(CrmCase $case): ?Order
    {
        /** @var Order|null */
        return $case->linked(Order::class);
    }
}
