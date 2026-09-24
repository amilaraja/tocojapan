<?php

namespace App\Modules\Mailer\Filament\Pages;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Domain\Brevo\BrevoDirectory;
use App\Modules\Mailer\Support\MailerAccess;
use App\Modules\Mailer\Support\MailerSettings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * S14 Mailer Settings. Mailer Admins only (TOC-GEN-002: Marketers get 403).
 * Values live in mailer_settings; the Brevo key is encrypted and only its
 * last 4 characters are ever shown (TOC-NFR-001).
 */
class MailerSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'mailer::filament.settings';

    protected static ?string $slug = 'mailer/settings';

    protected static ?string $title = 'Mailer settings';

    protected static ?string $navigationLabel = 'Mailer settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Mailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;

    /** Keys edited on this screen, other than the Brevo key. */
    protected const FIELDS = [
        'mailbox', 'google_key_path', 'import_interval_minutes', 'own_domains',
        'logo_path', 'top_bar_text', 'nav_links',
        'cta_heading', 'cta_text', 'cta_button', 'cta_url', 'fraud_text',
        'footer_company', 'footer_address', 'footer_phone', 'footer_whatsapp', 'footer_email', 'footer_reason',
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return MailerAccess::isAdmin();
    }

    public function mount(): void
    {
        $settings = app(MailerSettings::class);

        $this->form->fill([
            ...array_intersect_key($settings->all(), array_flip(self::FIELDS)),
            'brevo_api_key' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(MailerSettings::class);
        $masked = $settings->masked('brevo_api_key');

        return $schema
            ->components([
                Tabs::make('Mailer settings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Connections')->schema([
                            Section::make('Brevo connection')
                                ->description('The key TOCO Mailer uses to add contacts and create draft campaigns in Brevo. It is stored encrypted.')
                                ->schema([
                                    TextInput::make('brevo_api_key')
                                        ->label('Brevo key')
                                        ->password()
                                        ->revealable()
                                        ->autocomplete('off')
                                        ->placeholder($masked ? "Saved key: {$masked}" : 'No key saved yet')
                                        ->helperText($masked
                                            ? "A key ending in {$masked} is saved. Leave this blank to keep it, or paste a new key to replace it."
                                            : 'Paste the key from Brevo: Settings, SMTP & API, API keys.'),
                                ]),
                            Section::make('Inbox importer')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('mailbox')
                                        ->label('Mailbox to read')
                                        ->email()
                                        ->helperText('The Google Workspace address the importer reads (read-only).'),
                                    TextInput::make('import_interval_minutes')
                                        ->label('Check for new messages every (minutes)')
                                        ->numeric()
                                        ->integer()
                                        ->minValue((int) config('mailer.import.min_interval_minutes'))
                                        ->maxValue((int) config('mailer.import.max_interval_minutes'))
                                        ->required(),
                                    TextInput::make('google_key_path')
                                        ->label('Google key file location on the server')
                                        ->helperText('Must be outside the website folder, for example /home/<user>/secure/toco-gmail-sa.json.')
                                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                            if (filled($value) && str_starts_with(realpath(dirname((string) $value)) ?: (string) $value, public_path())) {
                                                $fail('This file must not be inside the public website folder.');
                                            }
                                        })
                                        ->columnSpanFull(),
                                    TagsInput::make('own_domains')
                                        ->label('TOCO email domains')
                                        ->helperText('Addresses on these domains are never imported, for example tocojapan.com.')
                                        ->placeholder('Add a domain')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                        Tab::make('Email header')->schema([
                            Section::make('Logo and top bar')
                                ->schema([
                                    FileUpload::make('logo_path')
                                        ->label('Email logo')
                                        ->image()
                                        ->disk('public')
                                        ->directory('email-assets/branding')
                                        ->maxSize(1024)
                                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                                        ->helperText('PNG or JPG, about 300px wide. Shown at 150px on a white header.'),
                                    TextInput::make('top_bar_text')->label('Top bar text')->maxLength(80)->required(),
                                ]),
                            Section::make('Header links')
                                ->description('Up to 3 links shown next to the logo. The last one is shown in red.')
                                ->schema([
                                    Repeater::make('nav_links')
                                        ->label('')
                                        ->columns(2)
                                        ->maxItems(3)
                                        ->reorderable()
                                        ->schema([
                                            TextInput::make('label')->label('Text')->maxLength(20)->required(),
                                            TextInput::make('url')->label('Link')->url()->required(),
                                        ])
                                        ->addActionLabel('Add a link')
                                        ->defaultItems(0),
                                ]),
                        ]),
                        Tab::make('Email footer')->schema([
                            Section::make('Request block')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('cta_heading')->label('Heading')->maxLength(80)->required(),
                                    TextInput::make('cta_button')->label('Button text')->maxLength(30)->required(),
                                    Textarea::make('cta_text')->label('Text')->rows(2)->maxLength(200)->columnSpanFull(),
                                    TextInput::make('cta_url')->label('Default button link')->url()->required()->columnSpanFull(),
                                ]),
                            Section::make('Fraud warning')
                                ->schema([
                                    Textarea::make('fraud_text')->label('Warning text')->rows(2)->maxLength(250)->required(),
                                ]),
                            Section::make('Company details')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('footer_company')->label('Company name')->required(),
                                    TextInput::make('footer_email')->label('Email')->email(),
                                    TextInput::make('footer_phone')->label('Phone'),
                                    TextInput::make('footer_whatsapp')->label('WhatsApp'),
                                    Textarea::make('footer_address')->label('Address')->rows(2)->columnSpanFull(),
                                    Textarea::make('footer_reason')
                                        ->label('Why the reader gets this email')
                                        ->rows(2)
                                        ->maxLength(250)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testBrevo')
                ->label('Test Brevo connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    try {
                        $account = app(BrevoClient::class)->withBudget(20)->get('account')->throw()->json();
                        app(BrevoDirectory::class)->forget();
                        Notification::make()
                            ->title('Connection OK')
                            ->body('Connected to the Brevo account of '.($account['companyName'] ?? $account['email'] ?? 'TOCO').'.')
                            ->success()->send();
                    } catch (Throwable $e) {
                        $unauthorised = $e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 401;
                        Notification::make()
                            ->title('Connection failed')
                            ->body($unauthorised ? 'Brevo did not accept this key. Paste the key again.' : $e->getMessage())
                            ->danger()->persistent()->send();
                    }
                }),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings = app(MailerSettings::class);

        $values = array_intersect_key($state, array_flip(self::FIELDS));
        $values['import_interval_minutes'] = (int) $values['import_interval_minutes'];
        $values['own_domains'] = array_values(array_unique(array_map(
            fn ($d) => ltrim(mb_strtolower(trim((string) $d)), '@'),
            $values['own_domains'] ?? [],
        )));
        $values['nav_links'] = array_values($values['nav_links'] ?? []);

        $settings->setMany($values);

        // Blank means "keep the saved key" (it is never sent back to the browser).
        if (filled($state['brevo_api_key'] ?? null)) {
            $settings->set('brevo_api_key', trim((string) $state['brevo_api_key']));
        }

        $this->data['brevo_api_key'] = null;

        Notification::make()->title('Mailer settings saved.')->success()->send();
    }
}
