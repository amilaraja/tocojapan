<?php

namespace App\Modules\Mailer\Filament\Resources\Buyers;

use App\Modules\Mailer\Filament\Clusters\Buyers;
use App\Modules\Mailer\Filament\Resources\Buyers\Pages\ListBuyers;
use App\Modules\Mailer\Filament\Resources\Buyers\Pages\ViewBuyer;
use App\Modules\Mailer\Models\Buyer;
use App\Modules\Mailer\Models\BuyerEnquiry;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Buyer list and profiles (TOC-BUY-007). */
class BuyerResource extends Resource
{
    protected static ?string $model = Buyer::class;

    protected static ?string $cluster = Buyers::class;

    protected static ?string $slug = 'list';

    protected static ?string $navigationLabel = 'All buyers';

    protected static ?string $modelLabel = 'buyer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_enquiry_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('latestEnquiry'))
            ->searchPlaceholder('Search name, email or phone')
            ->columns([
                TextColumn::make('name')->label('Buyer')
                    ->state(fn (Buyer $r) => trim(($r->title ? $r->title.' ' : '').$r->fullName()) ?: '—')
                    ->description(fn (Buyer $r) => $r->email)
                    ->searchable(['first_name', 'last_name', 'email', 'phone', 'phone_e164']),
                TextColumn::make('country')->label('Country')
                    ->state(fn (Buyer $r) => $r->country ? $r->country.($r->country_code ? ' ('.$r->country_code.')' : '') : null)
                    ->description(fn (Buyer $r) => $r->port)->placeholder('—'),
                TextColumn::make('phone_e164')->label('Phone')->placeholder('—')->copyable()
                    ->url(fn (Buyer $r) => $r->whatsappUrl(), shouldOpenInNewTab: true),
                TextColumn::make('buyer_type')->label('Type')->badge()
                    ->formatStateUsing(fn (?string $state) => Buyer::TYPES[$state] ?? $state)
                    ->color(fn (?string $state) => $state === 'dealer' ? 'warning' : 'gray')->placeholder('—'),
                TextColumn::make('last_vehicle')->label('Last asked about')
                    ->state(fn (Buyer $r) => $r->latestEnquiry?->vehicleLabel() ?: null)->placeholder('—'),
                TextColumn::make('enquiry_count')->label('Enquiries')->numeric()->sortable(),
                TextColumn::make('last_enquiry_at')->label('Last enquiry')->date('j M Y')->sortable(),
                TextColumn::make('first_enquiry_at')->label('First enquiry')->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country_code')->label('Country')->searchable()
                    ->options(fn () => self::countryOptions()),
                SelectFilter::make('buyer_type')->label('Type')->options(Buyer::TYPES),
                SelectFilter::make('make')->label('Asked about make')->searchable()
                    ->options(fn () => BuyerEnquiry::query()->whereNotNull('make')->distinct()->orderBy('make')->pluck('make', 'make')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $query, $make) => $query->whereHas('enquiries', fn (Builder $e) => $e->where('make', $make)))),
                SelectFilter::make('kind')->label('Enquiry kind')->options(BuyerEnquiry::KINDS)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $query, $kind) => $query->whereHas('enquiries', fn (Builder $e) => $e->where('kind', $kind)))),
                SelectFilter::make('recent')->label('Last enquiry')
                    ->options(['30' => 'Last 30 days', '90' => 'Last 3 months', '180' => 'Last 6 months', '365' => 'Last 12 months'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $query, $days) => $query->where('last_enquiry_at', '>=', now()->subDays((int) $days)))),
                Filter::make('repeat')->label('Asked more than once')->toggle()
                    ->query(fn (Builder $query) => $query->where('enquiry_count', '>', 1)),
                Filter::make('whatsapp')->label('Has a valid phone number')->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('phone_e164')),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /** @return array<string, string> */
    public static function countryOptions(): array
    {
        return DB::table('mailer_buyers')
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->selectRaw('country_code, MAX(country) as name, COUNT(*) as n')
            ->orderByDesc('n')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->country_code => "{$r->name} ({$r->n})"])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuyers::route('/'),
            'view' => ViewBuyer::route('/{record}'),
        ];
    }
}
