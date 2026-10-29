<style>
.it-config {--it-ink:#20252c;--it-muted:#69727e;--it-border:#e6e9ee;color:var(--it-ink);max-width:1480px;margin:auto}
.it-config .config-header{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:20px}
.it-config .config-page-header{align-items:flex-end;gap:20px 28px}
.it-config .config-page-heading{flex:1 1 360px;min-width:0}
.it-config .config-page-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.it-config .config-page-action{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:9px 14px;border:1px solid #dce1e7;border-radius:8px;background:#fff;color:#46525f;font-size:.8rem;font-weight:600;line-height:1.25;text-decoration:none;white-space:nowrap;transition:background-color .15s,border-color .15s,color .15s}
.it-config .config-page-action i{font-size:1rem;color:#73808e;line-height:1}
.it-config .config-page-action:hover{background:#f7f9fb;border-color:#c8d0d9;color:#20252c}
.it-config .config-page-action:focus-visible{outline:3px solid #a6c9f2;outline-offset:2px}
.it-config .config-page-action-back{background:#f5f6f8;border-color:#f5f6f8}
.it-config .config-eyebrow{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:#c82333;font-weight:750;margin-bottom:6px}
.it-config h1{font-size:1.55rem;font-weight:750;letter-spacing:-.03em;margin:0 0 6px}
.it-config h2{font-size:1.1rem;font-weight:700}.it-config p{color:var(--it-muted)}
.it-config .config-panel{background:#fff;border:1px solid var(--it-border);border-radius:14px;padding:22px;margin-bottom:18px}
.it-config .btn-outline-secondary{color:#53616f;border-color:#d7dfe6;background:#fff}.it-config .btn-outline-secondary:hover{color:#26333f;border-color:#bac5cf;background:#f5f7f9}.it-config .btn-outline-secondary:disabled{color:#89949e;border-color:#e4e9ed;background:#f8f9fa}
.it-config .btn-outline-primary{color:#b32635;border-color:#d58d95;background:#fff}.it-config .btn-outline-primary:hover{color:#fff;border-color:#b32635;background:#b32635}
.it-config .config-note{border:1px solid #dce6f2;background:#f4f8fc;border-radius:10px;color:#476079;padding:12px 16px;margin-bottom:18px;font-size:.82rem}
.it-config .config-badge{display:inline-flex;align-items:center;gap:5px;background:#f1f3f5;color:#626c78;border-radius:6px;padding:5px 9px;font-size:.72rem;font-weight:650}
.it-config .config-badge.active{background:#eaf6f1;color:#28785b}.it-config .config-badge.draft{background:#fff6e7;color:#9b6a20}
.it-config .config-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
.it-config .config-stat{background:#fff;border:1px solid var(--it-border);border-radius:12px;padding:16px}.it-config .config-stat strong{display:block;font-size:1.5rem}.it-config .config-stat span{color:var(--it-muted);font-size:.8rem}
.it-config .config-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}
.it-config .config-tabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid var(--it-border);padding-bottom:12px;margin-bottom:14px}
.it-config .config-tabs button{border:1px solid transparent;border-radius:7px;background:#f5f6f8;color:#586573;padding:9px 12px;font-size:.8rem;font-weight:650;line-height:1.3}.it-config .config-tabs button:hover{background:#eaeef2}.it-config .config-tabs button[aria-selected=true]{background:#fff1f1;color:#b92635;border-color:#f5d8da}.it-config .config-tabs button:focus-visible{outline:3px solid #a6c9f2;outline-offset:2px}
.it-config .config-tab-guide{color:#667481;font-size:.78rem;line-height:1.5;background:#f8fafb;border:1px solid #edf0f3;border-radius:8px;padding:10px 12px;margin-bottom:20px}
.it-config .builder-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.it-config .builder-wide{grid-column:1/-1}
.it-config .builder-card{border:1px solid var(--it-border);border-radius:10px;padding:16px;background:#fff;margin-bottom:14px}
.it-config .builder-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}.it-config .builder-card-head strong{font-size:.87rem}
.it-config details > summary{cursor:pointer;list-style:none}.it-config details > summary::-webkit-details-marker{display:none}.it-config details > summary .builder-card-head{margin-bottom:0}.it-config details[open] > summary{margin-bottom:16px;border-bottom:1px solid var(--it-border);padding-bottom:12px}
.it-config label{font-size:.78rem;font-weight:600;color:#586370;margin-bottom:5px}.it-config .form-control,.it-config .form-select{font-size:.82rem;border-color:#dfe3e8}
.it-config .builder-help{font-size:.74rem;color:var(--it-muted);margin:5px 0 0}.it-config .builder-condition{border-left:3px solid #dce6f2;padding:10px 12px;background:#f7f9fc;border-radius:4px;margin:8px 0}.it-config .builder-condition-row{display:flex;gap:6px;align-items:center;margin:8px 0;flex-wrap:wrap}.it-config .builder-condition-row .form-control,.it-config .builder-condition-row .form-select{flex:1;min-width:100px}
.it-config .field-options{display:flex;align-items:center;flex-wrap:wrap;gap:12px 22px}
.it-config .field-options label{margin:0!important;font-weight:500;line-height:1.4}
.it-config .field-options-primary{border-top:1px solid var(--it-border);padding-top:20px;margin-top:4px}
.it-config .field-role-group{border:0;margin:0;padding:4px 0}
.it-config .field-option-title{float:none;width:auto;color:#586370;font-size:.78rem;font-weight:700;line-height:1.4;margin:0 0 10px}
.it-config .field-condition{border-top:1px solid #edf0f3;padding-top:16px;margin-top:2px}
.it-config .field-condition-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.it-config .field-condition-heading .field-option-title{margin:0}
.it-config .field-condition .builder-condition{margin-top:12px}
.it-config .field-condition .builder-actions .form-select{width:auto;min-width:170px;max-width:100%;flex:1 1 170px}
.it-config .builder-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.it-config .config-small{font-size:.76rem;color:var(--it-muted)}
.it-config .preview-area{border:1px dashed #ccd5e0;border-radius:12px;padding:20px;background:#fafbfd;margin-top:16px}.it-config .preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.it-config .preview-field-full{grid-column:1/-1}
.it-config .config-error{color:#b42335;font-size:.78rem;margin-top:5px}.it-config .config-aside{position:sticky;top:90px}.it-config .config-aside textarea{min-height:80px}
.it-config .config-aside-help ol{padding-left:18px;color:#697683;font-size:.76rem;line-height:1.6;margin:0 0 12px}.it-config .config-aside-help li+li{margin-top:6px}.it-config .config-text-link{font-size:.76rem;font-weight:650;color:#a92332;text-decoration:none}.it-config .config-text-link:hover{text-decoration:underline}
.it-config .config-team-card{padding:0;overflow:hidden}.it-config .config-team-card>summary{padding:16px 18px;display:flex;justify-content:space-between;gap:14px;align-items:center}.it-config .config-team-card[open]>summary{margin:0;padding:16px 18px;border-bottom:1px solid var(--it-border)}.it-config .config-team-summary{display:flex;align-items:center;gap:9px;flex-wrap:wrap;min-width:0}.it-config .config-team-summary strong{font-size:.88rem}.it-config .config-team-summary small{font-size:.73rem;color:var(--it-muted)}.it-config .config-team-body{padding:18px}.it-config .config-team-card>summary>i{color:#7e8995;transition:transform .15s}.it-config .config-team-card[open]>summary>i{transform:rotate(180deg)}
.it-config .team-search{max-width:400px;margin-bottom:12px}.it-config .team-members{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-height:440px;overflow:auto;padding:2px 4px 2px 0}.it-config .team-member{border:1px solid #edf0f3;border-radius:8px;padding:10px 12px}.it-config .team-member label{display:block;margin-bottom:5px}.it-config .team-reason{max-width:620px;margin-top:20px}.it-config .team-empty{padding:12px 0;color:var(--it-muted);font-size:.8rem}
.it-config .pilot-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}.it-config .pilot-stat{border:1px solid var(--it-border);background:#fafbfc;border-radius:9px;padding:14px}.it-config .pilot-stat span{display:block;color:var(--it-muted);font-size:.73rem;margin-bottom:5px}.it-config .pilot-stat strong{font-size:.88rem;overflow-wrap:anywhere}.it-config .pilot-issues{padding-left:20px;margin:10px 0 0;color:#4d5966;font-size:.82rem;line-height:1.6}.it-config .pilot-issues li+li{margin-top:6px}
.it-config .config-history td{font-size:.79rem;vertical-align:top}.it-config [hidden]{display:none!important}
.it-config .sla-intro{display:flex;align-items:flex-start;gap:14px;margin:4px 0 18px}
.it-config .sla-intro-icon{display:grid;place-items:center;flex:none;width:44px;height:44px;border-radius:12px;background:#fff1f1;color:#ce2838;font-size:1.2rem}
.it-config .sla-eyebrow{color:#bd3441;font-size:.65rem;font-weight:750;letter-spacing:.09em;margin-bottom:4px}
.it-config .sla-title{font-size:1.2rem;margin:0 0 5px}.it-config .sla-intro-copy{font-size:.82rem;margin:0}
.it-config .sla-notice{display:flex;gap:10px;align-items:flex-start;background:#f4f8fc;border:1px solid #dce8f5;border-radius:10px;color:#48617b;font-size:.78rem;padding:12px 14px}.it-config .sla-notice i{flex:none}
.it-config .sla-section{border-top:1px solid #edf0f3;margin-top:24px;padding-top:22px}
.it-config .sla-section-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.it-config .sla-section-heading-main{display:flex;align-items:flex-start;gap:11px;min-width:0}
.it-config .sla-section-number{display:grid;place-items:center;flex:none;width:30px;height:30px;border-radius:9px;background:#f2f4f7;color:#687484;font-size:.72rem;font-weight:700}
.it-config .sla-section-title{font-size:.96rem;font-weight:700;margin:0 0 4px}.it-config .sla-section-description{font-size:.77rem;margin:0;line-height:1.5}
.it-config .sla-disclosure{border:1px solid #e3e8ed;border-radius:11px;margin:10px 0;background:#fff;overflow:hidden}
.it-config .sla-disclosure-summary{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 16px;transition:background .15s}
.it-config .sla-disclosure-summary:hover{background:#f9fafb}.it-config .sla-disclosure-summary:focus-visible{outline:3px solid #a6c9f2;outline-offset:-3px}
.it-config .sla-disclosure[open] > summary{margin:0;padding:15px 16px;border-bottom:1px solid #e9edf1}
.it-config .sla-disclosure-summary > i{color:#83909c;transition:transform .15s}.it-config .sla-disclosure[open] > summary > i{transform:rotate(180deg)}
.it-config .sla-disclosure-main{min-width:0}.it-config .sla-disclosure-name{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.86rem}
.it-config .sla-disclosure-badge{border-radius:6px;background:#f1f5f9;color:#53677b;font-size:.67rem;font-weight:700;padding:3px 7px}
.it-config .sla-disclosure-subtitle{display:block;color:#75808d;font-size:.74rem;margin-top:4px;overflow-wrap:anywhere}
.it-config .sla-disclosure-body{padding:16px}.it-config .sla-manage{display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.it-config .sla-subsection{border-top:1px solid #edf0f3;margin-top:20px;padding-top:18px}.it-config .sla-subsection-title{font-size:.86rem;font-weight:700;margin:0 0 4px}.it-config .sla-subsection-help{font-size:.75rem;line-height:1.5;margin:0 0 12px}
.it-config .sla-week,.it-config .sla-target-table{border:1px solid #e8ecf0;border-radius:10px;overflow:hidden}
.it-config .sla-day{display:grid;grid-template-columns:110px minmax(0,1fr);gap:12px;align-items:start;padding:10px 12px}.it-config .sla-day + .sla-day{border-top:1px solid #edf0f3}
.it-config .sla-day-name{font-size:.78rem;padding-top:7px}.it-config .sla-window-list{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.it-config .sla-window-row{display:flex;align-items:center;gap:6px}
.it-config .sla-window-row .form-control{width:90px;min-width:0}.it-config .sla-window-separator,.it-config .sla-day-off{color:#8a95a1;font-size:.75rem}
.it-config .sla-window-remove{font-size:.7rem}.it-config .sla-inline-button{border:0;background:transparent;color:#b72d3a;font-size:.76rem;font-weight:700;padding:6px 2px}.it-config .sla-inline-button:hover{text-decoration:underline}.it-config .sla-inline-button:disabled{color:#a0a8b2;text-decoration:none}
.it-config .sla-exception{display:flex;gap:10px;align-items:end;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid #edf0f3}.it-config .sla-exception > label{min-width:180px}.it-config .sla-exception .sla-window-list{flex:1;min-width:200px}
.it-config .sla-target-header,.it-config .sla-target-row{display:grid;grid-template-columns:minmax(70px,.65fr) repeat(2,minmax(0,1fr));align-items:center;gap:12px;padding:10px 12px}
.it-config .sla-target-header{background:#f8fafb;color:#697582;font-size:.73rem;font-weight:700}.it-config .sla-target-row{border-top:1px solid #edf0f3}
.it-config .sla-priority{justify-self:start;background:#f1f4f7;border-radius:6px;color:#526170;font-size:.73rem;font-weight:700;padding:4px 8px}.it-config .sla-priority-p1{background:#fff0f0;color:#b93842}.it-config .sla-priority-p2{background:#fff5e8;color:#a96b20}
.it-config .sla-unit-input{position:relative}.it-config .sla-unit-input .form-control{padding-right:48px}.it-config .sla-unit-input > span{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:.7rem;color:#8994a0;pointer-events:none}
.it-config .sla-pause-group{border:1px solid #edf0f3;border-radius:9px;padding:12px;margin:10px 0}.it-config .sla-pause-title{float:none;width:auto;font-size:.77rem;font-weight:700;margin:0;padding:0 4px}
.it-config .sla-check-grid{display:flex;gap:8px 12px;flex-wrap:wrap}.it-config .sla-check-grid label{margin:0!important;font-weight:500}
.it-config .sla-escalation{border:1px solid #e8ecf0;background:#fafbfc;border-radius:10px;padding:14px;margin:10px 0}.it-config .sla-escalation-heading{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;font-size:.82rem}.it-config .sla-label{display:block;font-size:.76rem;font-weight:700;margin:10px 0 8px}.it-config .sla-multi-select{min-height:100px}
.it-config .sla-empty,.it-config .sla-empty-inline{border:1px dashed #dce3e9;background:#fafbfc;border-radius:10px;color:#75808d;font-size:.78rem;padding:16px}.it-config .sla-empty{text-align:center;padding:32px;margin-top:20px}.it-config .sla-empty h3{font-size:.95rem;margin:10px 0 5px}.it-config .sla-empty p{font-size:.8rem}.it-config .sla-empty-icon{color:#c82333;font-size:1.6rem}
.it-config .sla-preview-button{margin-top:14px}.it-config .sla-preview-result:not(:empty){margin-top:14px;border:1px solid #dce8f5;background:#f7faff;border-radius:10px;padding:14px}.it-config .sla-preview-summary,.it-config .sla-preview-line{display:flex;flex-direction:column;gap:3px;font-size:.77rem}.it-config .sla-preview-summary span,.it-config .sla-preview-line small,.it-config .sla-preview-footnote{color:#6f7e8c}.it-config .sla-preview-line{border-top:1px solid #e5edf5;padding-top:10px;margin-top:10px}.it-config .sla-preview-footnote{display:block;margin-top:12px;font-size:.7rem}
@media(max-width:1100px){.it-config .config-layout{grid-template-columns:1fr}.it-config .config-aside{position:static}}
@media(max-width:767px){.it-config .builder-grid,.it-config .preview-grid,.it-config .team-members,.it-config .pilot-grid{grid-template-columns:1fr}.it-config .config-stats{grid-template-columns:repeat(2,1fr)}.it-config .config-panel{padding:16px}.it-config .config-team-card{padding:0}.it-config .config-page-actions{justify-content:flex-start;width:100%}.it-config .config-page-action{flex:1 1 auto}}
@media(max-width:575px){.it-config .sla-section-heading{align-items:flex-start}.it-config .sla-section-heading > button{margin-left:41px}.it-config .sla-day{grid-template-columns:1fr;gap:6px}.it-config .sla-day-name{padding-top:0}.it-config .sla-target-header,.it-config .sla-target-row{gap:6px;padding:9px 8px;grid-template-columns:50px repeat(2,minmax(0,1fr))}.it-config .sla-target-header{font-size:.66rem}.it-config .sla-unit-input .form-control{padding-right:35px}.it-config .sla-unit-input > span{right:5px;font-size:.62rem}}
</style>
