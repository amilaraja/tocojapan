<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Domain\Notifier;
use App\Modules\Crm\Models\CrmHoliday;
use App\Modules\Crm\Models\CrmReason;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmDefaults;
use App\Modules\Crm\Support\CrmSettings;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/** Everything an admin tunes without code (CRM-GEN-004, NTF-002). */
class CrmSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'crm::filament.settings';

    protected static ?string $slug = 'crm/settings';

    protected static ?string $title = 'CRM settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 23;

    private const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    private const EVENTS = [
        'case_created' => 'New Case', 'case_assigned' => 'Case assigned', 'customer_message' => 'Customer message', 'sla_warning' => 'Reply due soon (75 %)',
        'sla_breached' => 'SLA breached', 'case_stale' => 'No activity (stale)', 'case_unassigned' => 'Nobody owns a Case', 'task_due' => 'Task due',
        'stage_changed' => 'Stage changed', 'payment_received' => 'Payment received', 'vessel_booked' => 'Vessel booked', 'bl_issued' => 'B/L issued',
        'documents_couriered' => 'Documents couriered', 'vessel_arrived' => 'Vessel arrived', 'case_won' => 'Won (thank-you and review request)',
        'case_lost' => 'Lost', 'payment_due' => 'Payment due reminder', 'vehicle_conflict' => 'Car taken by another buyer', 'daily_digest' => 'Morning summary',
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return CrmAccess::isAdmin();
    }

    public function mount(): void
    {
        $s = CrmSettings::all();
        $hours = (array) $s['calendar.hours'];
        $rules = DB::table('crm_notification_rules')->get();

        $this->form->fill([
            'days' => collect(self::DAYS)->mapWithKeys(fn ($label, $day) => [$day => [
                'open' => isset($hours[$day]),
                'from' => $hours[$day][0] ?? '09:00',
                'to' => $hours[$day][1] ?? '18:00',
            ]])->all(),
            'holidays' => CrmHoliday::orderBy('date')->where('date', '>=', now()->subMonth())->get()->map(fn ($h) => ['date' => $h->date->toDateString(), 'name' => $h->name])->all(),
            'sla' => [
                'first_response_hours' => round($s['sla.first_response_minutes'] / 60, 2),
                'customer_reply_hours' => round($s['sla.customer_reply_minutes'] / 60, 2),
                'quote_sent_hours' => round($s['sla.quote_sent_minutes'] / 60, 2),
                'payment_due_days' => $s['sla.payment_due_days'],
                'vessel_booked_days' => $s['sla.vessel_booked_days'],
                'documents_couriered_days' => $s['sla.documents_couriered_days'],
                'warning_percent' => $s['sla.warning_percent'],
                'stale_days' => $s['sla.stale_days'],
                'stale_admin_days' => $s['sla.stale_admin_days'],
                'unassigned_escalation_minutes' => $s['sla.unassigned_escalation_minutes'],
                'default_follow_up_days' => $s['sla.default_follow_up_days'],
            ],
            'email' => [
                'sender_name' => $s['email.sender_name'],
                'show_nickname' => (bool) $s['email.show_nickname'],
                'review_url' => $s['email.review_url'],
                'review_delay_days' => $s['email.review_delay_days'],
                'payment_reminder_days_before' => $s['email.payment_reminder_days_before'],
                'replace_order_emails' => (bool) $s['email.replace_order_emails'],
                'inbound_enabled' => (bool) $s['inbound.enabled'],
                'extra_admin_emails' => $s['alerts.extra_admin_emails'],
            ],
            'lost' => CrmReason::where('kind', 'lost')->orderBy('sort')->get()->map(fn ($r) => ['label' => $r->label, 'is_active' => $r->is_active])->all(),
            'close' => CrmReason::where('kind', 'close')->orderBy('sort')->get()->map(fn ($r) => ['label' => $r->label, 'is_active' => $r->is_active])->all(),
            'rules' => collect(self::EVENTS)->mapWithKeys(fn ($label, $event) => [$event => [
                'inapp' => (bool) $rules->where('event', $event)->firstWhere('channel', 'inapp')?->enabled,
                'email' => (bool) $rules->where('event', $event)->firstWhere('channel', 'email')?->enabled,
                'customer_email' => (bool) $rules->where('event', $event)->firstWhere('channel', 'customer_email')?->enabled,
            ]])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $number = fn (string $name, string $label, ?string $help = null) => TextInput::make($name)->label($label)->numeric()->minValue(0)->required()->helperText($help);

        return $schema->statePath('data')->components([
            Tabs::make('CRM settings')->persistTabInQueryString()->tabs([
                Tab::make('Business hours')->schema([
                    Section::make('Hours (Japan time). Reply deadlines only count these hours.')->schema(
                        collect(self::DAYS)->map(fn ($label, $day) => Grid::make(3)->schema([
                            Toggle::make("days.{$day}.open")->label($label)->inline(false),
                            TimePicker::make("days.{$day}.from")->label('From')->seconds(false),
                            TimePicker::make("days.{$day}.to")->label('To')->seconds(false),
                        ]))->values()->all()
                    ),
                    Repeater::make('holidays')->label('Holidays (no business hours)')->schema([
                        DatePicker::make('date')->required(),
                        TextInput::make('name')->required()->maxLength(120),
                    ])->columns(2)->defaultItems(0)->addActionLabel('Add holiday'),
                ]),
                Tab::make('Service levels')->schema([Grid::make(3)->schema([
                    $number('sla.first_response_hours', 'First reply (business hours)', 'Proposed: 2'),
                    $number('sla.customer_reply_hours', 'Reply to a customer message (business hours)', '9 = one business day'),
                    $number('sla.quote_sent_hours', 'Quote sent (business hours)', '9 = one business day'),
                    $number('sla.payment_due_days', 'Payment due / car reserved (days)', 'Proposed: 3'),
                    $number('sla.vessel_booked_days', 'Vessel booked after payment (days)'),
                    $number('sla.documents_couriered_days', 'Documents couriered after B/L (days)'),
                    $number('sla.warning_percent', 'Warn at (% of the time)', 'Proposed: 75'),
                    $number('sla.stale_days', 'Stale after (days, owner told)'),
                    $number('sla.stale_admin_days', 'Stale after (days, admins told)'),
                    $number('sla.unassigned_escalation_minutes', 'Unassigned alert after (business minutes)'),
                    $number('sla.default_follow_up_days', 'Default follow-up on new Cases (days)'),
                ])]),
                Tab::make('Customer emails')->schema([
                    TextInput::make('email.sender_name')->label('Team name buyers see')->required()->maxLength(80)
                        ->helperText('Used when the owner has no nickname.'),
                    Toggle::make('email.show_nickname')->label('Show the owner\'s nickname to buyers'),
                    TextInput::make('email.review_url')->label('Review link')->url()->placeholder(url('/customer-reviews')),
                    $number('email.review_delay_days', 'Send the review request (days after "Car received")'),
                    $number('email.payment_reminder_days_before', 'Payment reminder (days before due)'),
                    Toggle::make('email.replace_order_emails')->label('CRM emails replace the old order-status emails'),
                    Toggle::make('email.inbound_enabled')->label('Add buyer email replies to their Case'),
                    TextInput::make('email.extra_admin_emails')->label('Also send admin alerts to')->maxLength(500)
                        ->helperText('Inboxes for unassigned Cases and late replies besides the CRM admins, separated by commas.'),
                ]),
                Tab::make('Lost and close reasons')->schema([
                    Repeater::make('lost')->label('Lost reasons')->schema([TextInput::make('label')->required()->maxLength(120), Toggle::make('is_active')->label('In use')->default(true)->inline(false)])->columns(2)->reorderable(),
                    Repeater::make('close')->label('Close reasons')->schema([TextInput::make('label')->required()->maxLength(120), Toggle::make('is_active')->label('In use')->default(true)->inline(false)])->columns(2)->reorderable(),
                ]),
                Tab::make('Notifications')->schema(
                    collect(self::EVENTS)->map(fn ($label, $event) => Grid::make(4)->schema([
                        TextInput::make("rules.{$event}.label")->hiddenLabel()->default($label)->disabled()->dehydrated(false)->formatStateUsing(fn () => $label),
                        Toggle::make("rules.{$event}.inapp")->label('Bell'),
                        Toggle::make("rules.{$event}.email")->label('Staff email'),
                        Toggle::make("rules.{$event}.customer_email")->label('Customer email')->disabled(! isset(Notifier::CUSTOMER_TEMPLATES[$event])),
                    ]))->values()->all()
                ),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $hours = [];
        foreach ($data['days'] as $day => $row) {
            if (! empty($row['open']) && ! empty($row['from']) && ! empty($row['to']) && substr($row['from'], 0, 5) < substr($row['to'], 0, 5)) {
                $hours[(int) $day] = [substr($row['from'], 0, 5), substr($row['to'], 0, 5)];
            }
        }
        $sla = $data['sla'];
        CrmSettings::setMany([
            'calendar.hours' => $hours,
            'sla.first_response_minutes' => (int) round($sla['first_response_hours'] * 60),
            'sla.customer_reply_minutes' => (int) round($sla['customer_reply_hours'] * 60),
            'sla.quote_sent_minutes' => (int) round($sla['quote_sent_hours'] * 60),
            'sla.payment_due_days' => (int) $sla['payment_due_days'],
            'sla.vessel_booked_days' => (int) $sla['vessel_booked_days'],
            'sla.documents_couriered_days' => (int) $sla['documents_couriered_days'],
            'sla.warning_percent' => (int) $sla['warning_percent'],
            'sla.stale_days' => (int) $sla['stale_days'],
            'sla.stale_admin_days' => (int) $sla['stale_admin_days'],
            'sla.unassigned_escalation_minutes' => (int) $sla['unassigned_escalation_minutes'],
            'sla.default_follow_up_days' => (int) $sla['default_follow_up_days'],
            'email.sender_name' => $data['email']['sender_name'],
            'email.show_nickname' => (bool) $data['email']['show_nickname'],
            'email.review_url' => $data['email']['review_url'] ?: null,
            'email.review_delay_days' => (int) $data['email']['review_delay_days'],
            'email.payment_reminder_days_before' => (int) $data['email']['payment_reminder_days_before'],
            'email.replace_order_emails' => (bool) $data['email']['replace_order_emails'],
            'inbound.enabled' => (bool) $data['email']['inbound_enabled'],
            'alerts.extra_admin_emails' => $data['email']['extra_admin_emails'] ?: null,
        ]);

        DB::transaction(function () use ($data) {
            CrmHoliday::where('date', '>=', now()->subMonth()->toDateString())->delete();
            foreach ($data['holidays'] ?? [] as $h) {
                CrmHoliday::updateOrCreate(['date' => $h['date']], ['name' => $h['name']]);
            }
            foreach (['lost', 'close'] as $kind) {
                CrmReason::where('kind', $kind)->delete();
                foreach (array_values($data[$kind] ?? []) as $i => $r) {
                    CrmReason::create(['kind' => $kind, 'label' => $r['label'], 'is_active' => (bool) ($r['is_active'] ?? true), 'sort' => $i]);
                }
            }
            foreach ($data['rules'] ?? [] as $event => $channels) {
                foreach (['inapp', 'email', 'customer_email'] as $channel) {
                    if (array_key_exists($channel, $channels)) {
                        DB::table('crm_notification_rules')->updateOrInsert(['event' => $event, 'channel' => $channel], ['enabled' => (bool) $channels[$channel], 'updated_at' => now()]);
                    }
                }
            }
        });
        CrmDefaults::rules();
        Notifier::flushRules();

        Notification::make()->title('CRM settings saved')->body('New targets apply to Cases created from now on.')->success()->send();
    }
}
