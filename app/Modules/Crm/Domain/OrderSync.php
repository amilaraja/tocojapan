<?php

namespace App\Modules\Crm\Domain;

use App\Models\Order;
use App\Modules\Crm\Models\CrmCase;

/**
 * The CRM keeps the website order in step (CRM-STG-009). While it writes, the
 * CRM's own order listener stands aside, and the old order-status email is
 * not sent (the CRM email replaces it).
 */
class OrderSync
{
    public static bool $writing = false;

    private const RANK = ['pending' => 0, 'paid' => 1, 'processing' => 2, 'shipped' => 3, 'delivered' => 4];

    /** @param array<string, mixed> $fields */
    public static function to(CrmCase $case, string $status, array $fields = []): void
    {
        $order = StageEngine::order($case);
        if ($order === null || in_array($order->status, ['cancelled', 'refunded'], true)) {
            return;
        }
        // Never move an order backwards.
        if ((self::RANK[$order->status] ?? 0) >= (self::RANK[$status] ?? 0)) {
            if ($fields) {
                self::write($order, [], $fields);
            }

            return;
        }

        self::write($order, ['status' => $status] + match ($status) {
            'paid' => ['paid_at' => $order->paid_at ?? now()],
            'shipped' => ['shipped_at' => now()],
            'delivered' => ['delivered_at' => now()],
            default => [],
        }, $fields);
    }

    /** @param array<string, mixed> $changes @param array<string, mixed> $fields */
    private static function write(Order $order, array $changes, array $fields): void
    {
        $fields = array_filter($fields, fn ($value, $key) => blank($order->{$key}), ARRAY_FILTER_USE_BOTH);
        if (! $changes && ! $fields) {
            return;
        }

        self::$writing = true;
        try {
            $order->forceFill($changes + $fields)->save();
        } finally {
            self::$writing = false;
        }
    }
}
