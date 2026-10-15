<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Each staff member's CRM profile: the nickname buyers see, time zone, email
 * preference and Away dates (decision 13 Oct 2026, NFR-005, NTF-004, ASN-007).
 * Admins can edit anyone's via ?user=ID.
 */
class MyCrmProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'crm::filament.settings';

    protected static ?string $slug = 'profile';

    protected static ?string $title = 'My CRM profile';

    protected static ?string $navigationLabel = 'My profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 19;

    #[\Livewire\Attributes\Url]
    public ?int $user = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    public function userId(): int
    {
        return CrmAccess::isAdmin() && $this->user ? $this->user : (int) auth()->id();
    }

    public function getTitle(): string
    {
        return $this->userId() === (int) auth()->id() ? 'My CRM profile' : 'CRM profile: '.\App\Models\User::find($this->userId())?->name;
    }

    public function mount(): void
    {
        $status = CrmAgentStatus::for($this->userId());
        $this->form->fill([
            'nickname' => $status->nickname,
            'timezone' => $status->timezone ?: 'Asia/Tokyo',
            'notify_mode' => $status->notify_mode ?: 'immediate',
            'away_from' => $status->away_from?->toDateString(),
            'away_until' => $status->away_until?->toDateString(),
            'can_pick_unassigned' => $status->exists ? $status->can_pick_unassigned : true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('How buyers know you')->schema([
                TextInput::make('nickname')->label('Nickname')->maxLength(40)->placeholder('Ken')
                    ->helperText('Buyers only ever see this name: email sender, sign-off and "Your TOCO contact". Your real name stays private.'),
            ]),
            Section::make('Preferences')->columns(2)->schema([
                Select::make('timezone')->label('Show times in')->searchable()->required()
                    ->options(collect(['Asia/Tokyo', 'Asia/Colombo', 'Asia/Dubai', 'Asia/Karachi', 'Asia/Manila', 'Asia/Bangkok', 'Africa/Nairobi', 'Africa/Lagos', 'Europe/London', 'Pacific/Auckland', 'Australia/Sydney', 'UTC'])
                        ->mapWithKeys(fn ($tz) => [$tz => $tz])->all() + array_combine(timezone_identifiers_list(), timezone_identifiers_list())),
                Select::make('notify_mode')->label('Emails')->options(['immediate' => 'As they happen', 'digest' => 'Morning summary only (late replies still come at once)'])->required(),
                Toggle::make('can_pick_unassigned')->label('I may take Cases from the unassigned queue'),
            ]),
            Section::make('Away')->description('While away you get no new Cases, and Cases with deadlines in that time are listed for an admin to hand over.')->columns(2)->schema([
                DatePicker::make('away_from')->label('Away from'),
                DatePicker::make('away_until')->label('Back on')->afterOrEqual('away_from'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $status = CrmAgentStatus::for($this->userId());
        $status->fill([
            'nickname' => trim((string) $data['nickname']) ?: null,
            'timezone' => $data['timezone'],
            'notify_mode' => $data['notify_mode'],
            'away_from' => $data['away_from'] ?: null,
            'away_until' => $data['away_until'] ?: null,
            'can_pick_unassigned' => (bool) $data['can_pick_unassigned'],
        ])->save();

        $atRisk = $status->away_from ? CrmCase::active()->where('owner_id', $this->userId())->count() : 0;
        Notification::make()->title('Profile saved')
            ->body($atRisk ? "{$atRisk} open Cases are listed for an admin to reassign while you are away." : null)
            ->success()->send();
    }
}
