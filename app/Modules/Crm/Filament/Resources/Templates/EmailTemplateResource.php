<?php

namespace App\Modules\Crm\Filament\Resources\Templates;

use App\Modules\Crm\Domain\TemplateRenderer;
use App\Modules\Crm\Models\CrmEmailTemplate;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Admin → CRM → Email templates (CRM-ACT-002, NTF-002). */
class EmailTemplateResource extends Resource
{
    protected static ?string $model = CrmEmailTemplate::class;

    protected static ?string $slug = 'templates';

    protected static ?string $modelLabel = 'email template';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Email templates';

    protected static ?int $navigationSort = 22;

    public static function canAccess(): bool
    {
        return CrmAccess::isAdmin();
    }

    public static function canDelete($record): bool
    {
        return ! $record->system;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('key')->label('Code')->required()->alphaDash()->maxLength(60)->unique(ignoreRecord: true)
                ->disabled(fn (?CrmEmailTemplate $record) => (bool) $record?->system),
            Toggle::make('system')->label('Sent automatically by the system')->disabled()->dehydrated(false),
            TextInput::make('subject')->required()->maxLength(255),
            Textarea::make('body')->required()->rows(14)
                ->helperText('Placeholders: {'.implode('} {', TemplateRenderer::PLACEHOLDERS).'}. The Case reference is added to every subject so replies find their Case.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (CrmEmailTemplate $r) => $r->subject),
                TextColumn::make('system')->label('Use')->badge()->formatStateUsing(fn ($state) => $state ? 'Automatic' : 'Staff')->color(fn ($state) => $state ? 'info' : 'gray'),
                TextColumn::make('updated_at')->since(),
            ])
            ->filters([TernaryFilter::make('system')->label('Automatic emails')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }
}
