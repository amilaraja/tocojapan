<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners;

use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\VehicleHold;
use App\Modules\PartnerApi\Domain\Stock\PartnerPricing;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Models\Partner;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Partner settings in plain words for a non-technical owner (UI brief 2.3).
 * The same sections are wizard steps on create and tabs on edit.
 */
class PartnerForm
{
    /** @return array<string, array<int, mixed>> section label => components */
    public static function sections(): array
    {
        return [
            'Partner' => self::partner(),
            'What they see' => self::scope(),
            'Prices' => self::prices(),
            'Photos' => self::photos(),
            'Limits' => self::limits(),
            'Holds' => self::holds(),
            'Contract' => self::contract(),
        ];
    }

    private static function partner(): array
    {
        return [
            Radio::make('type')
                ->label('Partner type')
                ->options([
                    Partner::TYPE_LISTING => 'Listing partner: shows your stock and sends buyers to you',
                    Partner::TYPE_SELLING => 'Selling partner: may reserve a car while their own buyer pays',
                ])
                ->default(Partner::TYPE_LISTING)
                ->required()
                ->live()
                ->helperText('Most partners are listing partners. Choose selling only when the partner takes money from buyers.')
                ->rule(fn (?Partner $record) => self::typeChangeRule($record)),
            TextInput::make('name')->label('Partner name')->required()->maxLength(120)
                ->helperText('As your staff know them, for example "JWT Japan".'),
            Select::make('country')->label('Country')->searchable()
                ->options(fn () => Country::query()->orderBy('name')->pluck('name', 'iso2')->all())
                ->helperText('Where the partner is based.'),
            TextInput::make('contact_name')->label('Contact person')->maxLength(120)
                ->helperText('Who you talk to about the connection.'),
            TextInput::make('contact_email')->label('Contact email')->email()->maxLength(190)
                ->helperText('For connection problems, for example tech@partner.com.'),
            Textarea::make('notes')->label('Internal notes')->rows(3)
                ->helperText('Only your staff see this.'),
        ];
    }

