<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Notifications\NewOrderMessage;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

class ViewOrder extends ViewRecord implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.admin.resources.orders.pages.view-order';

    public ?array $replyData = ['body' => '', 'attachments' => []];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Auto-mark customer messages as read by admin on view.
        OrderMessage::query()
            ->where('order_id', $this->record->id)
            ->where('from_admin', false)
            ->whereNull('read_by_admin_at')
            ->update(['read_by_admin_at' => now()]);

        $this->replyForm->fill($this->replyData);
    }

    protected function getHeaderActions(): array
    {
        /** @var Order $order */
        $order = $this->record;

        $transitions = [
            'processing' => ['label' => 'Mark as processing', 'color' => 'info', 'icon' => 'heroicon-o-cog-6-tooth', 'from' => ['paid']],
            'delivered' => ['label' => 'Mark as delivered', 'color' => 'success', 'icon' => 'heroicon-o-check-badge', 'from' => ['shipped']],
            'cancelled' => ['label' => 'Cancel order', 'color' => 'danger', 'icon' => 'heroicon-o-x-circle', 'from' => ['pending', 'paid', 'processing']],
        ];

        $actions = [];

        // Confirm bank payment received — only for bank_transfer orders still pending.
        if ($order->payment_provider === 'bank_transfer' && $order->status === 'pending') {
            $actions[] = Action::make('status_paid')
                ->label('Confirm payment received')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->requiresConfirmation()
                ->modalDescription('This marks the bank transfer as received and notifies the customer that processing has begun.')
                ->action(function () use ($order) {
                    $order->transitionTo('paid');
                    Notification::make()->title('Marked as paid — customer notified.')->success()->send();
                    $this->dispatch('$refresh');
                });
        }

        foreach ($transitions as $status => $cfg) {
            if (! in_array($order->status, $cfg['from'], true)) {
                continue;
            }
            $actions[] = Action::make("status_{$status}")
                ->label($cfg['label'])
                ->color($cfg['color'])
                ->icon($cfg['icon'])
                ->requiresConfirmation()
                ->action(function () use ($order, $status, $cfg) {
                    $order->transitionTo($status);
                    Notification::make()->title($cfg['label'].' — customer notified.')->success()->send();
                    $this->dispatch('$refresh');
                });
        }

        // Mark as shipped — collects B/L, vessel + voyage + ETA + optional carrier link.
        if (in_array($order->status, ['paid', 'processing'], true)) {
            $actions[] = Action::make('status_shipped')
                ->label('Mark as shipped')
                ->color('success')
                ->icon('heroicon-o-truck')
                ->fillForm(fn () => [
                    'bl_number' => $order->bl_number,
                    'vessel_name' => $order->vessel_name,
                    'voyage_no' => $order->voyage_no,
                    'eta_at' => $order->eta_at?->toDateString(),
                    'carrier_tracking_url' => $order->carrier_tracking_url,
                ])
                ->schema([
                    TextInput::make('bl_number')->label('B/L number')->required()->maxLength(64),
                    TextInput::make('vessel_name')->label('Vessel name')->required()->maxLength(120),
                    TextInput::make('voyage_no')->label('Voyage no.')->maxLength(64),
                    DatePicker::make('eta_at')->label('ETA at destination port')->required(),
                    TextInput::make('carrier_tracking_url')->label('Carrier tracking URL (optional)')->url()->maxLength(255),
                ])
                ->action(function (array $data) use ($order) {
                    $order->fill([
                        'bl_number' => $data['bl_number'],
                        'vessel_name' => $data['vessel_name'],
                        'voyage_no' => $data['voyage_no'] ?: null,
                        'eta_at' => $data['eta_at'],
                        'carrier_tracking_url' => $data['carrier_tracking_url'] ?: null,
                    ])->save();
                    $order->transitionTo('shipped');
                    Notification::make()->title('Marked as shipped — customer notified with B/L details.')->success()->send();
                    $this->dispatch('$refresh');
                });
        }

        return $actions;
    }

    public function replyForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Reply')
                    ->rows(3)
                    ->maxLength(5000),
                FileUpload::make('attachments')
                    ->label('Attach documents')
                    ->multiple()
                    ->disk('public')
                    ->directory('order-message-temp')
                    ->maxSize(8192)
                    ->maxFiles(5),
            ])
            ->statePath('replyData');
    }

    protected function getForms(): array
    {
        return ['replyForm'];
    }

    public function sendReply(): void
    {
        $state = $this->replyForm->getState();
        $body = trim((string) ($state['body'] ?? ''));
        $files = array_filter($state['attachments'] ?? []);

        if ($body === '' && empty($files)) {
            Notification::make()->title('Add a message or an attachment.')->warning()->send();

            return;
        }

        /** @var Order $order */
        $order = $this->record;
        $message = OrderMessage::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'from_admin' => true,
            'body' => $body,
            'read_by_admin_at' => now(),
        ]);

        foreach ($files as $rel) {
            $abs = \Illuminate\Support\Facades\Storage::disk('public')->path($rel);
            if (is_file($abs)) {
                $message->addMedia($abs)->toMediaCollection('attachments');
            }
        }

        try {
            $order->user->notify(new NewOrderMessage($message, forAdmin: false));
        } catch (\Throwable $e) {
            Log::warning('Customer notify failed: '.$e->getMessage());
        }

        $this->replyData = ['body' => '', 'attachments' => []];
        $this->replyForm->fill($this->replyData);

        Notification::make()->title('Reply sent — customer notified.')->success()->send();
    }
}
