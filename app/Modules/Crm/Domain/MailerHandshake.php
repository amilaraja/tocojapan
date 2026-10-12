<?php

namespace App\Modules\Crm\Domain;

use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Support\CrmSettings;
use App\Modules\Mailer\Domain\Brevo\BrevoClient;

/**
 * Buyers in the middle of a purchase get no "hot deals" emails (proposal
 * WEB-TOC-02 p.6). The CRM keeps one Brevo list of them; the Mailer adds it
 * as an exclusion list to every draft campaign it pushes. Only list
 * membership changes here: no contact attribute, no blacklist, no sending
 * (Mailer rules TOC-CMP-002, TOC-BRV-002/004).
 */
class MailerHandshake
{
    public const LIST_NAME = 'TOCO CRM: buying now (do not email)';

    public const SETTING = 'mailer.exclusion_list_id';

    /** Emails of customers with an open vehicle sale at stage 02 to 05. @return list<string> */
    public static function buyingNow(): array
    {
        return CrmCase::active()->where('type', Crm::TYPE_VEHICLE)->where('stage', '>=', 2)
            ->with('customer:id,email')->get()
            ->map(fn (CrmCase $case) => CustomerMatcher::normalize($case->customer?->email))
            ->filter()->unique()->values()->all();
    }

    /** @return array{list_id: ?int, added: int, removed: int, note: ?string} */
    public static function sync(BrevoClient $brevo): array
    {
        if (! $brevo->isConfigured()) {
            return ['list_id' => null, 'added' => 0, 'removed' => 0, 'note' => 'Brevo is not connected.'];
        }
        $listId = self::listId($brevo);
        if (! $listId) {
            return ['list_id' => null, 'added' => 0, 'removed' => 0, 'note' => 'Could not find or create the Brevo list.'];
        }

        $want = self::buyingNow();
        $have = self::members($brevo, $listId);
        $add = array_values(array_diff($want, $have));
        $remove = array_values(array_diff($have, $want));

        // Brevo only adds people who are already contacts; others get no campaigns anyway.
        foreach (array_chunk($add, 150) as $chunk) {
            $brevo->post("contacts/lists/{$listId}/contacts/add", ['emails' => $chunk]);
        }
        foreach (array_chunk($remove, 150) as $chunk) {
            $brevo->post("contacts/lists/{$listId}/contacts/remove", ['emails' => $chunk]);
        }

        return ['list_id' => $listId, 'added' => count($add), 'removed' => count($remove), 'note' => null];
    }

    /** The exclusion list for campaign pushes, or null (read by the Mailer's CampaignPusher). */
    public static function exclusionListId(): ?int
    {
        try {
            $id = (int) CrmSettings::get(self::SETTING);

            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function listId(BrevoClient $brevo): ?int
    {
        $known = self::exclusionListId();
        if ($known && $brevo->get("contacts/lists/{$known}")->successful()) {
            return $known;
        }

        $offset = 0;
        do {
            $page = $brevo->get('contacts/lists', ['limit' => 50, 'offset' => $offset]);
            foreach ((array) $page->json('lists') as $list) {
                if (($list['name'] ?? null) === self::LIST_NAME) {
                    CrmSettings::set(self::SETTING, (int) $list['id']);

                    return (int) $list['id'];
                }
            }
            $offset += 50;
        } while ($page->successful() && count((array) $page->json('lists')) === 50);

        $folder = (int) ($brevo->get('contacts/folders', ['limit' => 1])->json('folders.0.id') ?? 0);
        if (! $folder) {
            $folder = (int) $brevo->post('contacts/folders', ['name' => 'TOCO CRM'])->json('id');
        }
        $created = $brevo->post('contacts/lists', ['name' => self::LIST_NAME, 'folderId' => $folder]);
        $id = (int) $created->json('id');
        if ($id) {
            CrmSettings::set(self::SETTING, $id);
        }

        return $id ?: null;
    }

    /** @return list<string> */
    private static function members(BrevoClient $brevo, int $listId): array
    {
        $emails = [];
        $offset = 0;
        do {
            $page = $brevo->get("contacts/lists/{$listId}/contacts", ['limit' => 500, 'offset' => $offset]);
            $contacts = (array) $page->json('contacts');
            foreach ($contacts as $contact) {
                if ($email = CustomerMatcher::normalize($contact['email'] ?? null)) {
                    $emails[] = $email;
                }
            }
            $offset += 500;
        } while ($page->successful() && count($contacts) === 500);

        return $emails;
    }
}
