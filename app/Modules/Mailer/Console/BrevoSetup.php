<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use Illuminate\Console\Command;
use Throwable;

/** Creates the contact attributes the importer writes, when missing (TOC-BRV-004). */
class BrevoSetup extends Command
{
    /** @var array<string, string> name => Brevo type */
    public const ATTRIBUTES = [
        'SOURCE' => 'text',
        'TOCO_IMPORTED_AT' => 'date',
        'TOCO_LAST_ENQUIRY_AT' => 'date',
        'FIRSTNAME' => 'text',
        'LASTNAME' => 'text',
        'COUNTRY' => 'text',
        'PHONE' => 'text',
    ];

    public const ATTRIBUTES_NEEDED = ['SOURCE', 'TOCO_IMPORTED_AT', 'TOCO_LAST_ENQUIRY_AT', 'FIRSTNAME', 'LASTNAME', 'COUNTRY', 'PHONE'];

    protected $signature = 'mailer:brevo:setup';

    protected $description = 'Create missing Brevo contact attributes used by TOCO Mailer';

    public function handle(BrevoClient $client): int
    {
        try {
            $existing = collect($client->get('contacts/attributes')->throw()->json('attributes') ?? [])->pluck('name')->all();

            foreach (self::ATTRIBUTES as $name => $type) {
                if (in_array($name, $existing, true)) {
                    $this->line("  {$name}: already there");

                    continue;
                }
                $client->post("contacts/attributes/normal/{$name}", ['type' => $type])->throw();
                $this->info("  {$name}: created ({$type})");
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
