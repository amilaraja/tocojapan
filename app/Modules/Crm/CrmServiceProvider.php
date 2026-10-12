<?php

namespace App\Modules\Crm;

use App\Models\ContactInquiry;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\ProformaInvoice;
use App\Models\Quote;
use App\Models\QuoteMessage;
use App\Models\SparePartInquiry;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\Crm\Console\CrmDigest;
use App\Modules\Crm\Console\CrmImport;
use App\Modules\Crm\Console\CrmInbound;
use App\Modules\Crm\Console\CrmMailerSync;
use App\Modules\Crm\Console\CrmTick;
use App\Modules\Crm\Domain\CustomerMatcher;
use App\Modules\Crm\Domain\Intake;
use App\Modules\Crm\Support\CrmSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * TOCO CRM (SRS-TOC-02). Isolated under app/Modules/Crm; edits to shared
 * code are listed in docs/crm/integration-notes.md.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/crm.php', 'crm');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'crm');

        if (! self::enabled()) {
            return;
        }

        $this->registerRoutes();
        $this->registerIntake();
        $this->registerRecordLinks();

        if ($this->app->runningInConsole()) {
            $this->commands([CrmTick::class, CrmDigest::class, CrmInbound::class, CrmImport::class, CrmMailerSync::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('crm:tick')->everyMinute()->withoutOverlapping(5);
            $schedule->command('crm:inbound')->everyFiveMinutes()->withoutOverlapping(10);
            $schedule->command('crm:mailer-sync')->hourlyAt(7)->withoutOverlapping(30);
            $schedule->command('crm:digest')->dailyAt((string) config('crm.digest_time', '08:30'))->timezone('Asia/Tokyo');
        });
    }

    /**
     * Website records become Cases (CRM-CAS-001). Each listener runs after
     * the customer's transaction commits and swallows its own errors, so the
     * CRM can never fail or roll back a customer submission (CRM-NFR-004).
     */
    private function registerIntake(): void
    {
        $after = fn (string $what, callable $callback) => DB::afterCommit(fn () => Intake::safely($what, $callback));

        Quote::created(fn (Quote $quote) => $after('quote', fn () => Intake::quoteCreated($quote)));
        // wasChanged() may not survive until after the commit: pass the changed fields along.
        Quote::updated(function (Quote $quote) use ($after) {
            $changed = array_keys($quote->getChanges());
            $after('quote update', fn () => Intake::quoteUpdated($quote, $changed));
        });
        QuoteMessage::created(fn (QuoteMessage $message) => $after('quote message', fn () => Intake::quoteMessageCreated($message)));
        ProformaInvoice::created(fn (ProformaInvoice $proforma) => $after('proforma', fn () => Intake::proformaCreated($proforma)));
        Order::created(fn (Order $order) => $after('order', fn () => Intake::orderCreated($order)));
        Order::updated(function (Order $order) use ($after) {
            $changed = array_keys($order->getChanges());
            $after('order update', fn () => Intake::orderUpdated($order, $changed));
        });
        OrderMessage::created(fn (OrderMessage $message) => $after('order message', fn () => Intake::orderMessageCreated($message)));
        ContactInquiry::created(fn (ContactInquiry $inquiry) => $after('contact', fn () => Intake::contactCreated($inquiry)));
        SparePartInquiry::created(fn (SparePartInquiry $inquiry) => $after('spare part', fn () => Intake::sparePartCreated($inquiry)));
        VehicleHold::created(fn (VehicleHold $hold) => $after('hold', fn () => Intake::holdCreated($hold)));
        Vehicle::updated(function (Vehicle $vehicle) use ($after) {
            if ($vehicle->wasChanged('status') && $vehicle->status === 'sold') {
                $after('vehicle sold', fn () => Intake::vehicleSold($vehicle));
            }
        });

        // A guest who registers later sees their earlier Cases (CRM-POR-006).
        User::created(fn (User $user) => $after('user link', fn () => CustomerMatcher::linkUser($user)));
    }

    /**
     * "CRM Case C-…" on the existing order, quote and enquiry screens
     * (CRM-INT-002), via a render hook, so those screens are not edited.
     */
    private function registerRecordLinks(): void
    {
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            function (): string {
                $models = [
                    'filament.admin.resources.orders.view' => \App\Models\Order::class,
                    'filament.admin.resources.quotes.edit' => \App\Models\Quote::class,
                    'filament.admin.resources.contact-inquiries.edit' => \App\Models\ContactInquiry::class,
                    'filament.admin.resources.spare-part-inquiries.edit' => \App\Models\SparePartInquiry::class,
                ];
                $class = $models[request()->route()?->getName()] ?? null;
                $key = request()->route('record');
                if (! $class || ! $key || ! \App\Modules\Crm\Support\CrmAccess::canUse()) {
                    return '';
                }
                $record = $class::find(is_object($key) ? $key->getKey() : $key);
                $case = $record ? Intake::caseFor($record) : null;

                return $case ? view('crm::filament.record-link', ['case' => $case])->render() : '';
            },
        );
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->group(__DIR__.'/routes/web.php');
    }

    public static function enabled(): bool
    {
        return (bool) config('crm.enabled');
    }

    /** @return list<class-string> */
    public static function filamentPages(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            Filament\Pages\MyWork::class,
            Filament\Pages\PipelineBoard::class,
            Filament\Pages\TeamOverview::class,
            Filament\Pages\SlaMonitor::class,
            Filament\Pages\Reports::class,
            Filament\Pages\CrmSettingsPage::class,
            Filament\Pages\MyCrmProfile::class,
        ];
    }

    /** @return list<class-string> */
    public static function filamentResources(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            Filament\Resources\Cases\CaseResource::class,
            Filament\Resources\Customers\CustomerResource::class,
            Filament\Resources\Tasks\TaskResource::class,
            Filament\Resources\Queues\QueueResource::class,
            Filament\Resources\Steps\StepTemplateResource::class,
            Filament\Resources\Templates\EmailTemplateResource::class,
        ];
    }

    /** Whether the CRM's buyer emails replace the old order-status email (decision 13 Oct 2026). */
    public static function replacesOrderEmails(): bool
    {
        try {
            return (bool) CrmSettings::get('email.replace_order_emails');
        } catch (\Throwable) {
            return false;
        }
    }
}
