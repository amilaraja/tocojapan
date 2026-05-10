<?php

namespace App\Filament\Admin\Pages;

use App\Settings\CifSettings;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
