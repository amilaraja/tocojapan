@php $guide = $this->guide(); @endphp
<x-filament-panels::page>
    <style>
        .crm-help { font-size: 15px; line-height: 1.65; max-width: 1000px; }
        .crm-help h2 { font-size: 20px; font-weight: 800; margin: 28px 0 10px; scroll-margin-top: 80px; }
        .crm-help h3 { font-size: 16px; font-weight: 700; margin: 20px 0 8px; }
        .crm-help p, .crm-help ul, .crm-help ol { margin: 0 0 12px; }
        .crm-help ul { list-style: disc; padding-left: 22px; }
        .crm-help ol { list-style: decimal; padding-left: 22px; }
        .crm-help li { margin: 3px 0; }
        .crm-help hr { border: 0; border-top: 1px solid rgba(127,127,127,.25); margin: 24px 0; }
        .crm-help table { width: 100%; border-collapse: collapse; margin: 6px 0 16px; font-size: 14px; display: block; overflow-x: auto; }
        .crm-help th { text-align: left; font-weight: 700; padding: 7px 10px; background: rgba(127,127,127,.1); white-space: nowrap; }
        .crm-help td { padding: 7px 10px; border-top: 1px solid rgba(127,127,127,.2); vertical-align: top; }
        .crm-help code { font-size: 13px; padding: 1px 5px; border-radius: 4px; background: rgba(127,127,127,.15); }
        .crm-help strong { font-weight: 700; }
        .crm-help a { text-decoration: underline; }
        .crm-toc a { display: block; padding: 3px 0; font-size: 14px; }
        .crm-toc a:hover { text-decoration: underline; }
    </style>

    <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
        <x-filament::section heading="Contents" compact style="flex:1 1 220px;max-width:280px;position:sticky;top:80px">
            <nav class="crm-toc">
                @foreach ($guide['toc'] as $item)
                    <a href="#{{ $item['id'] }}">{{ $item['title'] }}</a>
                @endforeach
            </nav>
        </x-filament::section>
        <x-filament::section style="flex:999 1 480px;min-width:0">
            <div class="crm-help">{!! $guide['html'] !!}</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
