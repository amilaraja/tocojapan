<?php

namespace App\Modules\Crm\Domain;

/** The new part of an email reply: quoted history and signatures cut off. */
class InboundText
{
    public static function reply(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $kept = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^(On .+wrote:|-----Original Message-----|From: .+|_{5,}|Sent from my )/i', $trim)
                || preg_match('/^\d{4}[\/.-]\d{1,2}[\/.-]\d{1,2}.+(wrote|написал|schrieb)/iu', $trim)) {
                break;
            }
            if (str_starts_with($trim, '>')) {
                continue;
            }
            $kept[] = rtrim($line);
        }

        $out = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)) ?? '');

        return mb_substr($out, 0, 5000);
    }
}