    private static function scope(): array
    {
        return [
            Text::make('Leave everything empty to share all of your own stock. Each filter you fill narrows it; all filters apply together.'),
            Select::make('stock_scope.makes')->label('Only these makes')->multiple()->searchable()->live()
                ->options(fn () => Make::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->helperText('For example only Toyota and Nissan. Empty means every make.'),
            Select::make('stock_scope.body_types')->label('Only these body types')->multiple()->live()
                ->options(fn () => BodyType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->helperText('For example only vans and trucks. Empty means every body type.'),
            TextInput::make('stock_scope.year_min')->label('Oldest year')->numeric()->minValue(1950)->maxValue(2100)->live(debounce: 600)
                ->helperText('For example 2015 hides cars registered before 2015.'),
            TextInput::make('stock_scope.year_max')->label('Newest year')->numeric()->minValue(1950)->maxValue(2100)->live(debounce: 600),
            TextInput::make('stock_scope.price_min')->label('Lowest price (USD, your price)')->numeric()->minValue(0)->live(debounce: 600),
            TextInput::make('stock_scope.price_max')->label('Highest price (USD, your price)')->numeric()->minValue(0)->live(debounce: 600)
                ->helperText('For example 20000 shares only cars up to USD 20,000 before any markup.'),
            Text::make(fn (Get $get) => self::scopePreview($get)),
            Radio::make('field_set')->label('Vehicle details shared')
                ->options([
                    'standard' => 'Standard: make, model, year, mileage, engine, price, photos and similar',
                    'extended' => 'Extended: also chassis number, model code, dimensions, features and description',
                ])
                ->default('standard')->required()
                ->helperText('Share the extended details only with partners who need them, for example for import paperwork.'),
        ];
    }

    private static function prices(): array
    {
        return [
            Radio::make('price_mode')->label('Prices the partner shows')
                ->options([
                    PartnerPricing::AS_IS => 'Toco price as it is',
                    PartnerPricing::PERCENT => 'Toco price plus a percentage',
                    PartnerPricing::FIXED => 'Toco price plus a fixed amount',
                    PartnerPricing::ON_REQUEST => 'Price on request (no prices shown)',
                ])
                ->default(PartnerPricing::AS_IS)->required()->live()
                ->helperText("The partner's buyers see the final price only. Your base price is never sent."),
            TextInput::make('price_markup')
                ->label(fn (Get $get) => $get('price_mode') === PartnerPricing::PERCENT ? 'Markup (%)' : 'Markup (USD)')
                ->numeric()->minValue(0)->maxValue(fn (Get $get) => $get('price_mode') === PartnerPricing::PERCENT ? 100 : 100000)
                ->required(fn (Get $get) => in_array($get('price_mode'), [PartnerPricing::PERCENT, PartnerPricing::FIXED], true))
                ->visible(fn (Get $get) => in_array($get('price_mode'), [PartnerPricing::PERCENT, PartnerPricing::FIXED], true))
                ->helperText(fn (Get $get) => $get('price_mode') === PartnerPricing::PERCENT
                    ? 'For example 8 turns USD 10,000 into USD 10,800.'
                    : 'For example 500 turns USD 10,000 into USD 10,500.'),
        ];
    }

    private static function photos(): array
    {
        return [
            Text::make('All partner photos carry the Toco watermark.'),
            Select::make('image_max_width')->label('Largest photo size')
                ->options([640 => 'Small (640 pixels wide)', 1024 => 'Medium (1024 pixels wide)', 1280 => 'Large (1280 pixels wide)'])
                ->default(1280)->required()
                ->helperText('Smaller photos are less useful to anyone copying them.'),
            Toggle::make('image_ref_pill')->label('Show the "Toco Ref#" label on photos')->default(true)
                ->helperText('Today every photo carries the label. Turning this off takes effect once clean-photo delivery is switched on for new photos.'),
            Radio::make('image_delivery')->label('How the partner uses photos')
                ->options([
                    'rehost' => 'They download photos and host them on their own site (recommended)',
                    'hosted' => 'They show photos straight from Toco (links last 24 hours)',
                ])
                ->default('rehost')->required()
                ->helperText('Choose the second only for partners who cannot store photos themselves.'),
            TextInput::make('image_daily_limit')->label('Photo downloads per day')->numeric()->minValue(0)->required()
                ->default(config('partner_api.defaults.image_daily_limit'))
                ->helperText('For example 50000. Resets at midnight Japan time.'),
        ];
    }

    private static function limits(): array
    {
        return [
            TextInput::make('rate_per_minute')->label('Requests per minute')->numeric()->minValue(1)->required()
                ->default(config('partner_api.defaults.rate_per_minute'))
                ->helperText('How fast their system may ask for data, for example 120.'),
            TextInput::make('rate_per_day')->label('Requests per day')->numeric()->minValue(1)->required()
                ->default(config('partner_api.defaults.rate_per_day'))
                ->helperText('For example 20000. Resets at midnight Japan time.'),
            TagsInput::make('ip_allowlist')->label('Only accept calls from these server addresses (optional)')
                ->placeholder('Add an address and press Enter')
                ->helperText('Ask the partner for their server address, for example 203.0.113.10 or a range like 203.0.113.0/24. Leave empty to accept any address.')
                ->nestedRecursiveRules([fn () => function (string $attribute, mixed $value, Closure $fail) {
                    if (! self::isIpOrRange((string) $value)) {
                        $fail("\"{$value}\" is not a server address. Use a form like 203.0.113.10 or 203.0.113.0/24.");
                    }
                }]),
        ];
    }

    private static function holds(): array
    {
        $selling = fn (Get $get) => $get('type') === Partner::TYPE_SELLING;

        return [
            Text::make('Listing partners never hold cars. These settings apply to selling partners only.')
                ->visible(fn (Get $get) => ! $selling($get)),
            TextInput::make('max_active_holds')->label('Cars they may hold at once')->numeric()->minValue(1)->maxValue(50)
                ->default(config('partner_api.defaults.max_active_holds'))->visible($selling)->required($selling)
                ->helperText('For example 5. Stops one partner blocking too much stock.'),
            TextInput::make('hold_hours')->label('How long a hold lasts (hours)')->numeric()
                ->minValue(config('partner_api.hold_hours_min'))->maxValue(config('partner_api.hold_hours_max'))
                ->default(config('partner_api.defaults.hold_hours'))->visible($selling)->required($selling)
                ->helperText('Between 24 and 96. For example 48 gives their buyer two days to pay.'),
        ];
    }

    private static function contract(): array
    {
        return [
            DatePicker::make('contract_starts_on')->label('Contract start')
                ->helperText('Optional. Their keys do not work before this date.'),
            DatePicker::make('contract_ends_on')->label('Contract end')->afterOrEqual('contract_starts_on')
                ->helperText('Optional. Their keys stop working after this date.'),
            TextInput::make('attribution_code')->label('Attribution code')->disabled()->dehydrated(false)
                ->copyable()->visibleOn('edit')
                ->helperText('Added to links back to tocojapan.com so inquiries are credited to this partner.'),
            TextInput::make('webhook_url')->label('Change notification address (optional)')->url()->maxLength(255)
                ->rule('starts_with:https://')
                ->validationMessages(['starts_with' => 'The address must start with https://'])
                ->helperText("The partner's address for instant updates when a car is sold or changes, for example https://partner.com/toco-updates. Leave empty if they only check on a schedule."),
        ];
    }

    private static function scopePreview(Get $get): string
    {
        $partner = new Partner(['stock_scope' => array_filter((array) $get('stock_scope'), fn ($value) => $value !== null && $value !== '' && $value !== [])]);
        $matches = PartnerScope::listable($partner)->count();
        $total = PartnerScope::listable(new Partner)->count();

        return "Matches {$matches} of {$total} vehicles on sale now.";
    }

    /** Selling to listing is refused while the partner holds cars (FR-PRT-04). */
    private static function typeChangeRule(?Partner $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record) {
            if (! $record || $record->type !== Partner::TYPE_SELLING || $value !== Partner::TYPE_LISTING) {
                return;
            }
            $active = VehicleHold::active()->where('partner_id', $record->id)->count();
            if ($active > 0) {
                $fail("This partner holds {$active} car(s) right now. Release those holds, or wait for them to end, before making it a listing partner.");
            }
        };
    }

    public static function isIpOrRange(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', trim($value), 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($bits === null) {
            return true;
        }
        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits <= $max && IpUtils::checkIp($ip, $value) !== null;
    }
}
