{{-- CIF estimator Alpine factory, shared by the vehicle detail page and the
     listing quick-view modal. Push it once per page inside @push('scripts'). --}}
<script>
    // Countries/ports/import rules for calculators that don't inline them
    // (quick view): fetched once per page, shared by every instance.
    window.cifDestinations = window.cifDestinations || function (url) {
        if (!window.__cifDestinations) {
            window.__cifDestinations = fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : Promise.reject(r.status))
                .catch(() => { window.__cifDestinations = null; return []; });
        }
        return window.__cifDestinations;
    };

    // CIF estimator with add-ons + options. Lives in a window-scoped factory
    // so the Alpine x-data on the calculator card stays a one-liner.
    //
    // cfg.countries      inline list (detail page), or
    // cfg.countriesUrl   lazy-load it via cifDestinations() (quick view)
    // cfg.auto           quick view: calculate as soon as a port is picked and
    //                    remember the pick (window.tocoCifDest) so stepping to
    //                    the next vehicle in the modal keeps the same port.
    window.cifCalc = function (cfg) {
        return {
            countries: cfg.countries || [],
            countriesLoading: !cfg.countries,
            auto: !!cfg.auto,
            options: cfg.options,
            marineInsuranceFee: cfg.marineInsuranceFee,
            maintenanceFee: cfg.maintenanceFee,
            preInspectionFee: cfg.preInspectionFee,
            initialCif: Number(cfg.initialCif ?? 0),
            initialPortName: cfg.initialPortName || '',
            countryId: '', portId: '', ports: [],
            result: null, error: null, loading: false,
            // True once the visitor has changed port/country from the
            // preset. While true and `result` is null, the top-card CIF
            // strip hides itself rather than show a stale number.
            touched: false,
            marine: true,                  // marine insurance opt-in default ON
            maintenance: false,            // maintenance package default OFF
            preInspection: false,          // pre-inspection fee default OFF
            selectedOptions: [],           // array of vehicle_option ids
            init() {
                if (cfg.countries) {
                    this.prefill();
                } else {
                    window.cifDestinations(cfg.countriesUrl).then(list => {
                        this.countries = list;
                        this.countriesLoading = false;
                        this.prefill();
                    });
                }
            },
            prefill() {
                let preCountryId = cfg.preCountryId, prePortId = cfg.prePortId;
                const remembered = this.auto ? window.tocoCifDest : null;
                if (remembered && remembered.portId) {
                    // A port picked earlier in this modal wins over the cookie
                    // destination; hide the server-rendered CIF until recalculated.
                    if (String(remembered.portId) !== String(prePortId)) this.touched = true;
                    preCountryId = remembered.countryId;
                    prePortId = remembered.portId;
                }
                if (preCountryId) {
                    const c = this.countries.find(c => c.id == preCountryId);
                    if (c) {
                        this.ports = c.ports;
                        // Programmatic x-model assignment doesn't fire
                        // @change, so apply the mandatory pre-inspection
                        // rule (e.g. Sri Lanka) manually during prefill.
                        if (c.pre_inspection_required) {
                            this.preInspection = true;
                        }
                        const touched = this.touched;
                        this.$nextTick(() => {
                            this.countryId = preCountryId;
                            this.$nextTick(() => {
                                this.portId = prePortId;
                                this.$nextTick(() => {
                                    this.touched = touched; this.result = null;
                                    if (this.auto && this.portId) this.submit();
                                });
                            });
                        });
                    }
                }
            },
            onCountry() {
                this.portId = '';
                this.result = null;
                this.touched = true;
                const c = this.countries.find(c => c.id == this.countryId);
                this.ports = c ? c.ports : [];
                // Some countries mandate pre-inspection (e.g. Sri Lanka).
                // Force-tick the checkbox so the total reflects the
                // mandatory line; the checkbox is also disabled below.
                if (c && c.pre_inspection_required) {
                    this.preInspection = true;
                }
            },
            onPort() {
                // Stale result from the previous port would leave the top
                // CIF strip showing a number that doesn't match the picker.
                this.result = null;
                this.touched = true;
                if (this.auto && this.portId) {
                    window.tocoCifDest = { countryId: this.countryId, portId: this.portId };
                    this.submit();
                }
            },
            preInspectionMandatory() {
                const c = this.countries.find(c => c.id == this.countryId);
                return !!(c && c.pre_inspection_required);
            },
            regulation() {
                const p = this.ports.find(p => p.id == this.portId);
                return p && p.regulation ? p.regulation : null;
            },
            async submit() {
                this.error = null; this.result = null;
                if (!this.portId) { this.error = 'Pick a port.'; return; }
                this.loading = true;
                try {
                    const r = await fetch('/api/v1/cif/calculate', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ port_id: this.portId, vehicle_slug: cfg.slug })
                    });
                    const j = await r.json();
                    if (!r.ok) { this.error = j.errors?.message || j.message || 'Calculation failed.'; }
                    else { this.result = j.data; }
                } catch (e) {
                    this.error = 'Calculation failed.';
                } finally { this.loading = false; }
            },
            marineFee() { return this.marine ? Number(this.marineInsuranceFee ?? 0) : 0; },
            isSelected(optId) {
                // Checkbox :value pushes strings to the array; option ids are
                // numbers. Compare loosely so the totals actually update.
                return this.selectedOptions.some(s => Number(s) === Number(optId));
            },
            pricedOptionsTotal() {
                return this.options
                    .filter(o => this.isSelected(o.id) && o.price !== null)
                    .reduce((sum, o) => sum + Number(o.price), 0);
            },
            hasAskSelected() {
                return this.options.some(o => this.isSelected(o.id) && o.price === null);
            },
            grandTotal() {
                if (!this.result) return 0;
                const base = Number(this.result.price_fob ?? 0) + Number(this.result.freight ?? 0);
                const extras = this.marineFee()
                    + (this.maintenance ? this.maintenanceFee : 0)
                    + (this.preInspection ? this.preInspectionFee : 0)
                    + this.pricedOptionsTotal();
                return base + extras;
            },
            fmt(n) { return '$' + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
        };
    };
</script>
