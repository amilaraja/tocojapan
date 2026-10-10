<?php

namespace App\Modules\PartnerApi\Filament\Resources\Partners\Pages;

use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerForm;
use App\Modules\PartnerApi\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;

/** Create is a step-by-step wizard (UI brief 2.3); edit uses the same sections as tabs. */
class CreatePartner extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PartnerResource::class;

    protected function getSteps(): array
    {
        return collect(PartnerForm::sections())
            ->map(fn (array $components, string $label) => Step::make($label)->schema($components))
            ->values()->all();
    }

    protected function getRedirectUrl(): string
    {
        // Straight to the edit page, where connection keys are created.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
