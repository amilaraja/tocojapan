<?php

namespace App\Modules\Crm\Domain;

/**
 * Fixed CRM vocabulary (SRS-TOC-02 §1.4, §3). The five stages and their
 * names are fixed by the SRS; mini steps live in crm_step_templates.
 */
final class Crm
{
    public const TYPE_VEHICLE = 'vehicle_sale';

    public const TYPE_PARTS = 'spare_parts';

    public const TYPE_GENERAL = 'general';

    public const TYPES = [
        self::TYPE_VEHICLE => 'Vehicle sale',
        self::TYPE_PARTS => 'Spare parts',
        self::TYPE_GENERAL => 'General enquiry',
    ];

    public const SOURCES = [
        'quote' => 'Quote request',
        'proforma' => 'LC proforma invoice',
        'buy_now' => 'Buy now order',
        'contact_form' => 'Contact form',
        'spare_parts' => 'Spare-part request',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'walk_in' => 'Walk-in',
        'partner' => 'Partner / dealer',
        'other' => 'Other',
    ];

    /** Sources staff can pick when creating a Case by hand (CRM-CAS-003). */
    public const MANUAL_SOURCES = ['phone', 'whatsapp', 'email', 'walk_in', 'partner', 'other'];

    public const STAGES = [
        1 => 'Search & Order',
        2 => 'Payment',
        3 => 'Car Shipment',
        4 => 'Clearing',
        5 => 'Car Received',
    ];

    /** Customer-facing stage descriptions (SRS §3.1). */
    public const STAGE_DESCRIPTIONS = [
        1 => 'Get a price quote. Inquire now or buy now.',
        2 => 'Pay securely with PayPal or telegraphic bank transfer to our Japanese bank (or LC where accepted).',
        3 => 'RoRo or container. We book vessel space and handle all export documents.',
        4 => 'You receive the original documents (B/L, invoice, export certificate) by courier to clear customs.',
        5 => 'Pick up your vehicle from the destination port and you are ready to drive.',
    ];

    public const STATE_OPEN = 'open';

    public const STATE_WAITING = 'waiting';

    public const STATE_ON_HOLD = 'on_hold';

    public const STATE_WON = 'won';

    public const STATE_LOST = 'lost';

    public const STATE_CLOSED = 'closed';

    public const STATES = [
        self::STATE_OPEN => 'Open',
        self::STATE_WAITING => 'Waiting on customer',
        self::STATE_ON_HOLD => 'On hold',
        self::STATE_WON => 'Won',
        self::STATE_LOST => 'Lost',
        self::STATE_CLOSED => 'Closed',
    ];

    /** States in which a Case still needs work. */
    public const ACTIVE_STATES = [self::STATE_OPEN, self::STATE_WAITING, self::STATE_ON_HOLD];

    /** Support statuses for spare-part and general Cases (SRS §3.4). */
    public const SUPPORT_STATUSES = [
        'new' => 'New',
        'in_progress' => 'In progress',
        'waiting_customer' => 'Waiting on customer',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const PAYMENT_MODES = [
        'paypal' => 'PayPal',
        'bank_transfer' => 'Bank transfer (TT)',
        'lc' => 'Letter of credit (LC)',
    ];

    public static function stageLabel(?int $stage): string
    {
        return $stage ? sprintf('%02d %s', $stage, self::STAGES[$stage] ?? '') : '—';
    }

    public static function isActive(?string $state): bool
    {
        return in_array($state, self::ACTIVE_STATES, true);
    }
}
