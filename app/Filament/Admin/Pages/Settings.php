<?php

namespace App\Filament\Admin\Pages;

use App\Settings\CifSettings;
use App\Settings\GeneralSettings;
use App\Settings\ImageSettings;
use App\Settings\PaymentSettings;
use App\Settings\SocialSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
        $image = app(ImageSettings::class);
        $payment = app(PaymentSettings::class);

        $this->form->fill([
            'general' => [
                'site_name' => $general->site_name,
                'contact_email' => $general->contact_email,
                'contact_phone' => $general->contact_phone,
                'whatsapp_number' => $general->whatsapp_number,
                'header_logo' => $general->header_logo,
                'footer_logos' => $general->footer_logos,
                'show_stock_counts' => $general->show_stock_counts,
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
            'image' => [
                'max_width' => $image->max_width,
                'webp_quality' => $image->webp_quality,
                'watermark_enabled' => $image->watermark_enabled,
                'watermark_image_path' => $image->watermark_image_path,
                'watermark_position' => $image->watermark_position,
                'watermark_opacity' => $image->watermark_opacity,
                'watermark_width_pct' => $image->watermark_width_pct,
            ],
            'payment' => [
                'paypal_enabled' => $payment->paypal_enabled,
                'bank_transfer_enabled' => $payment->bank_transfer_enabled,
                'bank_account_details' => $payment->bank_account_details,
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
                                Section::make('Stock display')
                                    ->description('Controls how stock numbers are shown across the storefront.')
                                    ->schema([
                                        Toggle::make('general.show_stock_counts')
                                            ->label('Show per-make / per-body-type stock totals')
                                            ->helperText('When off, the (N) counts next to makes and body types are hidden — useful while stock is low.'),
                                    ]),
                                Section::make('Header logo')
                                    ->description('Logo shown in the sticky site header. Leave empty to fall back to the TJ chip + wordmark.')
                                    ->schema([
                                        FileUpload::make('general.header_logo')
                                            ->label('Header logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->maxSize(1024)
                                            ->acceptedFileTypes(['image/png', 'image/webp', 'image/svg+xml'])
                                            ->helperText('PNG/WebP/SVG. ~40-48px tall renders well.'),
                                    ]),
                                Section::make('Footer logos')
                                    ->description('Brand and certification logos shown in the site footer. Drag to reorder.')
                                    ->schema([
                                        Repeater::make('general.footer_logos')
                                            ->label('')
                                            ->reorderable()
                                            ->reorderableWithDragAndDrop()
                                            ->columns(3)
                                            ->itemLabel(fn (array $state): ?string => $state['alt'] ?? null)
                                            ->schema([
                                                FileUpload::make('image')
                                                    ->label('Image')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('footer-logos')
                                                    ->maxSize(1024)
                                                    ->columnSpan(1)
                                                    ->required(),
                                                TextInput::make('alt')
                                                    ->label('Alt text')
                                                    ->columnSpan(1),
                                                TextInput::make('link')
                                                    ->label('Link (optional)')
                                                    ->url()
                                                    ->columnSpan(1),
                                            ])
                                            ->addActionLabel('Add a logo')
                                            ->defaultItems(0),
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
                        Tab::make('Vehicle images')
                            ->schema([
                                Section::make('Resize & format')
                                    ->description('Uploaded vehicle photos are converted to WebP and resized to fit. Set to a sensible width for fast page loads.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('image.max_width')
                                            ->label('Max width (px)')
                                            ->numeric()
                                            ->minValue(400)
                                            ->maxValue(4096)
                                            ->required(),
                                        TextInput::make('image.webp_quality')
                                            ->label('WebP quality')
                                            ->numeric()
                                            ->minValue(40)
                                            ->maxValue(100)
                                            ->suffix('1-100')
                                            ->required(),
                                    ]),
                                Section::make('Watermark')
                                    ->description('Stamps the watermark onto every uploaded photo. Use a PNG with transparency for best results.')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('image.watermark_enabled')
                                            ->label('Apply watermark on upload')
                                            ->columnSpanFull(),
                                        FileUpload::make('image.watermark_image_path')
                                            ->label('Watermark image (PNG)')
                                            ->image()
                                            ->disk('public')
                                            ->directory('watermarks')
                                            ->maxSize(2048)
                                            ->acceptedFileTypes(['image/png', 'image/webp'])
                                            ->columnSpanFull()
                                            ->helperText('Stored under storage/app/public/watermarks. PNG with transparency recommended.'),
                                        Select::make('image.watermark_position')
                                            ->label('Position')
                                            ->options([
                                                'top-left' => 'Top left',
                                                'top-center' => 'Top center',
                                                'top-right' => 'Top right',
                                                'center' => 'Center',
                                                'bottom-left' => 'Bottom left',
                                                'bottom-center' => 'Bottom center',
                                                'bottom-right' => 'Bottom right',
                                            ])
                                            ->required(),
                                        TextInput::make('image.watermark_width_pct')
                                            ->label('Width (% of photo)')
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->required(),
                                        TextInput::make('image.watermark_opacity')
                                            ->label('Opacity')
                                            ->numeric()
                                            ->minValue(10)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->required(),
                                    ]),
                                Section::make('Reprocess existing photos')
                                    ->description('Run `php artisan vehicles:reprocess-images` from the server (or via a queue worker) to apply current settings to all existing vehicle photos.')
                                    ->schema([]),
                            ]),
                        Tab::make('Payments')
                            ->schema([
                                Section::make('PayPal (USD)')
                                    ->description('When enabled AND PAYPAL_* env keys are present, a "Buy now" button appears on every priced vehicle.')
                                    ->schema([
                                        Toggle::make('payment.paypal_enabled')->label('Enable PayPal checkout'),
                                    ]),
                                Section::make('Bank transfer')
                                    ->description('When enabled, a "Buy with bank transfer" button appears alongside PayPal. After clicking, the customer sees these account details and is asked to reference the order number on the transfer.')
                                    ->schema([
                                        Toggle::make('payment.bank_transfer_enabled')->label('Enable bank transfer checkout')->columnSpanFull(),
                                        Textarea::make('payment.bank_account_details')
                                            ->label('Account details shown to customers')
                                            ->rows(8)
                                            ->placeholder("Account name: Toco Japan Co., Ltd.\nBank name: ...\nBranch: ...\nAccount no.: ...\nSWIFT / BIC: ...")
                                            ->columnSpanFull(),
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
        $general->header_logo = $state['general']['header_logo'] ?: null;
        $general->footer_logos = array_values(array_filter(
            $state['general']['footer_logos'] ?? [],
            fn ($l) => ! empty($l['image'])
        ));
        $general->show_stock_counts = (bool) ($state['general']['show_stock_counts'] ?? false);
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

        $image = app(ImageSettings::class);
        $image->max_width = (int) $state['image']['max_width'];
        $image->webp_quality = (int) $state['image']['webp_quality'];
        $image->watermark_enabled = (bool) ($state['image']['watermark_enabled'] ?? false);
        $image->watermark_image_path = $state['image']['watermark_image_path'] ?: null;
        $image->watermark_position = (string) ($state['image']['watermark_position'] ?? 'bottom-right');
        $image->watermark_opacity = (int) $state['image']['watermark_opacity'];
        $image->watermark_width_pct = (int) $state['image']['watermark_width_pct'];
        $image->save();

        $payment = app(PaymentSettings::class);
        $payment->paypal_enabled = (bool) ($state['payment']['paypal_enabled'] ?? false);
        $payment->bank_transfer_enabled = (bool) ($state['payment']['bank_transfer_enabled'] ?? false);
        $payment->bank_account_details = (string) ($state['payment']['bank_account_details'] ?? '');
        $payment->save();

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
