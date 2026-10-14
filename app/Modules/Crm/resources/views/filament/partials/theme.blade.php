{{--
    Shared CRM look (client change 2026-10-14: the CRM uniformly colourful).
    Include once per CRM page. Plain CSS: the admin theme CSS is prebuilt on the server.
    Colours come from App\Modules\Crm\Support\CrmUi (STAGE_COLOURS, PALETTE).
--}}
<style>
    /* Coloured top bar on a Filament section: wrap it in <div class="crm-accent" style="--c:#hex"> */
    .crm-accent > .fi-section{border-top:4px solid var(--c);border-top-left-radius:12px;border-top-right-radius:12px}
    .crm-h{display:inline-flex;align-items:center;gap:8px}
    .crm-h .dot{width:10px;height:10px;border-radius:999px;background:var(--c);box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 25%,transparent)}
    .crm-count{display:inline-block;min-width:22px;text-align:center;border-radius:999px;padding:1px 8px;font-size:12px;font-weight:700;color:#fff;background:var(--c)}
    .crm-chip{display:inline-block;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:700;line-height:1.6;color:#fff;background:var(--c);white-space:nowrap}
    .crm-soft{display:inline-block;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:700;line-height:1.6;white-space:nowrap;color:var(--c);background:color-mix(in srgb,var(--c) 14%,transparent)}
    .crm-avatar{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:999px;font-size:10px;font-weight:800;color:#fff;background:var(--c);flex:none}
    .crm-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 10px;margin:0 -10px;border-top:1px solid rgba(127,127,127,.15);font-size:14px;border-radius:8px;transition:background .12s}
    .crm-row:hover{background:color-mix(in srgb,var(--c,#6366f1) 8%,transparent)}
    .crm-ref{font-weight:700;color:var(--c,#6366f1)}
    .crm-muted{opacity:.65;font-size:12px}
    /* Pipeline columns */
    .crm-board{display:grid;grid-template-columns:repeat(5,minmax(185px,1fr));gap:12px;overflow-x:auto;padding-bottom:8px}
    .crm-col{border-radius:12px;background:color-mix(in srgb,var(--c) 7%,transparent);border:1px solid color-mix(in srgb,var(--c) 25%,transparent);min-height:220px;overflow:hidden}
    .crm-col-head{padding:10px 12px;color:#fff;background:linear-gradient(135deg,var(--c),color-mix(in srgb,var(--c) 70%,#fff))}
    .crm-col-head .n{font-size:11px;font-weight:700;opacity:.9;letter-spacing:.05em}
    .crm-col-head .t{font-size:15px;font-weight:800}
    .crm-col-head .s{display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-top:4px}
    .crm-col-head .s b{background:rgba(255,255,255,.25);border-radius:999px;padding:1px 9px}
    .crm-col-body{padding:8px}
    .crm-card{display:block;background:var(--crm-card-bg,#fff);color:inherit;border:1px solid rgba(127,127,127,.2);border-left:4px solid var(--sla);border-radius:10px;padding:9px 10px;margin-top:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:transform .12s,box-shadow .12s}
    .crm-card:hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(0,0,0,.1)}
    .dark .crm-card{--crm-card-bg:rgba(255,255,255,.05)}
    .crm-empty{text-align:center;padding:24px 8px;font-size:12px;opacity:.55}
    /* Five-stage bar (Case page) */
    .crm-stages{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}
    .crm-stage{border-radius:10px;padding:8px 10px;border:1px solid color-mix(in srgb,var(--c) 40%,transparent);background:color-mix(in srgb,var(--c) 12%,transparent)}
    .crm-stage.now{color:#fff;border-color:var(--c);background:linear-gradient(135deg,var(--c),color-mix(in srgb,var(--c) 70%,#fff));box-shadow:0 4px 12px color-mix(in srgb,var(--c) 35%,transparent)}
    .crm-stage.next{border-color:rgba(127,127,127,.25);background:rgba(127,127,127,.06)}
    .crm-stage .t{font-weight:800;font-size:13px}
    .crm-stage .d{font-size:12px;opacity:.75}
    @media (max-width:700px){.crm-stages{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
