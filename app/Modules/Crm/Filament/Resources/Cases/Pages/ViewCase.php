<?php

namespace App\Modules\Crm\Filament\Resources\Cases\Pages;

use App\Models\Port;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\Crm\Domain\Assigner;
use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\StageEngine;
use App\Modules\Crm\Domain\TemplateRenderer;
use App\Modules\Crm\Domain\Timeline;
use App\Modules\Crm\Filament\Resources\Cases\CaseResource;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCaseStep;
use App\Modules\Crm\Models\CrmEmailTemplate;
use App\Modules\Crm\Models\CrmReason;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmUi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One Case (CRM-CAS-004/005, STG-001..008, ACT-001..007, SLA-005): header,
 * five-stage bar, mini steps, timeline, tasks and documents.
 */
class ViewCase extends ViewRecord
{
    protected static string $resource = CaseResource::class;

    protected string $view = 'crm::filament.case';

    public string $timelineFilter = 'all';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_unless(CrmAccess::canView($this->case()), 403);
        // A merged Case redirects to the Case it was merged into (CRM-CAS-006).
        if ($this->case()->merged_into_id) {
            $this->redirect(CaseResource::getUrl('view', ['record' => $this->case()->merged_into_id]));
        }
    }

    public function getTitle(): string
    {
        return $this->case()->reference;
    }

    public function case(): CrmCase
    {
        /** @var CrmCase */
        return $this->getRecord();
    }

    public function canEditCase(): bool
    {
        return CrmAccess::canEdit($this->case());
    }

    /** Operations work steps of stages 03 and 04 on any Case (SRS §2.2, CRM-ASN-008). */
    public function canTick(CrmCaseStep $step): bool
    {
        return $this->canEditCase()
            || (CrmAccess::isOperations() && in_array($step->template->stage, [3, 4], true))
            || (int) $step->assignee_id === (int) auth()->id();
    }

    /** @return Collection<int, CrmCaseStep> */
    public function steps(): Collection
    {
        return StageEngine::steps($this->case()->fresh());
    }

    /** @return Collection<int, CrmActivity> */
    public function timeline(): Collection
    {
        return $this->case()->activities()->with('user')
            ->when($this->timelineFilter !== 'all', fn ($q) => $q->whereIn('type', CrmActivity::FILTERS[$this->timelineFilter] ?? []))
            ->latest('created_at')->latest('id')->limit(200)->get();
    }

    protected function reloadCase(): void
    {
        $this->record = $this->case()->fresh();
    }

    protected function done(string $message): void
    {
        $this->reloadCase();
        Notification::make()->title($message)->success()->send();
    }

    /** Validation errors from the domain shown as a notification, the modal stays open. */
    protected function attempt(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->done($message);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
            $this->reloadCase();
        }
    }

    protected function getHeaderActions(): array
    {
        $edit = fn () => $this->canEditCase();
        $active = fn () => $this->case()->isActive();

        return [
            ActionGroup::make([
                $this->emailAction(),
                Action::make('message')->label('Message in their account')->icon('heroicon-o-chat-bubble-left-right')->visible($edit)
                    ->schema([Textarea::make('body')->label('Message')->rows(5)->required()->maxLength(5000)])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::sendPortalMessage($this->case(), $data['body'], auth()->user()), 'Message sent')),
                Action::make('note')->label('Add note')->icon('heroicon-o-pencil-square')
                    ->schema([Textarea::make('body')->label('Internal note (never shown to the customer)')->rows(4)->required()])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::addNote($this->case(), $data['body'], auth()->user()), 'Note added')),
                Action::make('logContact')->label('Log call / WhatsApp')->icon('heroicon-o-phone')->visible($edit)
                    ->schema([
                        Select::make('type')->options(['whatsapp' => 'WhatsApp chat', 'call' => 'Phone call', 'meeting' => 'Meeting'])->default('whatsapp')->required(),
                        Select::make('outcome')->options(['Talked' => 'Talked', 'Sent details' => 'Sent details', 'No answer' => 'No answer', 'Left message' => 'Left message', 'Customer will decide' => 'Customer will decide'])->required(),
                        Textarea::make('notes')->rows(3),
                    ])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::logContact($this->case(), $data['type'], $data['outcome'], $data['notes'] ?? null, auth()->user()), 'Logged')),
                Action::make('whatsapp')->label('Open WhatsApp chat')->icon('heroicon-o-chat-bubble-oval-left')
                    ->visible(fn () => (bool) $this->case()->customer?->whatsappNumber())
                    ->url(fn () => 'https://wa.me/'.$this->case()->customer?->whatsappNumber(), shouldOpenInNewTab: true),
                Action::make('task')->label('Add task')->icon('heroicon-o-check-circle')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(190),
                        DateTimePicker::make('due_at')->label('Due')->seconds(false)->timezone(CrmUi::tz())->default(now()->addDay()->setTime(10, 0))->required(),
                        Select::make('assignee_id')->label('For')->options(fn () => CrmUi::staffOptions())->default(fn () => $this->case()->owner_id ?? auth()->id()),
                    ])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::addTask($this->case(), $data['title'], Carbon::parse($data['due_at']), $data['assignee_id'] ?? null, auth()->user()), 'Task added')),
                $this->uploadAction(),
            ])->label('Follow up')->icon('heroicon-o-plus')->button()->color('primary'),

            ActionGroup::make([
                Action::make('followUp')->label('Set next follow-up')->icon('heroicon-o-calendar')->visible($active)
                    ->fillForm(fn () => ['at' => $this->case()->next_follow_up_at])
                    ->schema([DateTimePicker::make('at')->label('Next follow-up')->seconds(false)->timezone(CrmUi::tz())->required()])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::setFollowUp($this->case(), Carbon::parse($data['at']), auth()->user()), 'Follow-up set')),
                Action::make('waiting')->label('Waiting on customer')->icon('heroicon-o-pause')->visible(fn () => $this->case()->isActive() && $this->case()->state !== Crm::STATE_WAITING && $this->canEditCase())
                    ->schema([DateTimePicker::make('follow_up')->label('Follow up on')->seconds(false)->timezone(CrmUi::tz())->default(now()->addDays(2))->required(), Textarea::make('note')->rows(2)])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::setState($this->case(), Crm::STATE_WAITING, auth()->user(), $data['note'] ?? null, ['follow_up' => Carbon::parse($data['follow_up'])]), 'Waiting on customer: reply clocks paused')),
                Action::make('hold')->label('Put on hold')->icon('heroicon-o-hand-raised')->visible(fn () => $this->case()->isActive() && $this->case()->state !== Crm::STATE_ON_HOLD && $this->canEditCase())
                    ->schema([DatePicker::make('until')->label('Resume on')->default(now()->addWeek())->required(), Textarea::make('note')->label('Reason')->rows(2)->required()])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::setState($this->case(), Crm::STATE_ON_HOLD, auth()->user(), $data['note'], ['follow_up' => Carbon::parse($data['until'], 'Asia/Tokyo')->setTime(9, 0), 'hold_until' => Carbon::parse($data['until'], 'Asia/Tokyo')->startOfDay()]), 'On hold')),
                Action::make('resume')->label('Back to open')->icon('heroicon-o-play')->visible(fn () => in_array($this->case()->state, [Crm::STATE_WAITING, Crm::STATE_ON_HOLD], true) && $this->canEditCase())
                    ->action(fn () => $this->attempt(fn () => CaseActions::setState($this->case(), Crm::STATE_OPEN, auth()->user()), 'Open again')),
                Action::make('supportStatus')->label('Change status')->icon('heroicon-o-arrows-right-left')->visible(fn () => ! $this->case()->isVehicleSale() && $this->case()->isActive() && $this->canEditCase())
                    ->schema([Select::make('status')->options(Crm::SUPPORT_STATUSES)->required(), Textarea::make('note')->rows(2)])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::setStatus($this->case(), $data['status'], auth()->user(), $data['note'] ?? null), 'Status changed')),
                Action::make('advance')->label('Move to next stage')->icon('heroicon-o-forward')->visible(fn () => $this->case()->isVehicleSale() && $this->case()->isActive() && $this->canEditCase())
                    ->requiresConfirmation()
                    ->action(fn () => $this->attempt(fn () => StageEngine::advance($this->case(), auth()->user()), 'Moved on')),
                Action::make('back')->label('Move back one stage')->icon('heroicon-o-backward')->color('warning')
                    ->visible(fn () => $this->case()->isVehicleSale() && (int) $this->case()->stage > 1 && CrmAccess::isAdmin())
                    ->schema([Textarea::make('reason')->label('Reason')->required()->rows(2)])
                    ->action(fn (array $data) => $this->attempt(fn () => StageEngine::moveBack($this->case(), $data['reason'], auth()->user()), 'Moved back')),
                Action::make('convert')->label('Convert to vehicle sale')->icon('heroicon-o-truck')->visible(fn () => $this->case()->type === Crm::TYPE_GENERAL && $this->case()->isActive() && $this->canEditCase())
                    ->schema([$this->vehicleSelect('vehicle_id')->required()])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::convertToVehicleSale($this->case(), (int) $data['vehicle_id'], auth()->user()), 'Now a vehicle sale')),
                Action::make('lose')->label('Mark lost')->icon('heroicon-o-x-circle')->color('danger')->visible(fn () => $this->case()->isActive() && $this->case()->isVehicleSale() && $this->canEditCase())
                    ->schema([Select::make('reason')->label('Why was it lost?')->options(fn () => CrmReason::options('lost'))->required(), Textarea::make('note')->rows(2)])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::lose($this->case(), $data['reason'], $data['note'] ?? null, auth()->user()), 'Marked lost')),
                Action::make('close')->label('Close (duplicate, spam …)')->icon('heroicon-o-archive-box-x-mark')->color('gray')->visible(fn () => $this->case()->isActive() && $this->canEditCase())
                    ->schema([Select::make('reason')->options(fn () => CrmReason::options('close'))->required(), Textarea::make('note')->rows(2)])
                    ->action(fn (array $data) => $this->attempt(fn () => CaseActions::close($this->case(), $data['reason'], $data['note'] ?? null, auth()->user()), 'Closed')),
                Action::make('reopen')->label('Reopen')->icon('heroicon-o-arrow-uturn-left')->visible(fn () => ! $this->case()->isActive() && ! $this->case()->merged_into_id && CrmAccess::isAdmin())
                    ->requiresConfirmation()
                    ->action(fn () => $this->attempt(fn () => CaseActions::reopenCase($this->case(), auth()->user()), 'Reopened')),
            ])->label('Status')->icon('heroicon-o-flag')->button()->color('gray'),

            ActionGroup::make([
                Action::make('assign')->label('Assign / reassign')->icon('heroicon-o-user-plus')->visible(fn () => CrmAccess::isAdmin() || CrmAccess::isTeamLead())
                    ->schema([Select::make('owner')->label('Owner')->options(fn () => ['' => 'Unassigned'] + CrmUi::staffOptions())->default(fn () => $this->case()->owner_id), Textarea::make('note')->rows(2)])
                    ->action(function (array $data) {
                        Assigner::assign($this->case(), filled($data['owner']) ? User::find($data['owner']) : null, $data['note'] ?? null, auth()->user());
                        $this->done('Owner changed');
                    }),
                Action::make('pick')->label('Take this Case')->icon('heroicon-o-hand-raised')
                    ->visible(fn () => $this->case()->owner_id === null && $this->case()->isActive() && CrmAccess::canPick())
                    ->action(function () {
                        Assigner::assign($this->case(), auth()->user(), 'Picked from the unassigned queue', auth()->user());
                        $this->done('The Case is yours');
                    }),
                Action::make('details')->label('Edit details')->icon('heroicon-o-pencil')->visible($edit)
                    ->fillForm(fn () => $this->case()->only(['vehicle_id', 'dest_port_id', 'value_usd', 'value_basis', 'payment_mode', 'subject']))
                    ->schema([
                        $this->vehicleSelect('vehicle_id'),
                        Select::make('dest_port_id')->label('Destination port')->searchable()
                            ->options(fn () => Port::with('country')->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' ('.$p->country?->name.')'])->all()),
                        TextInput::make('value_usd')->label('Value (USD)')->numeric(),
                        Select::make('value_basis')->label('Basis')->options(['CIF' => 'CIF', 'FOB' => 'FOB']),
                        Select::make('payment_mode')->options(Crm::PAYMENT_MODES),
                        TextInput::make('subject')->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        $case = $this->case();
                        $port = filled($data['dest_port_id'] ?? null) ? Port::find($data['dest_port_id']) : null;
                        $case->forceFill($data + ['dest_country_id' => $port?->country_id ?? $case->dest_country_id])->save();
                        Timeline::log($case, 'system', 'Details updated', ['changes' => array_keys($case->getChanges())], userId: auth()->id());
                        $this->done('Saved');
                    }),
                Action::make('merge')->label('Merge into another Case')->icon('heroicon-o-arrows-pointing-in')->visible(fn () => $this->case()->isActive() && $this->canEditCase())
                    ->schema([Select::make('into')->label('Keep this Case')->required()
                        ->options(fn () => CrmCase::where('customer_id', $this->case()->customer_id)->whereKeyNot($this->case()->id)->whereNull('merged_into_id')
                            ->get()->mapWithKeys(fn ($c) => [$c->id => $c->reference.' · '.$c->progressLabel()])->all())])
                    ->requiresConfirmation()
                    ->action(function (array $data) {
                        CaseActions::merge($this->case(), CrmCase::findOrFail($data['into']), auth()->user());
                        $this->redirect(CaseResource::getUrl('view', ['record' => $data['into']]));
                    }),
                Action::make('portal')->label('Customer\'s view')->icon('heroicon-o-eye')->url(fn () => $this->case()->portalUrl(), shouldOpenInNewTab: true),
            ])->icon('heroicon-o-ellipsis-vertical')->color('gray'),
        ];
    }

    /** Email from the Case with templates and placeholders (CRM-ACT-002). */
    protected function emailAction(): Action
    {
        return Action::make('email')->label('Email the customer')->icon('heroicon-o-envelope')
            ->visible(fn () => $this->canEditCase() && filled($this->case()->customer?->email))
            ->modalWidth('3xl')
            ->schema([
                Select::make('template')->label('Template')->options(fn () => CrmEmailTemplate::where('system', false)->orderBy('name')->pluck('name', 'key')->all())
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $template = $state ? CrmEmailTemplate::byKey($state) : null;
                        if ($template) {
                            $set('subject', $template->subject);
                            $set('body', $template->body);
                        }
                    }),
                TextInput::make('subject')->required()->maxLength(255),
                Textarea::make('body')->required()->rows(12)
                    ->helperText('Placeholders: {customer_name} {vehicle} {price} {port} {stage} {portal_url} {due_date} {bank_details} {sender_name}. Bank details always come from Site settings → Payment.'),
            ])
            ->action(fn (array $data) => $this->attempt(fn () => CaseActions::sendEmail($this->case(), $data['subject'], $this->guardBankDetails($data['body']), auth()->user(), $data['template'] ?? null), 'Email sent'));
    }

    /** Staff may not type account numbers into emails: only {bank_details} (proposal WEB-TOC-02 p.6). */
    protected function guardBankDetails(string $body): string
    {
        if (preg_match('/\b(?:account|a\/c|acc(?:ount)?\s*no|iban|swift)\b[^\n]{0,40}\d{5,}/i', $body)) {
            throw ValidationException::withMessages(['body' => 'Do not type bank details. Use the {bank_details} placeholder so the details always come from Site settings.']);
        }

        return $body;
    }

    protected function uploadAction(): Action
    {
        return Action::make('upload')->label('Upload document')->icon('heroicon-o-paper-clip')
            ->schema([
                FileUpload::make('file')->disk('local')->directory('crm-uploads')->maxSize((int) config('crm.max_upload_kb', 10240))->required()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
                Select::make('doc_type')->label('Document')->options([
                    'proforma' => 'Proforma invoice', 'invoice' => 'Invoice', 'bl' => 'Bill of Lading (B/L)', 'export_certificate' => 'Export certificate',
                    'inspection_certificate' => 'Inspection certificate', 'photos' => 'Photos', 'payment_slip' => 'Payment slip', 'other' => 'Other',
                ])->required(),
                TextInput::make('label')->label('Name shown to the customer')->maxLength(120),
                Toggle::make('visible')->label('Customer can download it')->default(true),
            ])
            ->action(function (array $data) {
                $case = $this->case();
                $media = $case->addMedia(\Illuminate\Support\Facades\Storage::disk('local')->path($data['file']))
                    ->withCustomProperties(['doc_type' => $data['doc_type'], 'visible_to_customer' => (bool) $data['visible'], 'label' => $data['label'] ?? null, 'uploaded_by' => auth()->id()])
                    ->toMediaCollection('documents');
                Timeline::log($case, 'document', 'Uploaded '.($data['label'] ?: $media->file_name).((bool) $data['visible'] ? ' (customer can download)' : ' (internal)'),
                    ['media_id' => $media->id, 'doc_type' => $data['doc_type']], userId: auth()->id(), visibleToCustomer: (bool) $data['visible']);
                $this->done('Document uploaded');
            });
    }

    /** Tick a mini step, asking for its data (CRM-STG-004). */
    public function tickStepAction(): Action
    {
        return Action::make('tickStep')->label('Done')->icon('heroicon-m-check')->size('sm')->color('success')
            ->modalHeading(fn (array $arguments) => StageEngine::step($this->case(), $arguments['key'] ?? '')?->template->label ?? 'Step')
            ->modalSubmitActionLabel('Mark done')
            ->schema(function (array $arguments) {
                $step = StageEngine::step($this->case(), $arguments['key'] ?? '');

                return collect((array) ($step?->template->fields ?? []))->map(function (array $field) {
                    $component = match ($field['type'] ?? 'text') {
                        'select' => Select::make($field['name'])->options($field['options'] ?? []),
                        'date' => DatePicker::make($field['name']),
                        'number' => TextInput::make($field['name'])->numeric(),
                        default => TextInput::make($field['name'])->maxLength(190),
                    };

                    return $component->label($field['label'])->required((bool) ($field['required'] ?? false));
                })->push(Textarea::make('note')->label('Note (optional)')->rows(2))->all();
            })
            ->fillForm(fn (array $arguments) => $arguments['key'] === 'quote_sent' ? ['price' => $this->case()->value_usd, 'basis' => $this->case()->value_basis ?? 'CIF'] : [])
            ->action(function (array $data, array $arguments) {
                $step = StageEngine::step($this->case(), $arguments['key'] ?? '');
                abort_unless($step && $this->canTick($step), 403);
                $this->attempt(fn () => StageEngine::complete($this->case(), $arguments['key'], auth()->user(), array_filter($data, fn ($v) => $v !== null && $v !== '')), 'Done: '.$step->template->label);
            });
    }

    public function reopenStepAction(): Action
    {
        return Action::make('reopenStep')->label('Undo')->icon('heroicon-m-arrow-uturn-left')->size('xs')->color('gray')->link()
            ->requiresConfirmation()->modalHeading('Mark this step as not done?')
            ->visible(fn () => $this->canEditCase())
            ->action(function (array $arguments) {
                StageEngine::reopen($this->case(), $arguments['key'] ?? '', auth()->user());
                $this->done('Step reopened');
            });
    }

    public function completeTaskAction(): Action
    {
        return Action::make('completeTask')->label('Done')->icon('heroicon-m-check')->size('xs')->link()
            ->action(function (array $arguments) {
                $task = CrmTask::where('case_id', $this->case()->id)->findOrFail($arguments['task'] ?? 0);
                CaseActions::completeTask($task, auth()->user());
                $this->done('Task done');
            });
    }

    public function toggleDocumentAction(): Action
    {
        return Action::make('toggleDocument')->label('Show / hide')->size('xs')->link()->color('gray')
            ->visible(fn () => $this->canEditCase())
            ->action(function (array $arguments) {
                $media = $this->case()->getMedia('documents')->firstWhere('id', (int) ($arguments['media'] ?? 0));
                if ($media) {
                    $media->setCustomProperty('visible_to_customer', ! $media->getCustomProperty('visible_to_customer'))->save();
                    $this->done($media->getCustomProperty('visible_to_customer') ? 'Customer can download it' : 'Hidden from the customer');
                }
            });
    }

    public function downloadDocument(int $mediaId)
    {
        $media = $this->case()->getMedia('documents')->firstWhere('id', $mediaId);
        abort_unless($media instanceof Media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    protected function vehicleSelect(string $name): Select
    {
        return Select::make($name)->label('Vehicle')->searchable()
            ->getSearchResultsUsing(fn (string $search) => Vehicle::where('stock_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                ->limit(20)->get()->mapWithKeys(fn ($v) => [$v->id => $v->stock_no.' · '.trim($v->title)]))
            ->getOptionLabelUsing(fn ($value) => ($v = Vehicle::withTrashed()->find($value)) ? $v->stock_no.' · '.trim($v->title) : null);
    }

    /** Website records linked to the Case, with links to their admin screens (CRM-CAS-005, INT-002). @return list<array{label: string, url: ?string, status: ?string}> */
    public function linkedRecords(): array
    {
        return $this->case()->links()->with('linkable')->get()->filter(fn ($link) => $link->linkable)->map(function ($link) {
            $r = $link->linkable;

            return match (true) {
                $r instanceof \App\Models\Quote => ['label' => 'Quote '.$r->reference, 'url' => \App\Filament\Admin\Resources\Quotes\QuoteResource::getUrl('edit', ['record' => $r]), 'status' => ucfirst(str_replace('_', ' ', $r->status))],
                $r instanceof \App\Models\Order => ['label' => 'Order '.$r->order_no, 'url' => \App\Filament\Admin\Resources\Orders\OrderResource::getUrl('view', ['record' => $r]), 'status' => $r->statusLabel()],
                $r instanceof \App\Models\ProformaInvoice => ['label' => 'Proforma '.$r->invoice_no, 'url' => \App\Filament\Admin\Resources\ProformaInvoices\ProformaInvoiceResource::getUrl('index'), 'status' => 'Expires '.\Illuminate\Support\Carbon::parse($r->expires_on)->format('j M')],
                $r instanceof \App\Models\ContactInquiry => ['label' => 'Contact form #'.$r->id, 'url' => \App\Filament\Admin\Resources\ContactInquiries\ContactInquiryResource::getUrl('edit', ['record' => $r]), 'status' => $r->is_handled ? 'Handled' : 'Not handled'],
                $r instanceof \App\Models\SparePartInquiry => ['label' => 'Spare-part request #'.$r->id, 'url' => \App\Filament\Admin\Resources\SparePartInquiries\SparePartInquiryResource::getUrl('edit', ['record' => $r]), 'status' => $r->is_handled ? 'Handled' : 'Not handled'],
                default => ['label' => class_basename($r).' #'.$r->getKey(), 'url' => null, 'status' => null],
            };
        })->values()->all();
    }

    /** Template preview values for the header (used by the view). */
    public function placeholderValues(): array
    {
        return TemplateRenderer::values($this->case());
    }
}
