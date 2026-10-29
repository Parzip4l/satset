(() => {
    'use strict';
    const form = document.getElementById('it-request-form'); if (!form) return;
    const root = document.getElementById('it-request-fields'), feedback = document.getElementById('it-request-feedback');
    const catalog = document.getElementById('it-catalog'), submit = document.getElementById('it-request-submit');
    let answers = JSON.parse(document.getElementById('it-request-initial').textContent), uploads = {}, sequence = 0, fields = [], dependencies = new Set();
    const el = (tag, cls, text) => { const n=document.createElement(tag); if(cls)n.className=cls; if(text!==undefined)n.textContent=text; return n; };
    const message = (text) => { feedback.replaceChildren(el('div','alert alert-danger',text)); };
    async function refresh() {
        const current=++sequence; submit.disabled=true;
        try {
            const response=await fetch(form.dataset.schema,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},body:JSON.stringify({catalog_key:catalog.value,answers})});
            const data=await response.json(); if(current!==sequence)return; if(!response.ok)throw new Error(data.message||'Form tidak dapat dimuat.');
            fields=data.fields; dependencies=new Set(data.dependencies||[]); if(data.configuration_version_id){let version=form.querySelector('[name=configuration_version_id]');if(!version){version=el('input');version.type='hidden';version.name='configuration_version_id';form.append(version);}version.value=data.configuration_version_id;} const visible=new Set(fields.map(f=>f.key));
            for(const key of Object.keys(answers))if(!visible.has(key))delete answers[key];
            for(const key of Object.keys(uploads))if(!visible.has(key))delete uploads[key];
            render(); submit.disabled=false;
        } catch(error) {message(error.message);}
    }
    function render() {
        const focused=document.activeElement?.id, selection=document.activeElement?.selectionStart;
        root.replaceChildren();
        for(const f of fields) {
            const wrap=el('div',f.width===6?'col-md-6':'col-12'); const id=`answer-${f.key}`;
            const label=el('label','form-label',f.label+(f.required?' *':'')); label.htmlFor=id; wrap.append(label);
            let control;
            const value=Object.hasOwn(answers,f.key)?answers[f.key]:f.value;
            if(['select','radio','reference','multiselect'].includes(f.type)) {
                control=el('select','form-select'); control.multiple=f.type==='multiselect';
                if(!control.multiple){const blank=el('option','','Pilih...');blank.value='';control.append(blank);}
                for(const option of f.options) {const n=el('option','',option.label);n.value=option.value;n.selected=control.multiple?(Array.isArray(value)&&value.map(String).includes(String(option.value))):String(value??'')===String(option.value);control.append(n);}
            } else if(f.type==='textarea') {control=el('textarea','form-control');control.rows=3;control.value=value??'';}
            else {control=el('input',f.type==='checkbox'?'form-check-input':'form-control');control.type=({integer:'number',decimal:'number',datetime:'datetime-local',checkbox:'checkbox',date:'date',time:'time',file:'file'})[f.type]||'text';
                if(f.type==='file'){control.multiple=(f.max_files||1)>1;control.accept=(f.allowed_mimes||[]).map(x=>'.'+x).join(',');}
                else if(f.type==='checkbox')control.checked=!!value;else control.value=value??'';
                if(f.type==='decimal')control.step='any';
            }
            control.id=id;control.disabled=!!f.read_only;control.required=!!f.required&&f.type!=='file';
            if(f.placeholder)control.placeholder=f.placeholder;if(f.max_length)control.maxLength=f.max_length;
            const update=()=>{if(f.type==='file')uploads[f.key]=Array.from(control.files);else answers[f.key]=f.type==='checkbox'?control.checked:f.type==='multiselect'?Array.from(control.selectedOptions,o=>o.value):control.value;};
            control.addEventListener('input',update);control.addEventListener('change',()=>{update();if(dependencies.has(f.key))refresh();});
            if(!f.read_only&&f.type!=='file'&&!Object.hasOwn(answers,f.key)&&value!=null)answers[f.key]=value;
            wrap.append(control);if(f.help)wrap.append(el('div','form-text',f.help));
            if(f.type==='file'&&uploads[f.key]?.length)wrap.append(el('div','form-text',uploads[f.key].map(x=>x.name).join(', ')));
            root.append(wrap);
        }
        if(focused){const target=document.getElementById(focused);if(target){target.focus({preventScroll:true});if(typeof selection==='number'&&typeof target.selectionStart==='number')target.setSelectionRange(selection,selection);}}
    }
    catalog.addEventListener('change',()=>{answers={};uploads={};refresh();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();submit.disabled=true;feedback.replaceChildren();
        const body=new FormData(form);
        for(const f of fields) {
            if(f.read_only)continue;const value=answers[f.key];
            if(f.type==='file'){for(const file of uploads[f.key]||[])body.append(`uploads[${f.key}][]`,file);}
            else if(Array.isArray(value)){for(const item of value)body.append(`answers[${f.key}][]`,item);}
            else if(value!=null)body.append(`answers[${f.key}]`,typeof value==='boolean'?(value?'1':'0'):String(value));
        }
        // Empty PHP arrays need no artificial field; the server normalizes absent answers.
        try {const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body});const data=await response.json();
            if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Permintaan gagal.');
            window.location.assign(data.url||`/it/tickets/${data.id}`);
        } catch(error){message(error.message);submit.disabled=false;}
    });
    refresh();
})();
