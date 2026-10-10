<x-filament-widgets::widget>
    <x-filament::section heading="Partner network: needs attention">
        @php($items = $this->items())
        @if ($items === [])
            <p style="opacity:.7">Nothing needs attention.</p>
        @else
            <ul style="display:grid;gap:8px">
                @foreach ($items as $item)
                    <li style="display:flex;gap:10px;align-items:baseline">
                        <span style="width:8px;height:8px;border-radius:50%;flex:none;background:{{ $item['level'] === 'danger' ? '#dc2626' : '#d97706' }}"></span>
                        <span><strong>{{ $item['partner'] }}</strong>: {{ $item['issue'] }}.
                            <a href="{{ $item['url'] }}" style="text-decoration:underline">Open</a></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
