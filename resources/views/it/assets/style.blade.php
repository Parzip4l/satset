<style>
.it-assets { --asset-ink:#243044; --asset-muted:#6b778b; color:var(--asset-ink); }
.it-assets .asset-hero { background:#fff; border:1px solid #e5eaf0; border-radius:8px; padding:24px; }
.it-assets .asset-hero h1 { color:var(--asset-ink); font-size:1.55rem; font-weight:750; margin-bottom:7px; }
.it-assets .asset-hero p { color:var(--asset-muted); font-size:.85rem; margin:0; max-width:750px; }
.it-assets .asset-card { background:white; border:1px solid #e5eaf0; border-radius:8px; padding:20px; }
.it-assets .asset-section { font-size:1rem; font-weight:700; margin:0 0 16px; }
.it-assets .asset-label { font-size:.75rem; color:var(--asset-muted); margin-bottom:6px; }
.it-assets .asset-value { font-size:1.4rem; font-weight:750; overflow-wrap:anywhere; }
.it-assets .asset-note { color:var(--asset-muted); font-size:.76rem; line-height:1.5; }
.it-assets .asset-meta { font-weight:600; overflow-wrap:anywhere; }
.it-assets .asset-table th { background:#f5f7fa; color:var(--asset-muted); font-size:.73rem; white-space:nowrap; }
.it-assets .asset-table td { font-size:.82rem; vertical-align:middle; padding-top:15px; padding-bottom:15px; }
.it-assets .asset-history .asset-table { table-layout:fixed; width:100%; }
.it-assets .asset-history .asset-table th:first-child { width:24%; }
.it-assets .asset-history .asset-table td { overflow-wrap:anywhere; word-break:break-word; }
.it-assets .asset-history { border-left:3px solid #dce4ee; padding:0 0 24px 20px; margin-left:7px; }
.it-assets .asset-empty { padding:55px 15px; text-align:center; }
</style>
<style>
.it-assets .asset-autocomplete { position:relative; }
.it-assets .asset-suggestions { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:1050; background:#fff; border:1px solid #dfe5ec; border-radius:8px; box-shadow:0 10px 24px #2430441a; max-height:240px; overflow-y:auto; }
.it-assets .asset-suggestions button { display:block; width:100%; border:0; background:#fff; color:#243044; text-align:left; padding:10px 12px; font-size:.82rem; }
.it-assets .asset-suggestions button:hover,.it-assets .asset-suggestions button[aria-selected="true"] { background:#f1f5fa; }
</style>
<style>
.it-assets .portal-employee-results { position:static; margin-top:8px; box-shadow:none; }
</style>

<style>
.it-assets .btn { background-image:none; box-shadow:none; }
.it-assets .form-control,.it-assets .form-select { min-height:40px; font-size:.85rem; }
.it-assets .form-label { font-size:.8rem; margin-bottom:6px; }
.it-assets .form-text { font-size:.74rem; }
.it-assets .asset-filters { display:grid; grid-template-columns:minmax(220px,2fr) repeat(3,minmax(130px,1fr)); gap:18px 16px; align-items:start; }
.it-assets .asset-filters > * { min-width:0; }
.it-assets .asset-filter-employee { grid-column:span 2; }
.it-assets .asset-filter-actions { padding-top:26px; }
.it-assets .asset-filter-actions .btn { min-height:40px; flex:1; }
.it-assets .asset-spec-row { display:grid; grid-template-columns:minmax(130px,1fr) minmax(180px,2fr) 40px; align-items:end; gap:12px; margin-bottom:14px; }
.it-assets .asset-spec-row .btn { height:40px; padding:8px; }
.it-assets .asset-employee-picker { position:relative; }
.it-assets .portal-employee-results { position:absolute; top:calc(100% - 4px); margin-top:0; box-shadow:0 8px 20px #24304414; }
.it-assets .portal-employee-results button { overflow-wrap:anywhere; }
@media(max-width:991.98px) { .it-assets .asset-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } .it-assets .asset-filter-search { grid-column:span 2; } .it-assets .asset-filter-employee { grid-column:auto; } }
@media(max-width:575.98px) { .it-assets .asset-filters { grid-template-columns:minmax(0,1fr); } .it-assets .asset-filter-search { grid-column:auto; } .it-assets .asset-filter-actions { padding-top:0; } .it-assets .asset-card,.it-assets .asset-hero { padding:16px; } .it-assets .asset-spec-row { grid-template-columns:minmax(0,1fr) 40px; } .it-assets .asset-spec-row > div:first-child { grid-column:1 / -1; } }
</style>
