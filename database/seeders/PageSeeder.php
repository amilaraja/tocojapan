<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
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
