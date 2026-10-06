<?php

namespace App\Filament\Admin\Resources\SupplierImports;

use App\Filament\Admin\Resources\SupplierImports\Pages\CreateSupplierImport;
use App\Filament\Admin\Resources\SupplierImports\Pages\ListSupplierImports;
use App\Filament\Admin\Resources\SupplierImports\Pages\ViewSupplierImport;
use App\Models\Supplier;
use App\Models\SupplierImport;
use App\Suppliers\SupplierImporter;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class SupplierImportResource extends Resource
{
    protected static ?string $model = SupplierImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Stock imports';

    protected static ?string $modelLabel = 'stock import';

    protected static ?int $navigationSort = 3;

    /** Files dropped here by SFTP / File Manager can be imported without a browser upload. */
    public const INBOX_DIR = 'supplier-inbox';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['super_admin', 'admin', 'sales']);
    }

    public static function inboxPath(Supplier $supplier): string
    {
        return Storage::disk('local')->path(self::INBOX_DIR.'/'.$supplier->slug);
    }

    /** @return array<string, string> file name => label with size and date */
    public static function inboxFiles(?Supplier $supplier): array
    {
        if (! $supplier) {
            return [];
        }
        $dir = self::inboxPath($supplier);
        if (! is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (glob($dir.'/*.{csv,CSV,zip,ZIP,gz,GZ,txt,TXT}', GLOB_BRACE) ?: [] as $path) {
            $files[basename($path)] = basename($path).'  ('.number_format(filesize($path) / 1_048_576, 1).' MB, '.date('Y-m-d H:i', (int) filemtime($path)).')';
        }
        arsort($files);

        return $files;
    }

    public static function form(Schema $schema): Schema
    {
        $supplier = fn (Get $get) => $get('supplier_id') ? Supplier::query()->find($get('supplier_id')) : null;

        return $schema->components([
            Section::make('Stock file')
                ->columns(1)
                ->schema([
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(fn () => Supplier::query()->whereNotNull('feed_format')->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => Supplier::query()->where('slug', 'oneprice')->value('id'))
                        ->required()
                        ->live(),
                    Html::make(function (Get $get) use ($supplier) {
                        $s = $supplier($get);
                        if (! $s?->feed_format) {
                            return '';
                        }

                        return '<p class="text-sm text-gray-500">'.e(SupplierImporter::feedFor($s)::describe()).'</p>';
                    }),
                    Radio::make('source')
                        ->label('Where is the file?')
                        ->options([
                            'upload' => 'Upload from this computer',
                            'inbox' => 'Already on the server (inbox folder)',
                        ])
                        ->default('upload')
                        ->live()
                        ->required(),
                    FileUpload::make('upload')
                        ->label('File')
                        ->disk('local')
                        ->directory('supplier-imports/uploads')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/zip', 'application/x-zip-compressed', 'application/gzip', 'application/x-gzip'])
                        ->helperText('CSV, or the same CSV zipped (.zip / .gz) — zipping makes the file about 10× smaller. If the file is too big to upload, put it in the inbox folder instead.')
                        ->visible(fn (Get $get) => $get('source') === 'upload')
                        ->required(fn (Get $get) => $get('source') === 'upload'),
                    Select::make('inbox_file')
                        ->label('File in the inbox')
                        ->options(fn (Get $get) => self::inboxFiles($supplier($get)))
                        ->visible(fn (Get $get) => $get('source') === 'inbox')
                        ->required(fn (Get $get) => $get('source') === 'inbox')
                        ->helperText(fn (Get $get) => ($s = $supplier($get))
                            ? 'Upload the file by SFTP / File Manager to: '.self::inboxPath($s)
                            : null),
                    Radio::make('mode')
                        ->label('What does this file contain?')
                        ->options([
                            'full' => 'The supplier\'s complete current stock',
                            'partial' => 'Only some vehicles (new arrivals / price updates)',
                        ])
                        ->descriptions([
                            'full' => 'Vehicles missing from the file are taken off the site (delisted). Their pages redirect to similar stock and come back automatically if the vehicle reappears in a later file.',
                            'partial' => 'Adds and updates vehicles only. Nothing is delisted.',
                        ])
                        ->default('full')
                        ->required(),
                ]),
            Section::make('Profit margin for this import')
                ->description(fn (Get $get) => ($s = $supplier($get))
                    ? 'Leave empty to use the '.$s->name.' margin: '.self::marginLabel($s->effectiveMargin()).'.'
                    : null)
                ->columns(2)
                ->schema([
                    TextInput::make('margin_percent')->label('Margin %')->numeric()->minValue(0)->suffix('%')
                        ->placeholder(fn (Get $get) => ($s = $supplier($get)) ? (string) $s->effectiveMargin()['percent'] : null),
                    TextInput::make('margin_fixed_usd')->label('Fixed profit per vehicle')->numeric()->minValue(0)->prefix('$')
                        ->placeholder(fn (Get $get) => ($s = $supplier($get)) ? (string) $s->effectiveMargin()['fixed_usd'] : null),
                ]),
        ]);
    }

    /** @param  array{percent: float, fixed_usd: float}  $m */
    public static function marginLabel(array $m): string
    {
        return rtrim(rtrim(number_format($m['percent'], 2), '0'), '.').'% + $'.number_format($m['fixed_usd']).' per vehicle';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll(fn () => SupplierImport::query()->whereIn('status', ['queued', 'staging', 'previewing', 'applying', 'delisting'])->exists() ? '5s' : null)
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'user']))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('created_at')->label('Uploaded')->dateTime('Y-m-d H:i'),
                TextColumn::make('supplier.name')->label('Supplier'),
                TextColumn::make('original_name')->label('File')->limit(30),
                TextColumn::make('mode')->formatStateUsing(fn (string $state) => $state === 'full' ? 'Complete stock' : 'Partial'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SupplierImport $r) => $r->statusLabel())
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'previewed' => 'warning',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('valid_rows')->label('Vehicles')->numeric(),
                TextColumn::make('new')->label('New')->state(fn (SupplierImport $r) => $r->stat('applied', [])['new'] ?? $r->stat('counts', [])['new'] ?? 0)->numeric(),
                TextColumn::make('updated')->label('Updated')->state(fn (SupplierImport $r) => $r->stat('applied', [])['update'] ?? $r->stat('counts', [])['update'] ?? 0)->numeric(),
                TextColumn::make('delisted')->label('Delisted')->state(fn (SupplierImport $r) => $r->stat('applied', [])['delisted'] ?? $r->stat('to_delist'))->numeric(),
                TextColumn::make('error_rows')->label('Errors')->numeric()->color(fn ($state) => $state > 0 ? 'danger' : null),
                TextColumn::make('user.name')->label('By')->placeholder('Command line'),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name'),
                SelectFilter::make('status')->options(SupplierImport::STATUS_LABELS),
            ])
            ->recordActions([ViewAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierImports::route('/'),
            'create' => CreateSupplierImport::route('/create'),
            'view' => ViewSupplierImport::route('/{record}'),
        ];
    }
}
