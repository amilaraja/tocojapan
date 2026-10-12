<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\CustomerMatcher;
use App\Modules\Crm\Domain\Notifier;
use App\Modules\Crm\Domain\StageEngine;
use App\Modules\Crm\Domain\TemplateRenderer;
use App\Modules\Crm\Domain\Timeline;
use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCaseStep;
use App\Modules\Crm\Support\CrmSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The customer's view of their purchases and enquiries (SRS §9,
 * CRM-POR-001..006). Only the customer's own Cases, by secret link: a
 * wrong reference or token is a plain 404 (CRM-NFR-002).
 */
class PortalController
{
    public function index(Request $request): View
    {
        $customer = CustomerMatcher::forUser($request->user());
        $cases = $customer
            ? CrmCase::where('customer_id', $customer->id)->whereNull('merged_into_id')
                ->where(fn ($q) => $q->where('state', '!=', Crm::STATE_CLOSED)->orWhereNotIn('reason', ['Spam', 'Duplicate']))
                ->with(['vehicle', 'steps.template', 'destPort'])->latest('id')->get()
            : collect();

        return view('crm.portal.index', [
            'cases' => $cases,
            'next' => $cases->mapWithKeys(fn (CrmCase $case) => [$case->id => self::nextAction($case)]),
        ]);
    }

    public function show(string $case, string $token): View
    {
        $case = $this->find($case, $token);
        $case->loadMissing(['vehicle', 'destPort', 'destCountry']);

        $steps = $case->isVehicleSale()
            ? StageEngine::steps($case)->filter(fn (CrmCaseStep $step) => $step->template->customer_visible)->groupBy(fn (CrmCaseStep $step) => $step->template->stage)
            : collect();

        return view('crm.portal.show', [
            'case' => $case,
            'token' => $token,
            'steps' => $steps,
            'stepData' => StageEngine::stepData($case),
            'next' => self::nextAction($case),
            'messages' => $case->activities()->where('visible_to_customer', true)
                ->whereIn('type', ['customer_message', 'staff_message', 'email_out', 'email_in'])->latest('created_at')->limit(50)->get(),
            'documents' => $case->getMedia('documents')->filter(fn (Media $m) => (bool) $m->getCustomProperty('visible_to_customer'))->values(),
            'contact' => $this->contactName($case),
            'bankDetails' => $case->isVehicleSale() && (int) $case->stage === 2 ? TemplateRenderer::bankDetails() : null,
            'paymentDue' => $case->slaTimers()->where('measure', 'payment')->whereNull('cancelled_at')->latest('id')->value('due_at'),
        ]);
    }

    /** Accept the quote, "Documents received", "Car received" (CRM-POR-004). */
    public function confirm(string $case, string $token, string $step): RedirectResponse
    {
        $case = $this->find($case, $token);
        $allowed = match ($step) {
            'order_confirmed' => (int) $case->stage === 1 && StageEngine::step($case, 'quote_sent')?->isDone(),
            'documents_received' => (int) $case->stage === 4 && StageEngine::step($case, 'documents_couriered')?->isDone(),
            'car_received' => (int) $case->stage === 5,
            default => false,
        };
        abort_unless($allowed && $case->isActive(), 404);

        StageEngine::complete($case, $step, null, ['by_customer' => true], false, ['customer' => true]);
        Timeline::log($case, 'customer_message', match ($step) {
            'order_confirmed' => 'I confirm my order.',
            'documents_received' => 'I have received the original documents.',
            default => 'I have received my car.',
        }, ['action' => $step], userId: null, visibleToCustomer: true);
        Notifier::event('customer_message', $case, ['message' => 'Customer confirmed: '.StageEngine::step($case, $step)?->template->customerLabel()]);

        return back()->with('status', 'Thank you. We have recorded your confirmation.');
    }

