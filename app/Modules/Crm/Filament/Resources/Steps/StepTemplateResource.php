<?php

namespace App\Modules\Crm\Filament\Resources\Steps;

use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Models\CrmStepTemplate;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Admin → CRM → Stages & steps: the checklist of each stage (CRM-STG-002, OPEN-03). */
class StepTemplateResource extends Resource
{
    protected static ?string $model = CrmStepTemplate::class;

    protected static ?string $slug = 'crm/steps';

    protected static ?string $modelLabel = 'mini step';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Stages & steps';

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return CrmAccess::isAdmin();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('stage')->options(collect(Crm::STAGES)->mapWithKeys(fn ($n, $i) => [$i => Crm::stageLabel($i)])->all())->required(),
            TextInput::make('key')->label('Code')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true)
                ->disabled(fn (?CrmStepTemplate $record) => $record !== null && in_array($record->key, array_column(\App\Modules\Crm\Support\CrmDefaults::STEPS, 'key'), true))
                ->helperText('Built-in steps keep their code: the system ticks them automatically.'),
            TextInput::make('label')->label('Name for staff')->required()->maxLength(190),
            TextInput::make('customer_label')->label('Name the customer sees')->maxLength(190),
            Toggle::make('required')->label('Must be done before the next stage')->default(true),
            Toggle::make('customer_visible')->label('Customer sees it in their tracker'),
            Select::make('responsible_role')->label('Done by')->options(['operations' => 'Operations', 'agent' => 'Owner'])->placeholder('Owner'),
            Select::make('condition')->label('Only when')->options(['supplier_stock' => 'Dealer stock', 'lc' => 'Paying by LC', 'inspection' => 'Destination needs inspection'])->placeholder('Always'),
            Repeater::make('fields')->label('Ask for this data when ticked')->columnSpanFull()->collapsible()->defaultItems(0)
                ->schema([
                    TextInput::make('name')->label('Code')->alphaDash()->required(),
                    TextInput::make('label')->required(),
                    Select::make('type')->options(['text' => 'Text', 'number' => 'Number', 'date' => 'Date', 'select' => 'Choice'])->default('text')->required()->live(),
                    Toggle::make('required'),
                    KeyValue::make('options')->label('Choices')->visible(fn (Get $get) => $get('type') === 'select'),
                ])->columns(4),
            Toggle::make('is_active')->label('In use')->default(true),
            TextInput::make('sort')->numeric()->default(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort(fn ($query) => $query->orderBy('stage')->orderBy('sort'))
            ->columns([
                TextColumn::make('stage')->formatStateUsing(fn ($state) => Crm::stageLabel((int) $state))->sortable(),
                TextColumn::make('label')->weight('bold')->description(fn (CrmStepTemplate $r) => $r->customer_label),
                IconColumn::make('required')->boolean(),
                IconColumn::make('customer_visible')->label('Customer sees')->boolean(),
                TextColumn::make('condition')->placeholder('Always')->formatStateUsing(fn ($state) => ['supplier_stock' => 'Dealer stock', 'lc' => 'LC', 'inspection' => 'Inspection'][$state] ?? $state),
                TextColumn::make('responsible_role')->label('By')->placeholder('Owner'),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->filters([SelectFilter::make('stage')->options(collect(Crm::STAGES)->mapWithKeys(fn ($n, $i) => [$i => Crm::stageLabel($i)])->all())])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSteps::route('/'),
            'create' => Pages\CreateStep::route('/create'),
            'edit' => Pages\EditStep::route('/{record}/edit'),
        ];
    }
}
