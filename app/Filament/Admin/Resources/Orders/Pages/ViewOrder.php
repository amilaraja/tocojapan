<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderMessage;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

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
        return [];
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

        $this->replyData = ['body' => '', 'attachments' => []];
        $this->replyForm->fill($this->replyData);

        Notification::make()->title('Reply sent.')->success()->send();
    }
}
