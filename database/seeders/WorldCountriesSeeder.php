<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Adds every ISO-3166 country to the countries table.
 *
 * Existing countries (matched by iso2) are left untouched — only their
 * is_active flag and any port/regulation relations stay as configured.
 * Newly-added countries are created inactive so they don't appear in
 * front-end destination pickers until an admin enables them.
 */
class WorldCountriesSeeder extends Seeder
{
    public function run(): void
    {
        // [iso2, name, region, currency_code]
        $countries = [
            // ── Africa ──────────────────────────────────────────────
            ['DZ', 'Algeria', 'Africa', 'DZD'],
            ['AO', 'Angola', 'Africa', 'AOA'],
            ['BJ', 'Benin', 'Africa', 'XOF'],
            ['BW', 'Botswana', 'Africa', 'BWP'],
            ['BF', 'Burkina Faso', 'Africa', 'XOF'],
            ['BI', 'Burundi', 'Africa', 'BIF'],
            ['CV', 'Cabo Verde', 'Africa', 'CVE'],
            ['CM', 'Cameroon', 'Africa', 'XAF'],
            ['CF', 'Central African Republic', 'Africa', 'XAF'],
            ['TD', 'Chad', 'Africa', 'XAF'],
            ['KM', 'Comoros', 'Africa', 'KMF'],
            ['CG', 'Congo', 'Africa', 'XAF'],
            ['CD', 'DR Congo', 'Africa', 'CDF'],
            ['CI', "Côte d'Ivoire", 'Africa', 'XOF'],
            ['DJ', 'Djibouti', 'Africa', 'DJF'],
            ['EG', 'Egypt', 'Africa', 'EGP'],
            ['GQ', 'Equatorial Guinea', 'Africa', 'XAF'],
            ['ER', 'Eritrea', 'Africa', 'ERN'],
            ['SZ', 'Eswatini', 'Africa', 'SZL'],
            ['ET', 'Ethiopia', 'Africa', 'ETB'],
            ['GA', 'Gabon', 'Africa', 'XAF'],
            ['GM', 'Gambia', 'Africa', 'GMD'],
            ['GH', 'Ghana', 'Africa', 'GHS'],
            ['GN', 'Guinea', 'Africa', 'GNF'],
            ['GW', 'Guinea-Bissau', 'Africa', 'XOF'],
            ['KE', 'Kenya', 'Africa', 'KES'],
            ['LS', 'Lesotho', 'Africa', 'LSL'],
            ['LR', 'Liberia', 'Africa', 'LRD'],
            ['LY', 'Libya', 'Africa', 'LYD'],
            ['MG', 'Madagascar', 'Africa', 'MGA'],
            ['MW', 'Malawi', 'Africa', 'MWK'],
            ['ML', 'Mali', 'Africa', 'XOF'],
            ['MR', 'Mauritania', 'Africa', 'MRU'],
            ['MU', 'Mauritius', 'Africa', 'MUR'],
            ['MA', 'Morocco', 'Africa', 'MAD'],
            ['MZ', 'Mozambique', 'Africa', 'MZN'],
            ['NA', 'Namibia', 'Africa', 'NAD'],
            ['NE', 'Niger', 'Africa', 'XOF'],
            ['NG', 'Nigeria', 'Africa', 'NGN'],
            ['RW', 'Rwanda', 'Africa', 'RWF'],
            ['ST', 'São Tomé and Príncipe', 'Africa', 'STN'],
            ['SN', 'Senegal', 'Africa', 'XOF'],
            ['SC', 'Seychelles', 'Africa', 'SCR'],
            ['SL', 'Sierra Leone', 'Africa', 'SLE'],
            ['SO', 'Somalia', 'Africa', 'SOS'],
            ['ZA', 'South Africa', 'Africa', 'ZAR'],
            ['SS', 'South Sudan', 'Africa', 'SSP'],
            ['SD', 'Sudan', 'Africa', 'SDG'],
            ['TZ', 'Tanzania', 'Africa', 'TZS'],
            ['TG', 'Togo', 'Africa', 'XOF'],
            ['TN', 'Tunisia', 'Africa', 'TND'],
            ['UG', 'Uganda', 'Africa', 'UGX'],
            ['ZM', 'Zambia', 'Africa', 'ZMW'],
            ['ZW', 'Zimbabwe', 'Africa', 'ZWL'],

            // ── Asia & Middle East ──────────────────────────────────
            ['AF', 'Afghanistan', 'Asia', 'AFN'],
            ['AM', 'Armenia', 'Asia', 'AMD'],
            ['AZ', 'Azerbaijan', 'Asia', 'AZN'],
            ['BH', 'Bahrain', 'Asia', 'BHD'],
            ['BD', 'Bangladesh', 'Asia', 'BDT'],
            ['BT', 'Bhutan', 'Asia', 'BTN'],
            ['BN', 'Brunei', 'Asia', 'BND'],
            ['KH', 'Cambodia', 'Asia', 'KHR'],
            ['CN', 'China', 'Asia', 'CNY'],
            ['CY', 'Cyprus', 'Asia', 'EUR'],
            ['GE', 'Georgia', 'Asia', 'GEL'],
            ['IN', 'India', 'Asia', 'INR'],
            ['ID', 'Indonesia', 'Asia', 'IDR'],
            ['IR', 'Iran', 'Asia', 'IRR'],
            ['IQ', 'Iraq', 'Asia', 'IQD'],
            ['IL', 'Israel', 'Asia', 'ILS'],
            ['JP', 'Japan', 'Asia', 'JPY'],
            ['JO', 'Jordan', 'Asia', 'JOD'],
            ['KZ', 'Kazakhstan', 'Asia', 'KZT'],
            ['KW', 'Kuwait', 'Asia', 'KWD'],
            ['KG', 'Kyrgyzstan', 'Asia', 'KGS'],
            ['LA', 'Laos', 'Asia', 'LAK'],
            ['LB', 'Lebanon', 'Asia', 'LBP'],
            ['MY', 'Malaysia', 'Asia', 'MYR'],
            ['MV', 'Maldives', 'Asia', 'MVR'],
            ['MN', 'Mongolia', 'Asia', 'MNT'],
            ['MM', 'Myanmar', 'Asia', 'MMK'],
            ['NP', 'Nepal', 'Asia', 'NPR'],
            ['KP', 'North Korea', 'Asia', 'KPW'],
            ['OM', 'Oman', 'Asia', 'OMR'],
            ['PK', 'Pakistan', 'Asia', 'PKR'],
            ['PS', 'Palestine', 'Asia', 'ILS'],
            ['PH', 'Philippines', 'Asia', 'PHP'],
            ['QA', 'Qatar', 'Asia', 'QAR'],
            ['SA', 'Saudi Arabia', 'Asia', 'SAR'],
            ['SG', 'Singapore', 'Asia', 'SGD'],
            ['KR', 'South Korea', 'Asia', 'KRW'],
            ['LK', 'Sri Lanka', 'Asia', 'LKR'],
            ['SY', 'Syria', 'Asia', 'SYP'],
            ['TW', 'Taiwan', 'Asia', 'TWD'],
            ['TJ', 'Tajikistan', 'Asia', 'TJS'],
            ['TH', 'Thailand', 'Asia', 'THB'],
            ['TL', 'Timor-Leste', 'Asia', 'USD'],
            ['TR', 'Turkey', 'Asia', 'TRY'],
            ['TM', 'Turkmenistan', 'Asia', 'TMT'],
            ['AE', 'United Arab Emirates', 'Asia', 'AED'],
            ['UZ', 'Uzbekistan', 'Asia', 'UZS'],
            ['VN', 'Vietnam', 'Asia', 'VND'],
            ['YE', 'Yemen', 'Asia', 'YER'],

            // ── Europe ──────────────────────────────────────────────
            ['AL', 'Albania', 'Europe', 'ALL'],
            ['AD', 'Andorra', 'Europe', 'EUR'],
            ['AT', 'Austria', 'Europe', 'EUR'],
            ['BY', 'Belarus', 'Europe', 'BYN'],
            ['BE', 'Belgium', 'Europe', 'EUR'],
            ['BA', 'Bosnia and Herzegovina', 'Europe', 'BAM'],
            ['BG', 'Bulgaria', 'Europe', 'BGN'],
            ['HR', 'Croatia', 'Europe', 'EUR'],
            ['CZ', 'Czechia', 'Europe', 'CZK'],
            ['DK', 'Denmark', 'Europe', 'DKK'],
            ['EE', 'Estonia', 'Europe', 'EUR'],
            ['FI', 'Finland', 'Europe', 'EUR'],
            ['FR', 'France', 'Europe', 'EUR'],
            ['DE', 'Germany', 'Europe', 'EUR'],
            ['GR', 'Greece', 'Europe', 'EUR'],
            ['HU', 'Hungary', 'Europe', 'HUF'],
            ['IS', 'Iceland', 'Europe', 'ISK'],
            ['IE', 'Ireland', 'Europe', 'EUR'],
            ['IT', 'Italy', 'Europe', 'EUR'],
            ['XK', 'Kosovo', 'Europe', 'EUR'],
            ['LV', 'Latvia', 'Europe', 'EUR'],
            ['LI', 'Liechtenstein', 'Europe', 'CHF'],
            ['LT', 'Lithuania', 'Europe', 'EUR'],
            ['LU', 'Luxembourg', 'Europe', 'EUR'],
            ['MT', 'Malta', 'Europe', 'EUR'],
            ['MD', 'Moldova', 'Europe', 'MDL'],
            ['MC', 'Monaco', 'Europe', 'EUR'],
            ['ME', 'Montenegro', 'Europe', 'EUR'],
            ['NL', 'Netherlands', 'Europe', 'EUR'],
            ['MK', 'North Macedonia', 'Europe', 'MKD'],
            ['NO', 'Norway', 'Europe', 'NOK'],
            ['PL', 'Poland', 'Europe', 'PLN'],
            ['PT', 'Portugal', 'Europe', 'EUR'],
            ['RO', 'Romania', 'Europe', 'RON'],
            ['RU', 'Russia', 'Europe', 'RUB'],
            ['SM', 'San Marino', 'Europe', 'EUR'],
            ['RS', 'Serbia', 'Europe', 'RSD'],
            ['SK', 'Slovakia', 'Europe', 'EUR'],
            ['SI', 'Slovenia', 'Europe', 'EUR'],
            ['ES', 'Spain', 'Europe', 'EUR'],
            ['SE', 'Sweden', 'Europe', 'SEK'],
            ['CH', 'Switzerland', 'Europe', 'CHF'],
            ['UA', 'Ukraine', 'Europe', 'UAH'],
            ['GB', 'United Kingdom', 'Europe', 'GBP'],
            ['VA', 'Vatican City', 'Europe', 'EUR'],

            // ── America ─────────────────────────────────────────────
            ['AR', 'Argentina', 'America', 'ARS'],
            ['BZ', 'Belize', 'America', 'BZD'],
            ['BO', 'Bolivia', 'America', 'BOB'],
            ['BR', 'Brazil', 'America', 'BRL'],
            ['CA', 'Canada', 'America', 'CAD'],
            ['CL', 'Chile', 'America', 'CLP'],
            ['CO', 'Colombia', 'America', 'COP'],
            ['CR', 'Costa Rica', 'America', 'CRC'],
            ['EC', 'Ecuador', 'America', 'USD'],
            ['SV', 'El Salvador', 'America', 'USD'],
            ['GT', 'Guatemala', 'America', 'GTQ'],
            ['GY', 'Guyana', 'America', 'GYD'],
            ['HN', 'Honduras', 'America', 'HNL'],
            ['MX', 'Mexico', 'America', 'MXN'],
            ['NI', 'Nicaragua', 'America', 'NIO'],
            ['PA', 'Panama', 'America', 'PAB'],
            ['PY', 'Paraguay', 'America', 'PYG'],
            ['PE', 'Peru', 'America', 'PEN'],
            ['SR', 'Suriname', 'America', 'SRD'],
            ['US', 'United States', 'America', 'USD'],
            ['UY', 'Uruguay', 'America', 'UYU'],
            ['VE', 'Venezuela', 'America', 'VES'],

            // ── Caribbean ───────────────────────────────────────────
            ['AG', 'Antigua and Barbuda', 'Caribbean', 'XCD'],
            ['BS', 'Bahamas', 'Caribbean', 'BSD'],
            ['BB', 'Barbados', 'Caribbean', 'BBD'],
            ['CU', 'Cuba', 'Caribbean', 'CUP'],
            ['DM', 'Dominica', 'Caribbean', 'XCD'],
            ['DO', 'Dominican Republic', 'Caribbean', 'DOP'],
            ['GD', 'Grenada', 'Caribbean', 'XCD'],
            ['HT', 'Haiti', 'Caribbean', 'HTG'],
            ['JM', 'Jamaica', 'Caribbean', 'JMD'],
            ['KN', 'Saint Kitts and Nevis', 'Caribbean', 'XCD'],
            ['LC', 'Saint Lucia', 'Caribbean', 'XCD'],
            ['VC', 'Saint Vincent and the Grenadines', 'Caribbean', 'XCD'],
            ['TT', 'Trinidad and Tobago', 'Caribbean', 'TTD'],

            // ── Oceania ─────────────────────────────────────────────
            ['AU', 'Australia', 'Oceania', 'AUD'],
            ['FJ', 'Fiji', 'Oceania', 'FJD'],
            ['KI', 'Kiribati', 'Oceania', 'AUD'],
            ['MH', 'Marshall Islands', 'Oceania', 'USD'],
            ['FM', 'Micronesia', 'Oceania', 'USD'],
            ['NR', 'Nauru', 'Oceania', 'AUD'],
            ['NZ', 'New Zealand', 'Oceania', 'NZD'],
            ['PW', 'Palau', 'Oceania', 'USD'],
            ['PG', 'Papua New Guinea', 'Oceania', 'PGK'],
            ['WS', 'Samoa', 'Oceania', 'WST'],
            ['SB', 'Solomon Islands', 'Oceania', 'SBD'],
            ['TO', 'Tonga', 'Oceania', 'TOP'],
            ['TV', 'Tuvalu', 'Oceania', 'AUD'],
            ['VU', 'Vanuatu', 'Oceania', 'VUV'],
        ];

        $created = 0;

        foreach ($countries as $c) {
            [$iso2, $name, $region, $currency] = $c;

            // firstOrCreate: never overwrites a country that already exists,
            // so the original 23 destinations keep their is_active / sort_order.
            $country = Country::firstOrCreate(
                ['iso2' => $iso2],
                [
                    'name' => $name,
                    'region' => $region,
                    'slug' => Str::slug($name),
                    'currency_code' => $currency,
                    'is_active' => false,
                    'sort_order' => 500,
                ]
            );

            if ($country->wasRecentlyCreated) {
                $created++;
            } elseif (empty($country->region)) {
                // Backfill region on a pre-existing country that lacked one.
                $country->update(['region' => $region]);
            }
        }

        $this->command?->info("WorldCountriesSeeder: {$created} new countries added, ".(count($countries) - $created).' already present.');
    }
}
