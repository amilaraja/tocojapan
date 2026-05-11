<?php

namespace App\Filament\Admin\Pages;

use App\Settings\CifSettings;
use App\Settings\GeneralSettings;
use App\Settings\SocialSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.settings';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $general = app(GeneralSettings::class);
        $cif = app(CifSettings::class);
        $social = app(SocialSettings::class);

        $this->form->fill([
            'general' => [
                'site_name' => $general->site_name,
                'contact_email' => $general->contact_email,
                'contact_phone' => $general->contact_phone,
                'whatsapp_number' => $general->whatsapp_number,
            ],
            'cif' => [
                'insurance_pct_display' => $cif->insurance_pct * 100, // shown as %
                'default_currency' => $cif->default_currency,
                'price_on_request_default' => $cif->price_on_request_default,
            ],
            'social' => [
                'facebook_enabled' => $social->facebook_enabled,
                'facebook_page_id' => $social->facebook_page_id,
                // The token is decrypted at read-time; mask it in the UI so it
                // isn't shoulder-surfed but is still editable by re-typing.
                'facebook_page_access_token' => $social->facebook_page_access_token,
                'facebook_post_template' => $social->facebook_post_template,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Section::make('Site identity & contact')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('general.site_name')->label('Site name')->required(),
                                        TextInput::make('general.contact_email')->label('Contact email')->email()->required(),
                                        TextInput::make('general.contact_phone')->label('Contact phone'),
                                        TextInput::make('general.whatsapp_number')->label('WhatsApp number'),
                                    ]),
                            ]),
                        Tab::make('CIF calculator')
                            ->schema([
                                Section::make('Default insurance & currency')
                                    ->description('These defaults apply when a destination port has no explicit override.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('cif.insurance_pct_display')
                                            ->label('Insurance %')
                                            ->numeric()
                                            ->step('0.01')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->helperText('Stored internally as a fraction (e.g. 1.5% → 0.015).')
                                            ->required(),
                                        TextInput::make('cif.default_currency')
                                            ->label('Default currency')
                                            ->maxLength(3)
                                            ->required(),
                                    ]),
                            ]),
                        Tab::make('Social media')
                            ->schema([
                                Section::make('Facebook page')
                                    ->description('Posts a vehicle to your Facebook page on publish. Use a long-lived Page Access Token from Meta Graph API Explorer with the `pages_manage_posts` permission.')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('social.facebook_enabled')
                                            ->label('Enable Facebook sharing')
                                            ->columnSpanFull(),
                                        TextInput::make('social.facebook_page_id')
                                            ->label('Page ID')
                                            ->placeholder('e.g. 123456789012345'),
                                        TextInput::make('social.facebook_page_access_token')
                                            ->label('Page access token')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('off')
                                            ->helperText('Encrypted at rest. Paste a long-lived Page token.'),
                                        Textarea::make('social.facebook_post_template')
                                            ->label('Post template')
                                            ->rows(8)
                                            ->columnSpanFull()
                                            ->helperText('Placeholders: {title} {ref_no} {year} {mileage} {engine_cc} {price} {url}'),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $general = app(GeneralSettings::class);
        $general->site_name = $state['general']['site_name'];
        $general->contact_email = $state['general']['contact_email'];
        $general->contact_phone = $state['general']['contact_phone'] ?? null;
        $general->whatsapp_number = $state['general']['whatsapp_number'] ?? null;
        $general->save();

        $cif = app(CifSettings::class);
        $cif->insurance_pct = ((float) $state['cif']['insurance_pct_display']) / 100;
        $cif->default_currency = strtoupper((string) $state['cif']['default_currency']);
        $cif->price_on_request_default = (bool) ($state['cif']['price_on_request_default'] ?? false);
        $cif->save();

        $social = app(SocialSettings::class);
        $social->facebook_enabled = (bool) ($state['social']['facebook_enabled'] ?? false);
        $social->facebook_page_id = $state['social']['facebook_page_id'] ?: null;
        $social->facebook_page_access_token = $state['social']['facebook_page_access_token'] ?: null;
        $social->facebook_post_template = (string) ($state['social']['facebook_post_template'] ?? '');
        $social->save();

        Notification::make()->title('Settings saved.')->success()->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->action('save')
                ->keyBindings(['mod+s'])
                ->color('primary'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['super_admin', 'admin']);
    }
}
