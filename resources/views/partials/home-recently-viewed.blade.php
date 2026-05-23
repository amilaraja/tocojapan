<div x-data="recentlyViewed()" x-init="init()" x-show="hasItems" x-cloak>
    @include('partials.home-section-heading', [
        'kicker' => 'For you',
        'heading' => 'Recently Viewed',
        'icon' => 'clock',
        'sublabel' => null,
        'viewAllUrl' => null,
    ])

    <div x-html="markup" class="recently-viewed-strip"></div>
</div>

@once
@push('scripts')
<script>
    function recentlyViewed() {
        return {
            hasItems: false,
            markup: '',
            async init() {
                let slugs = [];
                try {
                    slugs = JSON.parse(localStorage.getItem('toco_recent_vehicles') || '[]');
                } catch (e) { slugs = []; }
                if (!Array.isArray(slugs) || slugs.length === 0) return;

                try {
                    const resp = await fetch('{{ route("vehicles.recently-viewed") }}?slugs=' + encodeURIComponent(slugs.join(',')), {
                        headers: { 'Accept': 'text/html' },
                    });
                    if (!resp.ok || resp.status === 204) return;
                    this.markup = await resp.text();
                    this.hasItems = true;
                } catch (e) { /* silent */ }
            },
        };
    }
</script>
@endpush
@endonce
