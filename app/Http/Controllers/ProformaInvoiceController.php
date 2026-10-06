<?php

namespace App\Http\Controllers;

use App\Models\Port;
use App\Models\ProformaInvoice;
use App\Models\Vehicle;
use App\Notifications\NewProformaInvoice;
use App\Services\ProformaInvoiceService;
use App\Support\StaffMail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * LC proforma invoices for signed-in customers shipping to a port whose
 * import regulation accepts LC (Admin → Import regulations → Payment modes).
 */
class ProformaInvoiceController extends Controller
{
    public function __construct(private ProformaInvoiceService $service) {}

    /** GET — confirm consignee details and the LC destination. */
    public function create(Request $request, string $slug): View|RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->with(['make', 'vehicleModel', 'media'])->firstOrFail();
        if ($error = $this->service->vehicleError($vehicle)) {
            return redirect()->route('vehicles.show', $vehicle->slug)->withErrors(['vehicle' => $error]);
        }

        $destinations = $this->service->lcDestinations();
        if ($destinations->isEmpty()) {
            return redirect()->route('vehicles.show', $vehicle->slug)->withErrors(['vehicle' => 'LC payment is not available for any destination yet.']);
        }

        $user = $request->user()->loadMissing('country');
        $portId = (int) $request->query('port_id');
        $port = $portId ? Port::with('country')->find($portId) : null;

        return view('proforma.create', [
            'vehicle' => $vehicle,
            'destinations' => $destinations,
            'selectedCountryId' => $port?->country_id ?? $destinations->firstWhere('id', $user->country_id)['id'] ?? null,
            'selectedPortId' => $port && $this->service->portError($port) === null ? $port->id : null,
            'user' => $user,
        ]);
    }

    /** POST — create the proforma and download it. */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $vehicle = Vehicle::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'port_id' => ['required', 'integer', 'exists:ports,id'],
            'consignee_name' => ['required', 'string', 'max:120'],
            'consignee_address' => ['required', 'string', 'max:500'],
            'consignee_phone' => ['required', 'string', 'max:40'],
            'consignee_email' => ['required', 'email', 'max:190'],
        ]);
        $port = Port::with('country')->findOrFail($data['port_id']);

        if ($error = $this->service->vehicleError($vehicle) ?? $this->service->portError($port)) {
            return back()->withInput()->withErrors(['port_id' => $error]);
        }

        $invoice = $this->service->create($request->user(), $vehicle, $port, [
            'consignee_name' => $data['consignee_name'],
            'consignee_address' => $data['consignee_address'],
            'consignee_phone' => $data['consignee_phone'],
            'consignee_email' => $data['consignee_email'],
        ]);

        StaffMail::send(new NewProformaInvoice($invoice), "proforma {$invoice->invoice_no}");

        return redirect()->route('proforma.index')
            ->with('status', "Proforma invoice {$invoice->invoice_no} is ready.")
            ->with('download', route('proforma.download', $invoice));
    }

    /** GET — the customer's proforma invoices. */
    public function index(Request $request): View
    {
        return view('proforma.index', [
            'invoices' => ProformaInvoice::query()
                ->where('user_id', $request->user()->id)
                ->with('vehicle')
                ->latest()
                ->paginate(20),
        ]);
    }

    /** GET — the PDF (owner only). */
    public function download(Request $request, ProformaInvoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        return response($this->service->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->fileName().'"',
        ]);
    }
}
