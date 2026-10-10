<?php

namespace App\Modules\PartnerApi\Support;

use Illuminate\Http\Request;

/** Unknown parameters are rejected, not ignored (§8.4). */
class StrictInput
{
    /** @param  list<string>  $allowed */
    public static function only(Request $request, array $allowed): void
    {
        $unknown = array_values(array_diff(array_keys($request->all()), $allowed));

        if ($unknown !== []) {
            throw new PartnerApiException(400, 'unknown_parameter', 'The request has parameters this endpoint does not accept.',
                'Unknown: '.implode(', ', $unknown).'.', extra: ['unknown' => $unknown]);
        }
    }
}
