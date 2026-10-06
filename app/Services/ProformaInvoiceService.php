<?php

namespace App\Services;

use App\Models\ImportRegulation;
use App\Models\Port;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Settings\ProformaSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * LC proforma invoices: who may get one, the CIF figures (same calculator
 * as checkout, so the PDF matches the price a buyer would pay) and the PDF.
 */
class ProformaInvoiceService
{
    public function __construct(
        private CifCalculator $calculator,
        private CheckoutFlow $checkout,
    ) {}

    /** Null when the vehicle can get a proforma; otherwise why not. */
    public function vehicleError(Vehicle $vehicle): ?string
    {
        if ($vehicle->status !== 'published') {
            return 'This vehicle is no longer available.';
        }

        // Same rules as online checkout: priced, has a shipping volume, and
        // (for partner stock) availability does not need a quote first.
        return $this->checkout->eligibilityError($vehicle);
    }

    /** Null when the port accepts LC; otherwise why not. */
    public function portError(Port $port): ?string
    {
        return ImportRegulation::portAllowsLc($port)
            ? null
            : 'LC payment is not available for '.$port->name.'.';
    }

    /**
     * Active ports that accept LC, grouped by country, for the destination picker.
     *
     * @return Collection<int, array{id: int, name: string, ports: list<array{id: int, name: string, rate_per_m3: float}>}>
     */
    public function lcDestinations(): Collection
    {
        $lc = ImportRegulation::lcPortIds();

        return $this->checkout->countries()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'ports' => $c->ports->filter(fn (Port $p) => isset($lc[$p->id]))
                    ->map(fn (Port $p) => ['id' => $p->id, 'name' => $p->name, 'rate_per_m3' => (float) $p->rate_per_m3])->values()->all(),
            ])
            ->filter(fn ($c) => $c['ports'] !== [])
            ->values();
    }

    /**
     * Price lines exactly as on the invoice.
     *
     * @return array{price_fob: float, discount: float, insurance: float, freight: float, total_cif: float}
     */
    public function figures(Vehicle $vehicle, Port $port): array
    {
        $cif = $this->calculator->calculate(
            priceFob: (float) $vehicle->effectivePriceFob(),
            m3: (float) $vehicle->m3,
            port: $port,
        );
        $listed = (float) ($vehicle->price_fob ?? $cif['price_fob']);

        return [
            'price_fob' => $listed,
            'discount' => round(max(0, $listed - $cif['price_fob']), 2),
            'insurance' => (float) $cif['insurance'],
            'freight' => (float) $cif['freight'],
            'total_cif' => (float) $cif['cif_total'],
        ];
    }

    /** @param  array{consignee_name: string, consignee_address: string, consignee_phone: string, consignee_email: string}  $consignee */
    public function create(User $user, Vehicle $vehicle, Port $port, array $consignee): ProformaInvoice
    {
        $port->loadMissing('country');
        $vehicle->loadMissing(['make', 'vehicleModel', 'bodyType']);
        $settings = app(ProformaSettings::class);
        $figures = $this->figures($vehicle, $port);

        return DB::transaction(function () use ($user, $vehicle, $port, $consignee, $settings, $figures) {
            // Invoice no. follows the stock no. like TOCO's invoice sheet
            // (E01948); repeat proformas for the same car get -2, -3 …
            $base = $vehicle->stock_no ?: 'V'.$vehicle->id;
            $n = ProformaInvoice::query()->where('vehicle_id', $vehicle->id)->lockForUpdate()->count();
            $invoiceNo = $n === 0 ? $base : $base.'-'.($n + 1);
            while (ProformaInvoice::query()->where('invoice_no', $invoiceNo)->exists()) {
                $invoiceNo = $base.'-'.(++$n + 1);
            }

            return ProformaInvoice::query()->create($consignee + $figures + [
                'invoice_no' => $invoiceNo,
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'dest_port_id' => $port->id,
                'issued_on' => today(),
                'expires_on' => today()->addDays(max(1, $settings->validity_days)),
                'snapshot' => $this->snapshot($vehicle, $port),
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Vehicle $vehicle, Port $port): array
    {
        $fuel = match ((string) $vehicle->fuel) {
            'petrol' => 'Gasoline / Petrol',
            'diesel' => 'Diesel',
            'hybrid' => 'Hybrid',
            'electric' => 'Electric',
            'lpg' => 'LPG',
            default => $vehicle->fuel ? ucfirst((string) $vehicle->fuel) : null,
        };

        return [
            'title' => $vehicle->title,
            'stock_no' => $vehicle->stock_no,
            'year' => $vehicle->year_first_reg,
            'registration' => $vehicle->registrationYmDisplay(),
            'make' => $vehicle->make?->name,
            'model' => $vehicle->vehicleModel?->name,
            'grade' => $vehicle->grade && preg_match('/^[\x20-\x7E]+$/', $vehicle->grade) ? $vehicle->grade : null,
            'body_type' => $vehicle->bodyType?->name,
            'chassis_number' => $vehicle->chassis_number,
            'model_code' => $vehicle->model_code,
            'color' => $vehicle->exterior_color ? ucwords((string) $vehicle->exterior_color) : null,
            'fuel' => $fuel,
            'transmission' => $vehicle->transmission ? ucfirst((string) $vehicle->transmission) : null,
            'engine_cc' => $vehicle->engine_cc,
            'mileage_km' => $vehicle->mileage_km,
            'steering' => $vehicle->steering_side === 'left' ? 'Left' : 'Right',
            'doors' => $vehicle->doors,
            'seats' => $vehicle->seats,
            'length_cm' => $vehicle->length_cm !== null ? (float) $vehicle->length_cm : null,
            'width_cm' => $vehicle->width_cm !== null ? (float) $vehicle->width_cm : null,
            'height_cm' => $vehicle->height_cm !== null ? (float) $vehicle->height_cm : null,
            'm3' => (float) $vehicle->m3,
            'photo' => $this->photoPathOrUrl($vehicle),
            'port' => $port->name,
            'country' => $port->country?->name,
        ];
    }

    /** Local file for uploaded photos (preferred), else the supplier's URL. */
    private function photoPathOrUrl(Vehicle $vehicle): ?string
    {
        $media = $vehicle->getFirstMedia('photos');
        if ($media) {
            $path = $media->hasGeneratedConversion('card') ? $media->getPath('card') : $media->getPath();

            return is_file($path) ? $path : null;
        }

        return $vehicle->externalPhotoUrls()[0] ?? null;
    }

    /** The PDF bytes. */
    public function pdf(ProformaInvoice $invoice): string
    {
        $settings = app(ProformaSettings::class);

        return Pdf::loadView('pdf.proforma-invoice', [
            'invoice' => $invoice,
            'v' => $invoice->snapshot,
            's' => $settings,
            'logo' => $this->dataUri(public_path('img/proforma/logo.jpg')),
            'stamp' => $this->dataUri(public_path('img/proforma/stamp.png')),
            'photo' => $this->dataUri($invoice->snapshot['photo'] ?? null),
        ])->setPaper('a4')->output();
    }

    /**
     * Images are embedded as data URIs (JPEG/PNG) so dompdf never fetches
     * remote URLs itself and WebP photos still render.
     */
    private function dataUri(?string $source): ?string
    {
        if (! $source) {
            return null;
        }
        try {
            $bytes = str_starts_with($source, 'http')
                ? Http::timeout(10)->get($source)->throw()->body()
                : (is_file($source) ? file_get_contents($source) : null);
        } catch (\Throwable) {
            return null;
        }
        if (! $bytes) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $img = @imagecreatefromstring($bytes);
            if ($img === false) {
                return null;
            }
            ob_start();
            imagejpeg($img, null, 85);
            $bytes = (string) ob_get_clean();
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
