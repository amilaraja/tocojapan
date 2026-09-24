<?php

namespace App\Modules\Mailer\Filament\Pages\Importer;

use App\Modules\Mailer\Domain\Importer\Extraction;
use App\Modules\Mailer\Domain\Importer\MessageParser;
use App\Modules\Mailer\Domain\Importer\Reasons;
use App\Modules\Mailer\Filament\Clusters\Importer;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * S11 Rule tester (TOC-EXT-008): shows what would be extracted. It uses
 * the same Extraction as the importer but never calls Brevo or saves.
 */
class RuleTester extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'mailer::filament.rule-tester';

    protected static ?string $cluster = Importer::class;

    protected static ?string $slug = 'rule-tester';

    protected static ?string $title = 'Rule tester';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 7;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sender_id')
                ->label('Approved sender')
                ->options(ApprovedSender::query()->orderBy('label')->pluck('label', 'id'))
                ->required(),
            Textarea::make('message')
                ->label('Sample message')
                ->helperText('Paste the full message source (in Gmail: More, Show original, Copy to clipboard), or just the text.')
                ->rows(12)
                ->required(),
        ])->statePath('data');
    }

    public function test(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();

        $sender = ApprovedSender::findOrFail($state['sender_id']);
        $message = MessageParser::fromRaw((string) $state['message'], 'test');

        $out = app(Extraction::class)->run($message, $sender);

        $this->result = [
            'from' => $message->from,
            'fromMatches' => $message->from === null ? null : app(\App\Modules\Mailer\Domain\Importer\SenderMatcher::class)->match($message->from, collect([$sender])) !== null,
            'addresses' => array_map(fn ($a) => [
                'email' => $a['email'],
                'keep' => $a['keep'],
                'reason' => $a['keep'] ? 'Would be imported' : Reasons::label($a['reason']),
            ], $out['addresses']),
            'fields' => collect($out['fields'])->mapWithKeys(fn ($v, $k) => [ucfirst(strtolower(str_replace('_', ' ', $k))) => $v])->all(),
        ];
    }
}
