<?php

namespace App\Modules\Crm\Support;

use App\Modules\Crm\Domain\Crm;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Proposed defaults from SRS-TOC-02 and the build plan (TOC-CRM-DEV-01 §1).
 * Idempotent: only inserts what is missing, never overwrites what an admin
 * changed. Called from the CRM migration, so tests start with the defaults.
 */
class CrmDefaults
{
    public static function install(): void
    {
        self::roles();
        self::steps();
        self::queues();
        self::holidays();
        self::reasons();
        self::templates();
        self::rules();
    }

    public static function roles(): void
    {
        foreach (CrmAccess::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $grants = [
            CrmAccess::ROLE_ADMIN => [CrmAccess::ADMIN],
            CrmAccess::ROLE_AGENT => [CrmAccess::AGENT],
            CrmAccess::ROLE_OPERATIONS => [CrmAccess::OPERATIONS],
            'super_admin' => [CrmAccess::ADMIN],
            'admin' => [CrmAccess::ADMIN],
        ];
        foreach ($grants as $role => $permissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** SRS §3.2. auto = the event that ticks it; condition = when the step applies. */
    public const STEPS = [
        // Stage 01 · Search & Order
        ['stage' => 1, 'key' => 'acknowledged', 'label' => 'Enquiry acknowledged (first reply sent)', 'required' => true, 'customer_visible' => false],
        ['stage' => 1, 'key' => 'availability_confirmed', 'label' => 'Vehicle availability confirmed with the supplier', 'customer_label' => 'Vehicle availability confirmed', 'required' => true, 'customer_visible' => true, 'condition' => 'supplier_stock'],
        ['stage' => 1, 'key' => 'quote_sent', 'label' => 'Price quote sent (FOB / CIF to the customer\'s port)', 'customer_label' => 'Price quote sent', 'required' => true, 'customer_visible' => true,
            'fields' => [['name' => 'price', 'label' => 'Quoted price (USD)', 'type' => 'number', 'required' => false], ['name' => 'basis', 'label' => 'Basis', 'type' => 'select', 'options' => ['CIF' => 'CIF', 'FOB' => 'FOB'], 'required' => false]]],
        ['stage' => 1, 'key' => 'proforma_issued', 'label' => 'LC proforma invoice issued', 'customer_label' => 'Proforma invoice issued', 'required' => true, 'customer_visible' => true, 'condition' => 'lc'],
        ['stage' => 1, 'key' => 'order_confirmed', 'label' => 'Customer confirms order', 'customer_label' => 'Order confirmed', 'required' => true, 'customer_visible' => true],
        ['stage' => 1, 'key' => 'vehicle_reserved', 'label' => 'Vehicle reserved for the customer', 'required' => true, 'customer_visible' => false],
        // Stage 02 · Payment
        ['stage' => 2, 'key' => 'invoice_issued', 'label' => 'Invoice issued', 'required' => true, 'customer_visible' => true],
        ['stage' => 2, 'key' => 'payment_received', 'label' => 'Payment received', 'required' => true, 'customer_visible' => true,
            'fields' => [['name' => 'payment_method', 'label' => 'Payment method', 'type' => 'select', 'options' => Crm::PAYMENT_MODES, 'required' => true], ['name' => 'amount', 'label' => 'Amount received (USD)', 'type' => 'number', 'required' => false]]],
        ['stage' => 2, 'key' => 'receipt_sent', 'label' => 'Payment receipt sent', 'required' => true, 'customer_visible' => false],
        ['stage' => 2, 'key' => 'vehicle_sold', 'label' => 'Vehicle marked sold on the website', 'required' => true, 'customer_visible' => false],
        // Stage 03 · Car Shipment
        ['stage' => 3, 'key' => 'export_certificate', 'label' => 'Export de-registration / export certificate obtained', 'customer_label' => 'Export certificate obtained', 'required' => true, 'customer_visible' => true, 'responsible_role' => 'operations'],
        ['stage' => 3, 'key' => 'inspection', 'label' => 'Pre-shipment inspection done', 'customer_label' => 'Pre-shipment inspection done', 'required' => true, 'customer_visible' => true, 'responsible_role' => 'operations', 'condition' => 'inspection'],
        ['stage' => 3, 'key' => 'vessel_booked', 'label' => 'Shipping method and vessel booked', 'customer_label' => 'Vessel booked', 'required' => true, 'customer_visible' => true, 'responsible_role' => 'operations',
            'fields' => [['name' => 'shipping_method', 'label' => 'Shipping method', 'type' => 'select', 'options' => ['roro' => 'RoRo', 'container' => 'Container'], 'required' => true], ['name' => 'vessel_name', 'label' => 'Vessel', 'type' => 'text', 'required' => true], ['name' => 'voyage_no', 'label' => 'Voyage no.', 'type' => 'text', 'required' => false], ['name' => 'etd', 'label' => 'ETD (sailing date)', 'type' => 'date', 'required' => true]]],
        ['stage' => 3, 'key' => 'delivered_to_port', 'label' => 'Vehicle delivered to the port of loading', 'required' => true, 'customer_visible' => false, 'responsible_role' => 'operations'],
        ['stage' => 3, 'key' => 'bl_issued', 'label' => 'Loaded on vessel, B/L issued', 'customer_label' => 'Loaded on vessel, Bill of Lading issued', 'required' => true, 'customer_visible' => true, 'responsible_role' => 'operations',
            'fields' => [['name' => 'bl_number', 'label' => 'B/L number', 'type' => 'text', 'required' => true], ['name' => 'eta', 'label' => 'ETA (arrival date)', 'type' => 'date', 'required' => true]]],
        // Stage 04 · Clearing
        ['stage' => 4, 'key' => 'documents_couriered', 'label' => 'Original documents sent by courier', 'customer_label' => 'Original documents sent by courier', 'required' => true, 'customer_visible' => true, 'responsible_role' => 'operations',
            'fields' => [['name' => 'courier', 'label' => 'Courier (or "Telex release")', 'type' => 'text', 'required' => true], ['name' => 'tracking_number', 'label' => 'Tracking number', 'type' => 'text', 'required' => false]]],
        ['stage' => 4, 'key' => 'documents_received', 'label' => 'Customer confirms documents received', 'customer_label' => 'Documents received', 'required' => true, 'customer_visible' => true],
        // Stage 05 · Car Received
        ['stage' => 5, 'key' => 'vessel_arrived', 'label' => 'Vessel arrived at the destination port', 'customer_label' => 'Vessel arrived', 'required' => true, 'customer_visible' => true,
            'fields' => [['name' => 'arrival_date', 'label' => 'Arrival date', 'type' => 'date', 'required' => true]]],
        ['stage' => 5, 'key' => 'car_received', 'label' => 'Customer confirms car received', 'customer_label' => 'Car received', 'required' => true, 'customer_visible' => true],
        ['stage' => 5, 'key' => 'review_requested', 'label' => 'Review / feedback requested', 'required' => false, 'customer_visible' => false],
    ];

    public static function steps(): void
    {
        foreach (self::STEPS as $i => $step) {
            if (DB::table('crm_step_templates')->where('key', $step['key'])->exists()) {
                continue;
            }
            DB::table('crm_step_templates')->insert([
                'stage' => $step['stage'],
                'key' => $step['key'],
                'label' => $step['label'],
                'customer_label' => $step['customer_label'] ?? null,
                'required' => $step['required'],
                'customer_visible' => $step['customer_visible'],
                'responsible_role' => $step['responsible_role'] ?? null,
                'condition' => $step['condition'] ?? null,
                'fields' => isset($step['fields']) ? json_encode($step['fields']) : null,
                'is_active' => true,
                'sort' => ($i + 1) * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function queues(): void
    {
        if (DB::table('crm_queues')->exists()) {
            return;
        }
        $queues = [
            ['name' => 'Sales', 'case_types' => [Crm::TYPE_VEHICLE]],
            ['name' => 'Spare parts', 'case_types' => [Crm::TYPE_PARTS]],
            ['name' => 'General', 'case_types' => [Crm::TYPE_GENERAL]],
        ];
        foreach ($queues as $i => $queue) {
            DB::table('crm_queues')->insert([
                'name' => $queue['name'],
                'case_types' => json_encode($queue['case_types']),
                'sources' => null,
                'max_open_cases' => 30,
                'is_active' => true,
                'sort' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Japanese national holidays (substitute days included). Editable in CRM settings. */
    public const HOLIDAYS = [
        '2026-01-01' => 'New Year\'s Day', '2026-01-12' => 'Coming of Age Day', '2026-02-11' => 'National Foundation Day',
        '2026-02-23' => 'Emperor\'s Birthday', '2026-03-20' => 'Vernal Equinox Day', '2026-04-29' => 'Showa Day',
        '2026-05-04' => 'Greenery Day', '2026-05-05' => 'Children\'s Day', '2026-05-06' => 'Substitute holiday',
        '2026-07-20' => 'Marine Day', '2026-08-11' => 'Mountain Day', '2026-09-21' => 'Respect for the Aged Day',
        '2026-09-22' => 'Citizens\' Holiday', '2026-09-23' => 'Autumnal Equinox Day', '2026-10-12' => 'Sports Day',
        '2026-11-03' => 'Culture Day', '2026-11-23' => 'Labour Thanksgiving Day',
        '2027-01-01' => 'New Year\'s Day', '2027-01-11' => 'Coming of Age Day', '2027-02-11' => 'National Foundation Day',
        '2027-02-23' => 'Emperor\'s Birthday', '2027-03-22' => 'Vernal Equinox (substitute)', '2027-04-29' => 'Showa Day',
        '2027-05-03' => 'Constitution Day', '2027-05-04' => 'Greenery Day', '2027-05-05' => 'Children\'s Day',
        '2027-07-19' => 'Marine Day', '2027-08-11' => 'Mountain Day', '2027-09-20' => 'Respect for the Aged Day',
        '2027-09-23' => 'Autumnal Equinox Day', '2027-10-11' => 'Sports Day', '2027-11-03' => 'Culture Day',
        '2027-11-23' => 'Labour Thanksgiving Day',
    ];

    public static function holidays(): void
    {
        if (DB::table('crm_holidays')->exists()) {
            return;
        }
        foreach (self::HOLIDAYS as $date => $name) {
            DB::table('crm_holidays')->insert(['date' => $date, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function reasons(): void
    {
        if (DB::table('crm_reasons')->exists()) {
            return;
        }
        $reasons = [
            'lost' => ['Price', 'Bought elsewhere', 'No reply from the customer', 'Vehicle sold to someone else', 'Shipping cost or time', 'Other'],
            'close' => ['Duplicate', 'Spam', 'Not a buyer'],
        ];
        foreach ($reasons as $kind => $labels) {
            foreach ($labels as $i => $label) {
                DB::table('crm_reasons')->insert(['kind' => $kind, 'label' => $label, 'is_active' => true, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Email templates. "system" ones are sent by events (customer emails in
     * SRS §8.1); the others are for staff to send from a Case (CRM-ACT-002).
     */
    public const TEMPLATES = [
        ['key' => 'acknowledgement', 'name' => 'We received your enquiry', 'system' => true,
            'subject' => 'We received your enquiry [{reference}]',
            'body' => "Dear {customer_name},\n\nThank you for contacting TOCO. We have received your enquiry{vehicle_phrase} and our sales team will reply shortly.\n\nYour reference number is {reference}. You can follow your enquiry here:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'stage_changed', 'name' => 'Your purchase moved to the next step', 'system' => true,
            'subject' => '{vehicle}: now at step {stage} [{reference}]',
            'body' => "Dear {customer_name},\n\nYour purchase of {vehicle} has moved to step {stage}.\n\n{stage_description}\n\nSee every step and your documents here:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'payment_received', 'name' => 'Payment received', 'system' => true,
            'subject' => 'Payment received for {vehicle} [{reference}]',
            'body' => "Dear {customer_name},\n\nWe have received your payment for {vehicle}. Thank you.\n\nAmount: {amount}\nPayment method: {payment_method}\nDate: {date}\n\nThis email is your receipt. We will now prepare the export documents and book the vessel.\n\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'vessel_booked', 'name' => 'Vessel booked', 'system' => true,
            'subject' => 'Vessel booked for {vehicle} [{reference}]',
            'body' => "Dear {customer_name},\n\nYour vehicle {vehicle} is booked on vessel {vessel} {voyage}, sailing on {etd}.\n\nWe will send the Bill of Lading and the arrival date once it is loaded.\n\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'bl_issued', 'name' => 'Loaded on vessel, B/L issued', 'system' => true,
            'subject' => '{vehicle} is on its way [{reference}]',
            'body' => "Dear {customer_name},\n\nYour vehicle {vehicle} is loaded on {vessel}.\n\nBill of Lading: {bl_number}\nExpected arrival at {port}: {eta}\n\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'documents_couriered', 'name' => 'Documents sent', 'system' => true,
            'subject' => 'Your documents are on the way [{reference}]',
            'body' => "Dear {customer_name},\n\nWe have sent the original documents for {vehicle} (Bill of Lading, invoice and export certificate).\n\nCourier: {courier}\nTracking number: {tracking_number}\n\nWhen they arrive, please click \"Documents received\" here:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'vessel_arrived', 'name' => 'Vessel arrived', 'system' => true,
            'subject' => '{vehicle} has arrived at {port} [{reference}]',
            'body' => "Dear {customer_name},\n\nThe vessel carrying {vehicle} has arrived at {port}. You can now collect your vehicle with the original documents.\n\nAfter you collect it, please click \"Car received\" here:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'payment_due', 'name' => 'Payment reminder', 'system' => true,
            'subject' => 'Payment reminder for {vehicle} [{reference}]',
            'body' => "Dear {customer_name},\n\nThis is a friendly reminder that payment for {vehicle} is due by {due_date}. The vehicle stays reserved for you until then.\n\nPay or see the payment details here:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'won_thanks', 'name' => 'Thank you and review request', 'system' => true,
            'subject' => 'Thank you for buying from TOCO [{reference}]',
            'body' => "Dear {customer_name},\n\nThank you for buying {vehicle} from TOCO. We hope you enjoy driving it.\n\nWe would be grateful if you could tell others about your experience:\n{review_url}\n\nWhen you need your next vehicle, we are here to help.\n\nBest regards,\n{sender_name}"],
        ['key' => 'reply_quote', 'name' => 'Price quote', 'system' => false,
            'subject' => 'Price quote for {vehicle} [{reference}]',
            'body' => "Dear {customer_name},\n\nThank you for your interest in {vehicle}.\n\nOur price is {price} to {port}.\n\nThe vehicle is available now. To reserve it, please confirm your order by replying to this email or from your account:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'reply_follow_up', 'name' => 'Follow-up: still interested?', 'system' => false,
            'subject' => 'Are you still interested in {vehicle}? [{reference}]',
            'body' => "Dear {customer_name},\n\nI wanted to follow up on your enquiry about {vehicle}. Do you have any questions I can answer, or would you like a quote to a different port?\n\nBest regards,\n{sender_name}"],
        ['key' => 'reply_payment', 'name' => 'Payment instructions', 'system' => false,
            'subject' => 'Payment details for {vehicle} [{reference}]',
            'body' => "Dear {customer_name},\n\nThank you for your order of {vehicle}. Please pay {price} by {due_date}.\n\nBank details:\n{bank_details}\n\nYou can also pay by PayPal from your account:\n{portal_url}\n\nBest regards,\n{sender_name}"],
        ['key' => 'reply_parts', 'name' => 'Spare parts reply', 'system' => false,
            'subject' => 'Your spare-part request [{reference}]',
            'body' => "Dear {customer_name},\n\nThank you for your spare-part request.\n\n\n\nBest regards,\n{sender_name}"],
    ];

    public static function templates(): void
    {
        foreach (self::TEMPLATES as $template) {
            if (DB::table('crm_email_templates')->where('key', $template['key'])->exists()) {
                continue;
            }
            DB::table('crm_email_templates')->insert($template + ['audience' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Notification matrix, SRS §8.1: event => channels switched on. */
    public const RULES = [
        'case_created' => ['inapp', 'email', 'customer_email'],
        'case_assigned' => ['inapp', 'email'],
        'customer_message' => ['inapp', 'email'],
        'sla_warning' => ['inapp', 'email'],
        'sla_breached' => ['inapp', 'email'],
        'case_stale' => ['inapp', 'email'],
        'task_due' => ['inapp', 'email'],
        'stage_changed' => ['inapp', 'customer_email'],
        'payment_received' => ['inapp', 'email', 'customer_email'],
        'vessel_booked' => ['inapp', 'customer_email'],
        'bl_issued' => ['inapp', 'customer_email'],
        'documents_couriered' => ['inapp', 'customer_email'],
        'vessel_arrived' => ['customer_email'],
        'case_won' => ['inapp', 'customer_email'],
        'case_lost' => ['inapp'],
        'payment_due' => ['inapp', 'customer_email'],
        'vehicle_conflict' => ['inapp', 'email'],
        'case_unassigned' => ['inapp', 'email'],
        'daily_digest' => ['email'],
    ];

    public static function rules(): void
    {
        foreach (self::RULES as $event => $channels) {
            foreach (['inapp', 'email', 'customer_email'] as $channel) {
                DB::table('crm_notification_rules')->insertOrIgnore([
                    'event' => $event, 'channel' => $channel, 'enabled' => in_array($channel, $channels, true),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