    /** Message the TOCO contact from the Case (CRM-POR-005). */
    public function message(Request $request, string $case, string $token): RedirectResponse
    {
        $case = $this->find($case, $token);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        CaseActions::customerMessaged($case, trim($data['body']));

        return back()->with('status', 'Message sent. We will reply soon.');
    }

    /** Payment slip upload (proposal WEB-TOC-02): staff check it and confirm the payment. */
    public function paymentSlip(Request $request, string $case, string $token): RedirectResponse
    {
        $case = $this->find($case, $token);
        abort_unless($case->isVehicleSale() && $case->isActive(), 404);
        $request->validate(['slip' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:'.(int) config('crm.max_upload_kb', 10240)]]);

        $media = $case->addMediaFromRequest('slip')
            ->usingFileName('payment-slip-'.now()->format('YmdHis').'.'.$request->file('slip')->extension())
            ->withCustomProperties(['doc_type' => 'payment_slip', 'visible_to_customer' => true, 'uploaded_by_customer' => true])
            ->toMediaCollection('documents');

        Timeline::log($case, 'document', 'Customer uploaded a payment slip: '.$media->file_name, ['media_id' => $media->id, 'doc_type' => 'payment_slip'], userId: null, visibleToCustomer: true);
        CaseActions::customerMessaged($case, 'I have uploaded my payment slip. Please confirm when the payment arrives.', 'customer_message', ['media_id' => $media->id]);

        return back()->with('status', 'Payment slip received. We will confirm as soon as the payment arrives.');
    }

    /** Documents marked visible to the customer (CRM-POR-003). */
    public function document(string $case, string $token, int $media): BinaryFileResponse
    {
        $case = $this->find($case, $token);
        $file = $case->getMedia('documents')->first(fn (Media $m) => $m->id === $media && $m->getCustomProperty('visible_to_customer'));
        abort_unless($file && is_file($file->getPath()), 404);

        return response()->download($file->getPath(), $file->file_name);
    }

    private function find(string $reference, string $token): CrmCase
    {
        $case = CrmCase::where('reference', $reference)->first();
        abort_unless($case && hash_equals((string) $case->portal_token, $token), 404);
        if ($case->merged_into_id && ($into = CrmCase::find($case->merged_into_id))) {
            abort(redirect($into->portalUrl()));
        }

        return $case;
    }

    /** What the customer should do next, in one line (CRM-POR-001). */
    public static function nextAction(CrmCase $case): ?string
    {
        if (! $case->isActive()) {
            return match ($case->state) {
                Crm::STATE_WON => 'Completed. Thank you for buying from TOCO.',
                default => null,
            };
        }
        if (! $case->isVehicleSale()) {
            return $case->status === 'waiting_customer' ? 'We are waiting for your reply.' : 'Our team is handling your request.';
        }

        $done = fn (string $key) => (bool) StageEngine::step($case, $key)?->isDone();

        return match ((int) $case->stage) {
            1 => $done('quote_sent') ? 'Confirm your order to reserve the car.' : 'We are preparing your price quote.',
            2 => 'Stage 02 Payment: payment due'.(($due = $case->slaTimers()->where('measure', 'payment')->whereNull('cancelled_at')->latest('id')->value('due_at'))
                ? ' by '.\Illuminate\Support\Carbon::parse($due)->timezone('Asia/Tokyo')->format('j M') : '').'.',
            3 => 'We are preparing the export documents and the vessel booking.',
            4 => $done('documents_couriered') ? 'Click "Documents received" when the documents arrive.' : 'We will courier the original documents soon.',
            5 => 'Click "Car received" after you collect your car.',
            default => null,
        };
    }

    /** Buyers only ever see the nickname (decision 13 Oct 2026). */
    private function contactName(CrmCase $case): string
    {
        return (CrmSettings::get('email.show_nickname') ? CrmAgentStatus::nicknameFor($case->owner_id) : null)
            ?: (string) CrmSettings::get('email.sender_name');
    }
}
