<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::updateOrCreate(['slug' => 'home'], [
            'template_key' => 'home',
            'title' => 'Toco Japan — Home',
            'data' => [
                'promo_left' => [
                    ['tone' => 'red',    'title' => 'Kei trucks',           'sub' => '660cc · RHD',          'url' => '/vehicles?body_type=mini-truck'],
                    ['tone' => 'navy',   'title' => 'Import regulations',   'sub' => 'Per country',          'url' => '#'],
                    ['tone' => 'silver', 'title' => 'Create account',       'sub' => 'Save & request',       'url' => '/register'],
                ],
                'promo_right' => [
                    ['tone' => 'navy',   'title' => 'Auction agent',           'sub' => '69,000+ cars',         'url' => '#'],
                    ['tone' => 'red',    'title' => 'Shipping & inspection',   'sub' => 'JEVIC · JAAI',         'url' => '#'],
                    ['tone' => 'silver', 'title' => 'Banking',                 'sub' => 'Telegraphic transfer', 'url' => '#'],
                ],
                'hero_slides' => [
                    ['image' => '/img/v5/hero-1.jpg',  'alt' => ''],
                    ['image' => '/img/v5/hero-2.jpg',  'alt' => ''],
                    ['image' => '/img/v5/hero-3.jpeg', 'alt' => ''],
                ],
                'seasonal' => [
                    'image' => '/img/v5/seasonal-banner.jpg',
                    'tag' => 'Limited time',
                    'text' => '<strong>Spring Sale</strong> — extra savings on selected vehicles, this month only.',
                    'cta_label' => 'Shop sale',
                    'cta_url' => '/vehicles',
                ],
                'popular_chips' => [
                    ['label' => 'Hilux Surf',         'query_string' => '?make=toyota&q=Hilux'],
                    ['label' => 'Land Cruiser Prado', 'query_string' => '?make=toyota&vehicle_model=land-cruiser-prado'],
                    ['label' => 'Alphard Hybrid',     'query_string' => '?make=toyota&vehicle_model=alphard&fuel=hybrid'],
                    ['label' => 'Kei trucks',         'query_string' => '?body_type=mini-truck'],
                    ['label' => 'RHD SUVs',           'query_string' => '?body_type=suv&steering=right'],
                    ['label' => 'Under $5,000',       'query_string' => '?price_to=5000'],
                ],
                'why_kicker' => 'Why Toco',
                'why_headline' => 'A trusted partner from auction to your port.',
                'why_toco' => [
                    ['num' => '01', 'title' => 'Deep stock',          'body' => 'Direct access to over 69,000 cars across Japan auctions plus our own owner stock.'],
                    ['num' => '02', 'title' => 'Trusted process',     'body' => 'JEVIC + JAAI inspection, transparent paperwork, and TT banking that just works.'],
                    ['num' => '03', 'title' => 'Worldwide shipping',  'body' => 'Roll-on/roll-off and container shipping to every major port across Africa, Caribbean, Pacific.'],
                    ['num' => '04', 'title' => 'Real support',        'body' => 'Talk to people, not bots. We answer in English, French, and Japanese — usually within hours.'],
                ],
                'stats' => [
                    'lead_a' => 'By the numbers,',
                    'lead_b' => 'since 2009.',
                    'items' => [
                        ['n' => '14,200', 'unit' => '+',  'label' => 'Units shipped'],
                        ['n' => '90',     'unit' => '+',  'label' => 'Countries served'],
                        ['n' => '8,412',  'unit' => '',   'label' => 'Cars in stock'],
                        ['n' => '4.9',    'unit' => '/5', 'label' => '2,800+ reviews'],
                    ],
                ],
                'testimonials' => [
                    'kicker' => 'Worldwide deliveries',
                    'headline' => 'Customers in 90+ countries.',
                    'body' => 'Photographs from real deliveries — uploaded by customers and our shipping partners.',
                    'items' => [
                        ['name' => 'K. Muzinga', 'country' => 'Congo',       'flag' => '🇨🇩', 'image' => '/img/v5/testimonial-1.jpg'],
                        ['name' => 'Marcus O.',  'country' => 'Jamaica',     'flag' => '🇯🇲', 'image' => '/img/v5/car-2.jpg'],
                        ['name' => 'Aroha T.',   'country' => 'New Zealand', 'flag' => '🇳🇿', 'image' => '/img/v5/car-4.jpg'],
                        ['name' => 'Daniel K.',  'country' => 'Kenya',       'flag' => '🇰🇪', 'image' => '/img/v5/car-3.jpg'],
                        ['name' => 'Sione F.',   'country' => 'Fiji',        'flag' => '🇫🇯', 'image' => '/img/v5/car-1.jpg'],
                        ['name' => 'Adaeze N.',  'country' => 'Nigeria',     'flag' => '🇳🇬', 'image' => '/img/v5/car-2.jpg'],
                    ],
                ],
                'how_intro_kicker' => 'How it works',
                'how_intro_headline' => 'Four steps from browse to delivery.',
                'how_intro_body' => 'No surprises, no hidden costs — just a clear path from picking your car to driving it home.',
                'how_steps' => [
                    ['num' => '01', 'title' => 'Pick a car',     'body' => 'Browse our stock or send us a target spec.'],
                    ['num' => '02', 'title' => 'Get a CIF quote', 'body' => 'Country, port and currency picked — we cost it out.'],
                    ['num' => '03', 'title' => 'Pay & inspect',  'body' => 'TT to a Japanese bank. JEVIC/JAAI inspect on your behalf.'],
                    ['num' => '04', 'title' => 'Ship & receive', 'body' => 'Containerised or RoRo. We handle docs end to end.'],
                ],
                'cta' => [
                    'kicker' => 'Ready to import?',
                    'headline' => 'Tell us what you want — we\'ll quote and ship it.',
                    'body' => 'Get a CIF estimate to your nearest port, in your currency. No commitment until you\'re happy with the deal.',
                    'button_primary_label' => 'Request a quote',
                    'button_primary_url' => '/register',
                    'button_secondary_label' => 'Browse stock',
                    'button_secondary_url' => '/vehicles',
                ],
            ],
            'status' => 'published',
            'seo_title' => 'Toco Japan — Japanese cars, delivered worldwide',
            'seo_description' => 'Quality Japanese vehicles, exported worldwide. Browse stock, get a CIF quote, and import with confidence.',
            'locale' => 'en',
            'published_at' => now()->subWeek(),
        ]);

        Page::updateOrCreate(['slug' => 'about'], [
            'template_key' => 'default',
            'title' => 'About Toco Japan',
            'data' => [
                'kicker' => 'Since 2009',
                'headline' => 'Quality Japanese vehicles, exported with care.',
                'body' => '<p>Toco Japan has been sourcing, inspecting and shipping quality Japanese cars since 2009. Our team in Yokohama works directly with auctions and trusted owner stock to find the right vehicle for each customer — and to ship it on time, on cost, and with paperwork that lands cleanly at the destination port.</p>'
                    .'<p>We are members of <strong>JUMVEA</strong>, accredited inspectors with <strong>JEVIC</strong> and <strong>JAAI</strong>, and we ship to over 20 countries every month.</p>',
            ],
            'status' => 'published',
            'seo_title' => 'About — Toco Japan',
            'seo_description' => 'Yokohama-based Japanese auto exporter since 2009. JUMVEA member, JEVIC/JAAI inspections.',
            'locale' => 'en',
            'published_at' => now()->subWeek(),
        ]);

        Page::updateOrCreate(['slug' => 'faq'], [
            'template_key' => 'faq',
            'title' => 'Frequently asked questions',
            'data' => [
                'kicker' => 'Help & FAQ',
                'headline' => 'Common questions about importing from Japan.',
                'groups' => [
                    [
                        'title' => 'Buying & paying',
                        'items' => [
                            ['question' => 'How do I pay for a car?', 'answer' => 'TT (telegraphic transfer) to our Japanese bank account. We share full bank details once your quote is accepted.'],
                            ['question' => 'Can I buy through an auction agent?', 'answer' => 'Yes — we bid on your behalf at all major Japanese auctions including USS, TAA, and JU.'],
                            ['question' => 'Do you accept letters of credit?', 'answer' => 'Yes for orders above $50,000. Contact sales for terms.'],
                        ],
                    ],
                    [
                        'title' => 'Shipping & inspection',
                        'items' => [
                            ['question' => 'Which ports do you ship to?', 'answer' => 'Most major destinations across Africa, the Caribbean, the Pacific, and the UK/Europe. See our destination pages for the full list and current rates.'],
                            ['question' => 'Is JEVIC inspection mandatory?', 'answer' => 'It depends on the destination country. We will tell you up-front whether your country requires JEVIC, JAAI, or another inspection.'],
                            ['question' => 'How long does shipping take?', 'answer' => 'Typically 4–8 weeks from Yokohama to East Africa, 6–10 weeks to West Africa, 5–7 weeks to the Caribbean. We update you with vessel schedules.'],
                        ],
                    ],
                ],
            ],
            'status' => 'published',
            'seo_title' => 'FAQ — Toco Japan',
            'seo_description' => 'Answers to common questions about buying, paying, shipping and importing Japanese cars with Toco Japan.',
            'locale' => 'en',
            'published_at' => now()->subWeek(),
        ]);

        Page::updateOrCreate(['slug' => 'contact'], [
            'template_key' => 'contact',
            'title' => 'Contact Toco Japan',
            'data' => [
                'kicker' => 'Get in touch',
                'headline' => 'Talk to a real person in Yokohama.',
                'intro' => '<p>Sales answers within one business day, in English, French, or Japanese.</p>',
                'address_line_1' => '1-1 Minatomirai, Nishi-ku',
                'address_line_2' => 'Yokohama 220-0012, Japan',
                'map_embed_url' => '',
            ],
            'status' => 'published',
            'seo_title' => 'Contact — Toco Japan',
            'seo_description' => 'Email, phone and WhatsApp for Toco Japan in Yokohama.',
            'locale' => 'en',
            'published_at' => now()->subWeek(),
        ]);

        $lk = Country::where('iso2', 'LK')->first();
        if ($lk) {
            Page::updateOrCreate(['slug' => 'sri-lanka'], [
                'template_key' => 'country-landing',
                'title' => 'Japanese cars to Sri Lanka',
                'data' => [
                    'country_id' => $lk->id,
                    'kicker' => 'Destination · LK',
                    'headline' => 'Japanese cars to Sri Lanka — RHD, inspected, on schedule.',
                    'intro' => '<p>Sri Lanka is one of the busiest destinations from Yokohama. We ship daily to Colombo with JEVIC inspection and Sri Lanka customs paperwork sorted in advance.</p>',
                    'body' => '<p>Most popular makes for our Sri Lanka customers: Toyota Aqua, Toyota Prius, Honda Vezel, Suzuki Wagon R, and Nissan Leaf.</p>',
                ],
                'status' => 'published',
                'seo_title' => 'Japanese cars to Sri Lanka — Toco Japan',
                'seo_description' => 'Toyota, Honda, Nissan, Suzuki and more — shipped from Yokohama to Colombo. CIF estimate in 24h.',
                'locale' => 'en',
                'published_at' => now()->subWeek(),
            ]);
        }

        $tz = Country::where('iso2', 'TZ')->first();
        if ($tz) {
            Page::updateOrCreate(['slug' => 'tanzania'], [
                'template_key' => 'country-landing',
                'title' => 'Japanese cars to Tanzania',
                'data' => [
                    'country_id' => $tz->id,
                    'kicker' => 'Destination · TZ',
                    'headline' => 'Japanese cars to Tanzania — Dar es Salaam delivery.',
                    'intro' => '<p>We ship every two weeks to Dar es Salaam. JEVIC inspection on every vehicle, and we handle Tanzania Revenue Authority paperwork on your behalf.</p>',
                ],
                'status' => 'published',
                'seo_title' => 'Japanese cars to Tanzania — Toco Japan',
                'seo_description' => 'Quality used Japanese vehicles to Dar es Salaam. JEVIC inspected, TRA papers handled.',
                'locale' => 'en',
                'published_at' => now()->subWeek(),
            ]);
        }
    }
}
