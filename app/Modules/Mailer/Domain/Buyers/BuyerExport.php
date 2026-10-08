<?php

namespace App\Modules\Mailer\Domain\Buyers;

use App\Modules\Mailer\Models\Buyer;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV download of buyers (TOC-BUY-007/009). Values come from emails, so formula-like cells are neutralised. */
class BuyerExport
{
    public const HEADERS = ['Email', 'Title', 'First name', 'Last name', 'Country', 'Country code', 'Port', 'Phone', 'Phone (international)', 'Type', 'Enquiries', 'First enquiry', 'Last enquiry', 'Last make', 'Last model', 'Last year'];

    /** @param  Builder<Buyer>  $query */
    public static function download(Builder $query, string $name): StreamedResponse
    {
        $rows = (function () use ($query) {
            foreach ($query->with('latestEnquiry')->lazy(500) as $b) {
                $e = $b->latestEnquiry;
                yield array_map([self::class, 'cell'], [
                    $b->email, $b->title, $b->first_name, $b->last_name, $b->country, $b->country_code, $b->port, $b->phone, $b->phone_e164,
                    Buyer::TYPES[$b->buyer_type] ?? $b->buyer_type, $b->enquiry_count,
                    $b->first_enquiry_at?->toDateString(), $b->last_enquiry_at?->toDateString(), $e?->make, $e?->model, $e?->year,
                ]);
            }
        })();

        return Csv::download($name.'-'.now()->format('Ymd-His').'.csv', self::HEADERS, $rows);
    }

    /** Excel would run =, +, -, @ at the start of a cell as a formula. */
    public static function cell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            && ! preg_match('/^\+\d[\d\s]*$/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
