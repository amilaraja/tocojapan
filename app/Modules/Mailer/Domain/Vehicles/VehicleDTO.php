<?php

namespace App\Modules\Mailer\Domain\Vehicles;

/**
 * Read-only view of a tocojapan.com vehicle for the Mailer (TOC-VEH-002).
 * Also the shape stored as a campaign vehicle snapshot (TOC-CB-004).
 */
final class VehicleDTO
{
    public const BADGE_NEW = 'new';

    public const BADGE_HOT_DEAL = 'hot_deal';

    /** Available for new campaigns. */
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_SOLD = 'sold';

    /** Draft, deleted or not found: cannot be emailed. */
    public const STATUS_UNAVAILABLE = 'unavailable';

    public function __construct(
        public readonly int $id,
        public readonly ?string $stockRef,
        public readonly string $title,
        public readonly ?string $make,
        public readonly ?string $model,
        public readonly ?string $bodyType,
        public readonly ?int $modelYear,
        public readonly ?int $registrationYear,
        public readonly ?int $mileageKm,
        public readonly ?string $transmission,
        public readonly ?float $priceFob,
        public readonly ?float $previousPrice,
        public readonly bool $priceOnRequest,
        public readonly ?string $badge,
        public readonly string $status,
        public readonly ?string $photoUrl,
        public readonly ?string $photoPath,
        public readonly string $url,
    ) {}

    public function isSelectable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /** Meta line as on the site card: "2022 · 50,002 km · Automatic". */
    public function metaLine(): string
    {
        return implode(' · ', array_filter([
            $this->registrationYear ? (string) $this->registrationYear : null,
            $this->mileageKm !== null ? number_format($this->mileageKm).' km' : null,
            $this->transmission,
        ]));
    }

    public function badgeLabel(): ?string
    {
        return match ($this->badge) {
            self::BADGE_HOT_DEAL => 'HOT DEAL',
            self::BADGE_NEW => 'NEW',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            stockRef: $data['stockRef'] ?? null,
            title: (string) ($data['title'] ?? ''),
            make: $data['make'] ?? null,
            model: $data['model'] ?? null,
            bodyType: $data['bodyType'] ?? null,
            modelYear: isset($data['modelYear']) ? (int) $data['modelYear'] : null,
            registrationYear: isset($data['registrationYear']) ? (int) $data['registrationYear'] : null,
            mileageKm: isset($data['mileageKm']) ? (int) $data['mileageKm'] : null,
            transmission: $data['transmission'] ?? null,
            priceFob: isset($data['priceFob']) ? (float) $data['priceFob'] : null,
            previousPrice: isset($data['previousPrice']) ? (float) $data['previousPrice'] : null,
            priceOnRequest: (bool) ($data['priceOnRequest'] ?? false),
            badge: $data['badge'] ?? null,
            status: (string) ($data['status'] ?? self::STATUS_UNAVAILABLE),
            photoUrl: $data['photoUrl'] ?? null,
            photoPath: $data['photoPath'] ?? null,
            url: (string) ($data['url'] ?? ''),
        );
    }
}
