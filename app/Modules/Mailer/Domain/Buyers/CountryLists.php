<?php

namespace App\Modules\Mailer\Domain\Buyers;

use App\Modules\Mailer\Domain\Brevo\BrevoClient;
use App\Modules\Mailer\Models\Buyer;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One Brevo list per buyer country, in a "TOCO buyers by country" folder
 * (TOC-BUY-011). Lists are created when the first buyer of a country is
 * added; existing lists with the same name are reused. Contacts are only
 * ever added: nothing is removed, unsubscribed or sent (rules 1 and 5).
 */
class CountryLists
{
    public const FOLDER_NAME = 'TOCO buyers by country';

    /** Brevo accepts up to 150 emails per "add to list" call. */
    public const CHUNK = 150;

    /** @var array<string, int>|null list name => id, loaded once per run */
    private ?array $brevoLists = null;

    public function __construct(protected BrevoClient $client, protected MailerSettings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('brevo_country_lists') && $this->client->isConfigured();
    }

    public static function listName(string $countryCode, ?string $country): string
    {
        return 'Buyers – '.($country ?: $countryCode).' ('.$countryCode.')';
    }

    /** Buyers with a country that are not on their country's list yet. */
    public function pendingCount(): int
    {
        return $this->pending()->count();
    }

    /**
     * Add pending buyers to their country lists, creating lists as needed.
     *
     * @return array{added: int, not_in_brevo: int, lists_created: int}
     */
    public function sync(float $deadline): array
    {
        $out = ['added' => 0, 'not_in_brevo' => 0, 'lists_created' => 0];

        while (microtime(true) < $deadline) {
            $first = $this->pending()->orderBy('country_code')->first(['country_code', 'country']);
            if (! $first) {
                break;
            }
            [$listId, $created] = $this->listFor($first->country_code, $first->country);
            $out['lists_created'] += $created ? 1 : 0;

            $buyers = $this->pending()->where('country_code', $first->country_code)->limit(self::CHUNK)->get(['id', 'email']);
            $result = $this->add($listId, $buyers->pluck('email')->all());
            $out['added'] += $result['added'];
            $out['not_in_brevo'] += $result['failed'];
            // Done either way: a contact Brevo does not know is added by the importer when it arrives.
            Buyer::query()->whereIn('id', $buyers->pluck('id'))->toBase()->update(['brevo_country_list_id' => $listId]);
        }

        return $out;
    }

    /** Live import: add one buyer to their country list (TOC-BUY-011). */
    public function addBuyer(Buyer $buyer): void
    {
        if (! $this->enabled() || ! $buyer->country_code) {
            return;
        }
        [$listId] = $this->listFor($buyer->country_code, $buyer->country);
        if ((int) $buyer->brevo_country_list_id === $listId) {
            return;
        }
        $this->add($listId, [$buyer->email]);
        Buyer::query()->whereKey($buyer->id)->toBase()->update(['brevo_country_list_id' => $listId]);
    }

    /** @return Builder<Buyer> */
    protected function pending(): Builder
    {
        // A buyer whose country changed is added to the new country's list (the old list keeps them).
        return Buyer::query()->whereNotNull('country_code')->where(function ($q) {
            $q->whereNull('brevo_country_list_id')
                ->orWhereNotExists(fn ($s) => $s->from('mailer_country_lists as l')
                    ->whereColumn('l.country_code', 'mailer_buyers.country_code')
                    ->whereColumn('l.brevo_list_id', 'mailer_buyers.brevo_country_list_id'));
        });
    }

    /**
     * @param  list<string>  $emails
     * @return array{added: int, failed: int}
     */
    protected function add(int $listId, array $emails): array
    {
        if ($emails === []) {
            return ['added' => 0, 'failed' => 0];
        }
        $response = $this->client->post("contacts/lists/{$listId}/contacts/add", ['emails' => array_values($emails)]);
        if ($response->status() === 400 && str_contains((string) $response->body(), 'already')) {
            return ['added' => 0, 'failed' => 0]; // all of them were already on the list
        }
        if (! $response->successful()) {
            throw new RuntimeException('Brevo did not accept the contacts for list '.$listId.' (HTTP '.$response->status().').');
        }

        return [
            'added' => count($response->json('contacts.success') ?? []),
            'failed' => count($response->json('contacts.failure') ?? []),
        ];
    }

    /** @return array{0: int, 1: bool} list id, and whether it was created now */
    protected function listFor(string $countryCode, ?string $country): array
    {
        $existing = DB::table('mailer_country_lists')->where('country_code', $countryCode)->value('brevo_list_id');
        if ($existing) {
            return [(int) $existing, false];
        }

        $name = self::listName($countryCode, $country);
        $id = $this->brevoLists()[$name] ?? null;
        $created = false;
        if (! $id) {
            $id = (int) $this->client->post('contacts/lists', ['name' => $name, 'folderId' => $this->folderId()])->throw()->json('id');
            $this->brevoLists[$name] = $id;
            $created = true;
        }
        DB::table('mailer_country_lists')->insert([
            'country_code' => $countryCode, 'brevo_list_id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id, $created];
    }

    protected function folderId(): int
    {
        if ($id = (int) $this->settings->get('brevo_country_lists_folder_id')) {
            return $id;
        }
        $offset = 0;
        do {
            $page = $this->client->get('contacts/folders', ['limit' => 50, 'offset' => $offset])->throw()->json();
            foreach ($page['folders'] ?? [] as $folder) {
                if (($folder['name'] ?? null) === self::FOLDER_NAME) {
                    $id = (int) $folder['id'];
                    break 2;
                }
            }
            $offset += 50;
        } while (count($page['folders'] ?? []) === 50);

        $id = $id ?: (int) $this->client->post('contacts/folders', ['name' => self::FOLDER_NAME])->throw()->json('id');
        $this->settings->set('brevo_country_lists_folder_id', $id);

        return $id;
    }

    /** @return array<string, int> */
    protected function brevoLists(): array
    {
        if ($this->brevoLists !== null) {
            return $this->brevoLists;
        }
        $this->brevoLists = [];
        $offset = 0;
        do {
            $page = $this->client->get('contacts/lists', ['limit' => 50, 'offset' => $offset])->throw()->json();
            foreach ($page['lists'] ?? [] as $list) {
                $this->brevoLists[(string) $list['name']] = (int) $list['id'];
            }
            $offset += 50;
        } while (count($page['lists'] ?? []) === 50);

        return $this->brevoLists;
    }
}
