<?php

namespace App\Filament\Admin\Resources\Suppliers;

use App\Filament\Admin\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Admin\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Admin\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use App\Settings\StockSettings;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['super_admin', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        $feed = fn (Get $get) => filled($get('feed_format')) || ! $get('is_own_stock');

        return $schema->components([
            Section::make('Supplier')
                ->columns(3)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true)
                        ->helperText('Used in links, e.g. /vehicles?supplier=oneprice'),
                    TextInput::make('stock_prefix')->label('Stock no. prefix')->maxLength(10)
                        ->helperText('OP → stock no. OP-29225'),
                    Select::make('feed_format')
                        ->label('Stock file format')
                        ->options(['oneprice_csv' => 'OnePrice CSV'])
                        ->placeholder('None — vehicles added by hand')
                        ->live(),
                    TextInput::make('sort_priority')->label('Listing priority')->numeric()->minValue(0)->default(100)
                        ->helperText('Lower is listed first on /vehicles. Own stock is 0.'),
                    Toggle::make('is_active')->label('Active')->inline(false)->default(true),
                    Toggle::make('is_own_stock')->label('Toco own stock')->disabled()->dehydrated(false)->inline(false),
                ]),

            Section::make('Pricing')
                ->description('FOB (USD) = supplier price ÷ exchange rate × (1 + margin %) + fixed margin, rounded up. Leave the margin empty to use the site default (Site settings → Supplier stock). Each import can also set its own margin.')
                ->columns(3)
                ->visible($feed)
                ->schema([
                    Select::make('settings.price_basis')
                        ->label('Price to use')
                        ->options(['retail' => 'Retail price', 'wholesale' => 'Wholesale price'])
                        ->default('retail')->required(),
                    Toggle::make('settings.fallback_to_wholesale')
                        ->label('Use wholesale price when retail is missing')->inline(false)
                        ->helperText('Off: vehicles without the chosen price show "On request".'),
                    Select::make('settings.source_currency')
                        ->label('Supplier currency')
                        ->options(fn () => DB::table('currencies')->orderBy('sort_order')->pluck('code', 'code')->all())
                        ->default('JPY')->required(),
                    TextInput::make('settings.margin_percent')->label('Profit margin %')->numeric()->minValue(0)->suffix('%')
                        ->placeholder(fn () => 'Site default: '.app(StockSettings::class)->supplier_margin_percent.'%'),
                    TextInput::make('settings.margin_fixed_usd')->label('Fixed profit per vehicle')->numeric()->minValue(0)->prefix('$')
                        ->placeholder(fn () => 'Site default: $'.app(StockSettings::class)->supplier_margin_fixed_usd)
                        ->helperText('Added after the % margin.'),
                    TextInput::make('settings.round_to')->label('Round up to (USD)')->numeric()->minValue(1)->default(10),
                    TextInput::make('settings.max_source_price')->label('Treat prices at or above this as "On request"')->numeric()
                        ->default(Supplier::DEFAULT_SETTINGS['max_source_price'])
                        ->helperText('Suppliers use placeholder prices like 99,999,000 for "ask".'),
                    Html::make(fn (?Supplier $record) => self::pricingExample($record))->columnSpan(2),
                ]),

            Section::make('Publishing')
                ->columns(2)
                ->visible($feed)
                ->schema([
                    Toggle::make('settings.auto_publish_new')->label('Publish new vehicles straight away')
                        ->helperText('Off: new vehicles arrive as drafts for review.')->default(true),
                    Toggle::make('settings.allow_online_checkout')->label('Allow PayPal / bank-transfer checkout')
                        ->helperText('Off: customers request a quote, so sales confirms availability with the supplier first.'),
                    Toggle::make('settings.show_on_homepage')->label('Show in homepage "latest" strip'),
                    Toggle::make('settings.in_sitemap')->label('Include in the vehicle sitemap')
                        ->helperText('Google allows 50,000 links per sitemap — leave off for very large feeds.'),
                ]),

            Section::make('Safety & clean-up')
                ->columns(2)
                ->visible($feed)
                ->schema([
                    TextInput::make('settings.delist_guard_percent')->label('Ask before delisting more than')->numeric()->suffix('% of live stock')->default(30)
                        ->helperText('Protects against a cut-off or wrong file wiping the stock.'),
                    TextInput::make('settings.purge_delisted_after_days')->label('Remove delisted vehicles after')->numeric()->suffix('days')->default(180)
                        ->helperText('Vehicles with orders, quotes or favourites are always kept. 0 = never remove.'),
                ]),
        ]);
    }

    protected static function pricingExample(?Supplier $record): string
    {
        if (! $record) {
            return '';
        }
        $rate = $record->sourceRateToUsd();
        $example = 1_000_000;
        $fob = $record->fobFromSource((float) $example, $rate);
        $m = $record->effectiveMargin();

        return '<div class="text-sm text-gray-500">Example with the saved settings (margin '.$m['percent'].'% + $'.number_format($m['fixed_usd']).'): '
            .e($record->setting('source_currency')).' '.number_format($example)
            .' → <strong>$'.($fob !== null ? number_format($fob) : '—').'</strong> FOB'
            .($rate ? ' (today\'s rate '.number_format($rate, 2).' per USD)' : ' (no exchange rate found)').'</div>';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_priority')
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('slug')->color('gray'),
                IconColumn::make('is_own_stock')->label('Own stock')->boolean(),
                TextColumn::make('feed_format')->label('File format')->placeholder('Manual'),
                TextColumn::make('live_count')->label('Live')
                    ->state(fn (Supplier $r) => $r->vehicles()->where('status', 'published')->count())->numeric(),
                TextColumn::make('delisted_count')->label('Delisted')
                    ->state(fn (Supplier $r) => $r->vehicles()->where('status', 'delisted')->count())->numeric(),
                TextColumn::make('last_import')->label('Last import')
                    ->state(fn (Supplier $r) => $r->imports()->where('status', 'completed')->latest('finished_at')->value('finished_at'))
                    ->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('sort_priority')->label('Priority')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
