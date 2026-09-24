<?php

namespace App\Modules\Mailer\Filament\Resources\Banners;

use App\Modules\Mailer\Domain\Campaigns\BannerImages;
use App\Modules\Mailer\Models\Banner;
use App\Modules\Mailer\Support\MailerAccess;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** S8 Banner library (TOC-BAN-001 to 003). Marketers and Admins. */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $slug = 'mailer/banners';

    protected static ?string $navigationLabel = 'Banners';

    protected static ?string $modelLabel = 'banner';

    protected static string|\UnitEnum|null $navigationGroup = 'Mailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return MailerAccess::canUse();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Banner image')
                ->helperText('JPG or PNG, exactly 1200 × 440 pixels, up to 1 MB. It is shown 600 × 220 in the email and saved smaller automatically.')
                ->image()
                ->disk('public')
                ->directory('email-assets/banners/uploads')
                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                ->maxSize(BannerImages::MAX_UPLOAD_KB)
                ->required()
                ->hiddenOn('edit')
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $file = $value instanceof TemporaryUploadedFile ? $value->getRealPath() : null;
                    if ($file && ($problem = BannerImages::problem($file))) {
                        $fail($problem);
                    }
                }),
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('link_url')
                ->label('Link when clicked')
                ->url()
                ->placeholder('https://tocojapan.com/vehicles')
                ->helperText('Leave empty to link to the stock list.'),
            TextInput::make('alt_text')
                ->label('Text shown when images are off')
                ->helperText('Say what the banner says, e.g. "Hot deals from Japan: price cuts on 40+ vehicles".')
                ->required()
                ->maxLength(255),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('campaigns'))
            ->defaultSort('created_at', 'desc')
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                Stack::make([
                    ImageColumn::make('path')->disk('public')->imageWidth('100%')->imageHeight('auto')->extraImgAttributes(['alt' => '', 'style' => 'aspect-ratio:1200/440;width:100%;object-fit:cover;']),
                    TextColumn::make('name')->weight('bold')->searchable(),
                    TextColumn::make('link_url')->placeholder('Links to the stock list')->limit(50)->color('gray'),
                    TextColumn::make('campaigns_count')->formatStateUsing(fn ($state) => 'Used in '.$state.' campaign'.($state == 1 ? '' : 's'))->color('gray'),
                    TextColumn::make('archived_at')->formatStateUsing(fn ($state) => $state ? 'Archived' : null)->badge()->color('gray'),
                ])->space(2),
            ])
            ->filters([
                TernaryFilter::make('archived')
                    ->label('Show')
                    ->placeholder('Active banners')
                    ->trueLabel('Archived banners')
                    ->falseLabel('All banners')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('archived_at'),
                        false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query->whereNull('archived_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('archive')
                    ->label(fn (Banner $r) => $r->archived_at ? 'Restore' : 'Archive')
                    ->icon(fn (Banner $r) => $r->archived_at ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->action(fn (Banner $r) => $r->forceFill(['archived_at' => $r->archived_at ? null : now()])->save()),
            ])
            ->emptyStateHeading('No banners yet')
            ->emptyStateDescription('Upload a 1200 × 440 banner to use at the top of your campaigns.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
