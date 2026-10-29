(() => {
    'use strict';
    const root = document.getElementById('it-configuration-editor');
    if (!root) return;
    const initial = JSON.parse(document.getElementById('it-configuration-data').textContent);
    const config = initial.configuration;
    const editable = root.dataset.editable === '1';
    const roles = ['requester', 'technician', 'approver'];
    const roleNames = {requester: 'Pemohon', technician: 'Teknisi', approver: 'Approver'};
    const types = {text:'Teks pendek', textarea:'Teks panjang', integer:'Angka bulat', decimal:'Desimal', date:'Tanggal', datetime:'Tanggal & waktu', time:'Waktu', select:'Dropdown', multiselect:'Pilihan jamak', radio:'Pilihan tunggal', checkbox:'Checkbox', file:'Lampiran', reference:'Referensi master'};
    const container = document.getElementById('config-builder');
    let selectedForm = config.forms[0], expandedField = null;
    let tab = 'services', dirty = false, lock = Number(root.dataset.lock), saving = false;
    let previewForm = config.forms[0]?.key || '', previewRole = 'requester', samples = {}, files = {}, previewSequence = 0;
    const expandedSlaCalendars = new WeakSet(), expandedSlaPolicies = new WeakSet();
    const tabGuide = {services:'Tentukan layanan, pemilik, dan tim yang bertanggung jawab.',categories:'Susun kategori agar permintaan mudah dikelompokkan.',catalog:'Hubungkan item layanan dengan kategori dan formulir yang sesuai.',forms:'Atur pertanyaan yang diisi pemohon dan siapa yang dapat melihatnya.',locations:'Kelola pilihan lokasi yang digunakan pada formulir.',operations:'Tentukan prioritas, tim tujuan, dan aturan persetujuan.',sla:'Atur jam layanan, target waktu, dan eskalasi.',preview:'Periksa formulir dan validasi menggunakan draft yang sudah disimpan.'};
    const el = (tag, cls, text) => {const node = document.createElement(tag); if (cls) node.className = cls; if (text !== undefined) node.textContent = text; return node;};
    const feedback = (message, failure = false) => {const area = document.getElementById('config-feedback'); area.replaceChildren(el('div', `alert alert-${failure ? 'danger' : 'success'}`, message));};
    const changed = () => {dirty = true; const status = document.getElementById('config-save-status'); if (status) status.textContent = 'Ada perubahan lokal yang belum disimpan.';};
    const button = (text, action, cls = 'btn btn-outline-secondary btn-sm', permitted = editable) => {const node = el('button', cls, text); node.type = 'button'; node.disabled = !permitted; node.addEventListener('click', action); return node;};
    const label = (text, input) => {const wrap = el('div'); const id = `cfg-${Math.random().toString(36).slice(2)}`; input.id = id; const title = el('label', '', text); title.htmlFor = id; wrap.append(title, input); return wrap;};
    const textInput = (value, callback, type = 'text') => {const node = el(type === 'textarea' ? 'textarea' : 'input', 'form-control'); if (type !== 'textarea') node.type = type; else node.rows = 2; node.value = value ?? ''; node.disabled = !editable; node.addEventListener('input', () => {callback(node.value); changed();}); return node;};
    const select = (options, value, callback, enabled = editable, markDirty = true) => {const node = el('select', 'form-select'); for (const [key, name] of options) {const option = el('option', '', name); option.value = key; option.selected = String(key) === String(value ?? ''); node.append(option);} node.disabled = !enabled; node.addEventListener('change', () => {callback(node.value); if (markDirty && editable) changed();}); return node;};
    const check = (text, value, callback) => {const wrap = el('label', 'd-inline-flex align-items-center gap-2 me-3'); const node = el('input', 'form-check-input m-0'); node.type = 'checkbox'; node.checked = !!value; node.disabled = !editable; node.addEventListener('change', () => {callback(node.checked); changed();}); wrap.append(node, document.createTextNode(text)); return wrap;};
    const choices = (section, empty = 'Pilih...') => [['', empty], ...config[section].map(item => [item.key, `${item.name} (${item.key})`])];
    const optionalNumber = (object, name, value) => {if (value === '') delete object[name]; else object[name] = Number(value);};
    const cardHead = (title, array, index) => {const head = el('div', 'builder-card-head'); head.append(el('strong', '', title)); const actions = el('div', 'builder-actions'); actions.append(button('↑', () => {if(index) {[array[index-1], array[index]]=[array[index],array[index-1]]; changed(); render();}}, undefined), button('↓', () => {if(index<array.length-1) {[array[index+1], array[index]]=[array[index],array[index+1]]; changed(); render();}}), button('Hapus dari draft', () => {array.splice(index,1); changed(); render();}, 'btn btn-light btn-sm')); head.append(actions); return head;};
    function master(section) {
        const names = {services:'layanan', categories:'kategori', catalog:'item katalog', locations:'lokasi'};
        const head = el('div', 'config-header'); head.append(el('h2','mb-0',`Daftar ${names[section]}`), button(`+ Tambah ${names[section]}`, () => {
            const key = `${section.slice(0,3)}_${Date.now().toString(36)}`;
            const item = {key, name:'', is_active:true};
            if(section==='services') Object.assign(item,{description:'', owner_id:null, fallback_team_id:null});
            if(section==='categories'||section==='locations') item.parent_key=null;
            if(section==='catalog') Object.assign(item,{description:'',service_key:config.services[0]?.key||'',category_key:config.categories[0]?.key||'',form_key:config.forms[0]?.key||'',record_type:config.forms[0]?.record_type||'incident'});
            config[section].push(item); changed(); render();
        })); container.append(head);
        container.append(el('p','config-small','Kode adalah identitas stabil. Nonaktifkan master yang sudah digunakan. Versi terpublikasi tetap tersimpan.'));
        config[section].forEach((item,index) => {
            const card = el('div','builder-card'); card.append(cardHead(item.name||`${names[section]} baru`,config[section],index));
            const grid = el('div','builder-grid');
            grid.append(label('Kode',textInput(item.key,v=>item.key=v)),label('Nama',textInput(item.name,v=>item.name=v)));
            if ('description' in item) {const desc = label('Deskripsi',textInput(item.description,v=>item.description=v,'textarea')); desc.classList.add('builder-wide'); grid.append(desc);}
            if(section==='services') {
                grid.append(label('Service owner',select([['','Belum ditetapkan'],...initial.users.map(user=>[user.id,`${user.name} — ID ${user.id}`])],item.owner_id,v=>item.owner_id=v?Number(v):null)), label('Tim fallback',select([['','Belum ditetapkan'],...initial.teams.map(team=>[team.id,team.name+(team.is_active?'':' (draft/nonaktif)')])],item.fallback_team_id,v=>item.fallback_team_id=v?Number(v):null)));
            }
            if(section==='categories'||section==='locations') grid.append(label('Parent',select([['','Tanpa parent'],...config[section].filter(other=>other!==item).map(other=>[other.key,other.name||other.key])],item.parent_key,v=>item.parent_key=v||null)));
            if(section==='catalog') {
                grid.append(label('Layanan IT',select(choices('services'),item.service_key,v=>item.service_key=v)),label('Kategori',select(choices('categories'),item.category_key,v=>item.category_key=v)));
                grid.append(label('Jenis catatan',select([['incident','Incident'],['service_request','Service Request']],item.record_type,v=>item.record_type=v)),label('Template form',select(choices('forms'),item.form_key,v=>item.form_key=v)));
            }
            const state = el('div','builder-wide'); state.append(check('Aktif dalam katalog konfigurasi',item.is_active,v=>item.is_active=v)); if(section==='catalog')state.append(check('Tiket rahasia (akses petugas dibatasi)',item.is_restricted||false,v=>item.is_restricted=v)); grid.append(state); card.append(grid); container.append(card);
        });
        if(!config[section].length) container.append(el('p','config-small','Belum ada data. Tambahkan melalui tombol di atas.'));
    }
    function conditionEditor(field, property, fields) {
        const wrap = el('div','builder-wide field-condition');
        const heading=el('div','field-condition-heading');heading.append(el('div','field-option-title',property==='visible_when'?'Tampilkan jika':'Wajib diisi jika'));
        if(!field[property]) {heading.append(button('+ Tambah kondisi',()=>{field[property]={mode:'all',rules:[]}; changed(); render();}));wrap.append(heading);return wrap;}
        wrap.append(heading);
        const cast = (value, source) => source?.type==='checkbox' ? value==='true' : ['integer','decimal'].includes(source?.type) && value!=='' ? Number(value) : value;
        const tree = (node, parent, index, depth) => {
            const group = el('div','builder-condition');
            if (node.mode) {
                const controls = el('div','builder-actions');
                controls.append(select([['all','Semua kondisi (AND)'],['any','Salah satu (OR)']],node.mode,v=>node.mode=v),button('+ Syarat',()=>{const target=fields.find(other=>other!==field); node.rules.push({field:target?.key||'',operator:'equals',value:''}); changed(); render();}));
                if(depth<2) controls.append(button('+ Grup',()=>{node.rules.push({mode:'all',rules:[]}); changed(); render();}));
                controls.append(button('Hapus',()=>{if(parent) parent.splice(index,1); else delete field[property]; changed(); render();},'btn btn-light btn-sm'));
                group.append(controls); node.rules.forEach((rule,i)=>group.append(tree(rule,node.rules,i,depth+1)));
                if(!node.rules.length) group.append(el('p','builder-help','Tambahkan setidaknya satu syarat sebelum menyimpan.'));
            } else {
                const row = el('div','builder-condition-row');
                row.append(select([['','Field sumber'],...fields.filter(other=>other!==field&&other.type!=='file').map(other=>[other.key,other.label||other.key])],node.field,v=>{node.field=v; node.value='';}),select([['equals','Sama dengan'],['not_equals','Tidak sama'],['in','Salah satu nilai'],['is_empty','Kosong']],node.operator,v=>{node.operator=v;if(v==='is_empty')delete node.value;else node.value=v==='in'?[]:''; render();}));
                if(node.operator!=='is_empty') {
                    const input=textInput(Array.isArray(node.value)?node.value.join(', '):node.value,v=>{const source=fields.find(f=>f.key===node.field); node.value=node.operator==='in'?v.split(',').map(x=>cast(x.trim(),source)):cast(v,source);}); input.placeholder=node.operator==='in'?'wifi, lan':'Nilai / kode pilihan'; row.append(input);
                }
                row.append(button('×',()=>{if(parent)parent.splice(index,1);else delete field[property];changed();render();},'btn btn-light btn-sm')); group.append(row);
            }
            return group;
        };
        wrap.append(tree(field[property],null,0,0)); return wrap;
    }
    function formBuilder() {
        const top=el('div','config-header'); top.append(el('h2','mb-0','Template & field'),button('+ Tambah template',()=>{selectedForm={key:`form_${Date.now().toString(36)}`,name:'Form baru',record_type:'incident',fields:[]};config.forms.push(selectedForm);changed();render();})); container.append(top);
        if(!config.forms.includes(selectedForm))selectedForm=config.forms[0];
        if(config.forms.length)container.append(label('Template yang diedit',select(config.forms.map(form=>[form.key,form.name]),selectedForm?.key,key=>{selectedForm=config.forms.find(form=>form.key===key);render();},true,false)));
        config.forms.forEach((form,index)=>{
            if(form!==selectedForm)return;

            const panel=el('div','builder-card'); panel.append(cardHead(`${form.name} · ${form.fields.length} field`,config.forms,index));
            const grid=el('div','builder-grid'); grid.append(label('Kode template',textInput(form.key,v=>form.key=v)),label('Nama template',textInput(form.name,v=>form.name=v)),label('Jenis catatan',select([['incident','Incident'],['service_request','Service Request']],form.record_type,v=>form.record_type=v))); panel.append(grid);
            const addField=()=>{const field={key:`field_${Date.now().toString(36)}`,label:'Field baru',type:'text',visible_roles:[...roles],read_only_roles:[],required:false,width:12,reportable:false};form.fields.push(field);expandedField=field;changed();render();};
            panel.append(button('+ Tambah field',addField,'btn btn-outline-secondary btn-sm mt-3'));
            form.fields.forEach((field,j)=>{
                const card=el('details','builder-card mt-3');card.open=expandedField===field;const summary=el('summary');summary.append(cardHead(`${j+1}. ${field.label||'Field baru'} · ${types[field.type]}`,form.fields,j));card.append(summary);card.addEventListener('toggle',()=>{if(card.open)expandedField=field;});const props=el('div','builder-grid');
                props.append(label('Kode field',textInput(field.key,v=>field.key=v)),label('Label',textInput(field.label,v=>field.label=v)),label('Tipe',select(Object.entries(types),field.type,v=>{field.type=v;delete field.default;delete field.source;delete field.options;if(['select','radio','multiselect'].includes(v))field.options=[{value:'pilihan_1',label:'Pilihan 1'}];if(v==='reference')field.source='locations';changed();render();})),label('Section',textInput(field.section,v=>field.section=v)),label('Lebar',select([['12','Penuh'],['6','Setengah']],field.width??12,v=>field.width=Number(v))),label('Bantuan pengisian',textInput(field.help,v=>field.help=v)));
                props.append(label('Placeholder',textInput(field.placeholder,v=>field.placeholder=v)));
                if(['text','textarea'].includes(field.type)) props.append(label('Format',select([['','Teks biasa'],['email','Email'],['url','URL http/https'],['phone','Nomor telepon']],field.format,v=>{if(v)field.format=v;else delete field.format;})));
                const limits=['text','textarea'].includes(field.type)?[['min_length','Panjang minimum'],['max_length','Panjang maksimum']]:['integer','decimal'].includes(field.type)?[['min','Minimum'],['max','Maksimum']]:[];
                limits.forEach(([key,title])=>props.append(label(title,textInput(field[key],v=>optionalNumber(field,key,v),'number'))));
                if(field.type==='reference') props.append(label('Master sumber',select([['it_services','Layanan IT'],['it_categories','Kategori IT'],['locations','Lokasi'],['support_teams','Tim aktif (internal)']],field.source,v=>field.source=v)));
                if(['select','radio','multiselect'].includes(field.type)) {
                    const options=label('Pilihan — satu baris: kode|label',textInput((field.options||[]).map(o=>`${o.value}|${o.label}`).join('\n'),v=>field.options=v.split('\n').filter(x=>x.trim()).map(line=>{const [value,...name]=line.split('|');return{value:value.trim(),label:name.join('|').trim()||value.trim()};}),'textarea')); options.classList.add('builder-wide');props.append(options);
                }
                if(field.type==='file') {
                    props.append(label('Jumlah file (1–5)',textInput(field.max_files??1,v=>optionalNumber(field,'max_files',v),'number')),label('Batas per file (KB, maks. 5120)',textInput(field.max_size_kb??5120,v=>optionalNumber(field,'max_size_kb',v),'number')),label('Ekstensi diizinkan (pisahkan koma)',textInput((field.allowed_mimes||['pdf','jpg','jpeg','png']).join(', '),v=>field.allowed_mimes=v.split(',').map(x=>x.trim()).filter(Boolean))));
                } else {
                    const value=field.default; props.append(label('Default (kosong = tanpa default)',textInput(Array.isArray(value)?value.join(', '):value,v=>{if(v===''){delete field.default;return;}field.default=field.type==='checkbox'?v==='true':field.type==='multiselect'?v.split(',').map(x=>x.trim()):['integer','decimal'].includes(field.type)?Number(v):v;})));
                }
                const toggles=el('div','builder-wide field-options field-options-primary'); toggles.append(check('Wajib diisi',field.required,v=>field.required=v),check('Dapat dipakai laporan',field.reportable,v=>field.reportable=v));props.append(toggles);
                for(const [key,title] of [['visible_roles','Terlihat oleh'],['read_only_roles','Read-only untuk']]) {
                    const row=el('fieldset','builder-wide field-role-group');row.append(el('legend','field-option-title',title));const options=el('div','field-options');roles.forEach(role=>options.append(check(roleNames[role],(field[key]||[]).includes(role),v=>{field[key]=v?[...new Set([...(field[key]||[]),role])]:(field[key]||[]).filter(x=>x!==role);})));row.append(options);props.append(row);
                }
                props.append(conditionEditor(field,'visible_when',form.fields),conditionEditor(field,'required_when',form.fields));card.append(props);panel.append(card);
            });
            container.append(panel);
        });
    }
    async function callPreview(validate=false, prune=true) {
        if(dirty){feedback('Simpan draft terlebih dahulu agar preview memakai versi server yang sama.',true);return;}
        if(!previewForm)return;
        const sequence=++previewSequence;
        const body=new FormData(); body.append('form_key',previewForm);body.append('role',previewRole);body.append('validate',validate?'1':'0');
        for(const [key,value] of Object.entries(samples)) {if(Array.isArray(value))value.forEach(v=>body.append(`answers[${key}][]`,v));else if(value!==null&&value!=='')body.append(`answers[${key}]`,typeof value==='boolean'?(value?'1':'0'):value);}
        for(const [key,list] of Object.entries(files))list.forEach(file=>body.append(`uploads[${key}][]`,file));
        try {
            const response=await fetch(root.dataset.previewUrl,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'},body});
            const result=await response.json();if(sequence!==previewSequence)return;
            if(!result.fields){feedback(Object.values(result.errors||{message:[result.message||'Preview gagal.']}).flat().join(' '),true);return;}
            const allowed=new Set(result.fields.filter(f=>!f.read_only).map(f=>f.key));
            if(prune&&[...Object.keys(samples),...Object.keys(files)].some(key=>!allowed.has(key))){for(const key of Object.keys(samples))if(!allowed.has(key))delete samples[key];for(const key of Object.keys(files))if(!allowed.has(key))delete files[key];return callPreview(validate,false);}
            renderPreview(result);
        } catch {feedback('Preview tidak dapat dimuat. Periksa koneksi lalu coba lagi.',true);}
    }
    function preview() {
        const head=el('div','config-header');head.append(el('h2','mb-0','Preview & validasi server'));container.append(head);
        container.append(el('p','config-small','Coba tampilan tiga peran. Jawaban dan file uji tidak disimpan sebagai tiket. Preview selalu memakai draft terakhir yang tersimpan.'));
        const selectors=el('div','builder-grid');selectors.append(label('Template',select(config.forms.map(f=>[f.key,f.name]),previewForm,v=>{previewForm=v;samples={};files={};callPreview();},true,false)),label('Peran',select(roles.map(r=>[r,roleNames[r]]),previewRole,v=>{previewRole=v;samples={};files={};callPreview();},true,false)));container.append(selectors);
        container.append(el('div','preview-area','Memuat preview...'));callPreview();
    }
    function dependsOn(key) {
        const form=config.forms.find(item=>item.key===previewForm);
        const reads=node=>node ? (node.field===key || (node.rules||[]).some(reads)) : false;
        return (form?.fields||[]).some(field=>reads(field.visible_when)||reads(field.required_when));
    }
    const refreshIfNeeded=key=>{if(dependsOn(key))callPreview();};
    function renderPreview(result) {
        const focused=document.activeElement;
        const focusKey=focused?.dataset?.previewKey;
        const selection=typeof focused?.selectionStart==='number'?[focused.selectionStart,focused.selectionEnd]:null;

        const area=container.querySelector('.preview-area');if(!area)return;area.replaceChildren();
        const form=el('form','preview-grid');form.noValidate=true;
        result.fields.forEach(field=>{
            const wrap=el('div',field.width===6?'':'preview-field-full');let input;
            const value=samples[field.key]??field.value;
            if(['select','reference','multiselect','radio'].includes(field.type)) {
                if(field.type==='radio') {
                    input=el('div');field.options.forEach(option=>{const radio=el('input','form-check-input me-2');radio.type='radio';radio.name=`preview_${field.key}`;radio.value=option.value;radio.checked=String(value??'')===option.value;radio.disabled=field.read_only;radio.addEventListener('change',()=>{samples[field.key]=radio.value;refreshIfNeeded(field.key);});const choice=el('label','d-block mb-2');choice.append(radio,document.createTextNode(option.label));input.append(choice);});
                } else {
                    input=el('select','form-select');if(field.type==='multiselect')input.multiple=true;else{const option=el('option','','Pilih...');option.value='';input.append(option);}
                    field.options.forEach(option=>{const node=el('option','',option.label);node.value=option.value;node.selected=Array.isArray(value)?value.includes(option.value):String(value??'')===option.value;input.append(node);});
                    input.addEventListener('change',()=>{samples[field.key]=input.multiple?Array.from(input.selectedOptions).map(o=>o.value):input.value;refreshIfNeeded(field.key);});
                }
            } else if(field.type==='textarea') {input=el('textarea','form-control');input.rows=3;input.value=value??'';input.addEventListener('input',()=>samples[field.key]=input.value);input.addEventListener('change',()=>callPreview());}
            else {
                input=el('input',field.type==='checkbox'?'form-check-input':'form-control');input.type=({integer:'number',decimal:'number',datetime:'datetime-local',reference:'text'})[field.type]||field.type;
                if(field.type==='checkbox'){input.checked=!!value;input.addEventListener('change',()=>{samples[field.key]=input.checked;refreshIfNeeded(field.key);});}
                else if(field.type==='file'){input.multiple=(field.max_files??1)>1;input.accept=(field.allowed_mimes||[]).map(x=>'.'+x).join(',');input.addEventListener('change',()=>{files[field.key]=Array.from(input.files);refreshIfNeeded(field.key);});}
                else {input.value=value??'';if(field.type==='decimal')input.step='any';input.addEventListener('input',()=>samples[field.key]=input.value);input.addEventListener('change',()=>callPreview());}
            }
            input.dataset.previewKey=field.key;
            input.disabled=field.read_only;input.required=field.required;
            for(const [key,attr]of [['min','min'],['max','max'],['min_length','minLength'],['max_length','maxLength']])if(field[key]!==undefined)input[attr]=field[key];
            if(field.placeholder)input.placeholder=field.placeholder;
            wrap.append(label(field.label+(field.required?' *':''),input));if(field.section)wrap.prepend(el('div','config-small mb-1',field.section));if(field.help)wrap.append(el('p','builder-help',field.help));
            if(files[field.key]?.length)wrap.append(el('p','builder-help',files[field.key].map(file=>file.name).join(', ')));
            if(result.errors[`answers.${field.key}`])wrap.append(el('div','config-error',result.errors[`answers.${field.key}`]));form.append(wrap);
        });
        const actions=el('div','preview-field-full builder-actions');const submit=el('button','btn btn-primary btn-sm','Uji validasi server');submit.type='submit';actions.append(submit);if(result.valid!==null)actions.append(el('span',result.valid?'text-success':'text-danger',result.valid?'Valid — tidak ada data yang disimpan.':'Periksa field yang ditandai.'));form.append(actions);form.addEventListener('submit',event=>{event.preventDefault();callPreview(true);});area.append(form);
        const simulation=el('div','config-note mt-3 mb-0');simulation.append(el('strong','','Simulasi proses'));Object.entries(result.simulation).filter(([key])=>key!=='submission_enabled').forEach(([key,value])=>simulation.append(el('div','mt-1',`${key}: ${value}`)));area.append(simulation);
        if(focusKey){const target=area.querySelector(`[data-preview-key="${focusKey}"]`);if(target){target.focus({preventScroll:true});if(selection&&typeof target.selectionStart==='number')target.setSelectionRange(...selection);}}
    }
    function operations() {
        container.append(el('h2','','Workflow & penanganan IT'));
        container.append(el('p','config-small','Aturan dicocokkan dari atas ke bawah. Aturan pertama yang sesuai digunakan. Pengajuan tetap tertutup sampai SLA dan pilot.'));
        if(config.schema_version<2) {
            container.append(el('p','','Bundle ini masih memakai profil Fase 2. Aktifkan profil workflow pada draft untuk mulai menyusun aturan.'));
            container.append(button('Tambahkan profil workflow ke draft',()=>{
                config.schema_version=2;
                const matrix={};['high','medium','low'].forEach((a,i)=>['high','medium','low'].forEach((b,j)=>matrix[`${a}.${b}`]=`P${Math.min(4,1+i+j)}`));
                config.profiles={workflow:'it_v1',routing:[],approval:[],priority_matrix:matrix,reopen_days:7,sla:'phase4_pending',submission_enabled:false};changed();render();
            }));return;
        }
        const p=config.profiles;
        const grid=el('div','builder-grid');grid.append(label('Batas reopen (hari, 1–30)',textInput(p.reopen_days,v=>p.reopen_days=Number(v),'number')));
        for(const [key,value] of Object.entries(p.priority_matrix))grid.append(label(`Prioritas ${key}`,select(['P1','P2','P3','P4'].map(x=>[x,x]),value,v=>p.priority_matrix[key]=v)));
        container.append(grid);
        for(const section of ['routing','approval']) {
            const head=el('div','config-header mt-4');head.append(el('h2','',section==='routing'?'Routing berurutan':'Approval Service Request'),button('+ Tambah aturan',()=>{
                p[section].push(section==='routing'?{key:`route_${Date.now().toString(36)}`,team_id:initial.teams[0]?.id||null}:{key:`approval_${Date.now().toString(36)}`,record_type:'service_request',resolver:'portal',approver_id:null});changed();render();
            }));container.append(head);
            p[section].forEach((rule,index)=>{
                const card=el('div','builder-card');card.append(cardHead(`Aturan ${index+1}`,p[section],index));const row=el('div','builder-grid');
                row.append(label('Kode aturan',textInput(rule.key,v=>rule.key=v)));
                for(const [key,list,name] of [['catalog_key','catalog','Item katalog'],['service_key','services','Layanan'],['category_key','categories','Kategori']])row.append(label(name,select(choices(list,'Semua'),rule[key],v=>{if(v)rule[key]=v;else delete rule[key];})));
                row.append(label('Jenis catatan',select([['','Semua'],['incident','Incident'],['service_request','Service Request']],rule.record_type,v=>{if(v)rule.record_type=v;else delete rule.record_type;})));
                if(section==='routing')row.append(label('Tim tujuan',select([['','Pilih tim'],...initial.teams.map(t=>[t.id,t.name+(t.is_active?'':' (nonaktif)')])],rule.team_id,v=>rule.team_id=v?Number(v):null)));
                else {
                    row.append(label('Resolver approver',select([['portal','Atasan dari Portal'],['user','Pengguna tertentu']],rule.resolver,v=>{rule.resolver=v;rule.approver_id=null;render();})));
                    if(rule.resolver==='user')row.append(label('Approver',select([['','Pilih approver'],...initial.users.map(u=>[u.id,u.name])],rule.approver_id,v=>rule.approver_id=v?Number(v):null)));
                }
                card.append(row);container.append(card);
            });
        }
        container.append(el('p','config-small mt-3','Publikasi workflow membutuhkan owner dan tim fallback aktif dengan anggota. Pengaturan tim tersedia melalui menu Tim IT.'));
    }
    function slaEditor() {
        const intro=el('div','sla-intro');
        const introIcon=el('span','sla-intro-icon');introIcon.append(el('i','bi bi-stopwatch'));
        const introText=el('div');introText.append(el('div','sla-eyebrow','PENGATURAN LAYANAN'),el('h2','sla-title','SLA, kalender & eskalasi'),el('p','sla-intro-copy','Atur jam layanan, target penyelesaian, dan siapa yang menerima eskalasi.'));
        intro.append(introIcon,introText);container.append(intro);
        const notice=el('div','sla-notice');notice.append(el('i','bi bi-info-circle'),el('span','','Target perlu disepakati oleh service owner. Publikasi konfigurasi tidak otomatis membuka pengajuan IT.'));container.append(notice);
        if(config.schema_version<3) {
            const empty=el('div','sla-empty');empty.append(el('div','sla-empty-icon','＋'),el('h3','','Mulai pengaturan SLA'),el('p','','Tambahkan profil SLA ke draft untuk mengatur kalender, target, dan eskalasi.'));
            empty.append(button('Tambahkan profil SLA',()=>{
                if(config.schema_version<2){const matrix={};['high','medium','low'].forEach((a,i)=>['high','medium','low'].forEach((b,j)=>matrix[`${a}.${b}`]=`P${Math.min(4,1+i+j)}`));config.profiles={workflow:'it_v1',routing:[],approval:[],priority_matrix:matrix,reopen_days:7,sla:'phase4_pending',submission_enabled:false};}
                const weekly={};for(let d=1;d<=7;d++)weekly[d]=d<6?[{start:'08:00',end:'17:00'}]:[];
                config.schema_version=3;config.profiles.sla={engine:'sla_v1',calendars:[{key:'office',name:'Draft jam kerja — perlu ditinjau',timezone:'Asia/Jakarta',mode:'business',weekly,holidays:[],exceptions:[]}],policies:[],fallback:{incident:null,service_request:null},reopen_budget:'remaining'};changed();render();
            },'btn btn-primary btn-sm'));container.append(empty);return;
        }
        const sla=config.profiles.sla;
        const icon=(name)=>el('i',`bi ${name}`);
        const section=(number,title,description,action)=>{
            const box=el('section','sla-section');const head=el('div','sla-section-heading');const left=el('div','sla-section-heading-main');
            const num=el('span','sla-section-number',number);const copy=el('div');copy.append(el('h3','sla-section-title',title),el('p','sla-section-description',description));left.append(num,copy);head.append(left);if(action)head.append(action);box.append(head);container.append(box);return box;
        };
        const makeDisclosure=(item,expanded,title,subtitle,badge,array,index)=>{
            const card=el('details','sla-disclosure');card.open=expanded.has(item);
            const summary=el('summary','sla-disclosure-summary');const main=el('div','sla-disclosure-main');const name=el('div','sla-disclosure-name');name.append(el('strong','',title),el('span','sla-disclosure-badge',badge));main.append(name,el('span','sla-disclosure-subtitle',subtitle));summary.append(main,icon('bi-chevron-down'));card.append(summary);
            card.addEventListener('toggle',()=>{if(card.open)expanded.add(item);else expanded.delete(item);});
            const body=el('div','sla-disclosure-body');const manage=el('div','sla-manage');
            const move=(direction)=>{const target=index+direction;if(target<0||target>=array.length)return;[array[index],array[target]]=[array[target],array[index]];changed();render();};
            const up=button('Naikkan',()=>move(-1),'btn btn-light btn-sm');const down=button('Turunkan',()=>move(1),'btn btn-light btn-sm');if(index===0)up.disabled=true;if(index===array.length-1)down.disabled=true;
            manage.append(up,down,button('Hapus',()=>{array.splice(index,1);expanded.delete(item);changed();render();},'btn btn-outline-danger btn-sm'));body.append(manage);card.append(body);return {card,body};
        };
        const windowEditor=(windows)=>{
            const area=el('div','sla-window-list');
            if(!windows.length)area.append(el('span','sla-day-off','Tidak ada jam layanan'));
            windows.forEach((w,index)=>{
                const row=el('div','sla-window-row');const start=textInput(w.start,v=>w.start=v);start.setAttribute('aria-label','Jam mulai');start.placeholder='08:00';start.inputMode='numeric';
                const end=textInput(w.end,v=>w.end=v);end.setAttribute('aria-label','Jam selesai');end.placeholder='17:00';end.inputMode='numeric';
                const remove=button('Hapus',()=>{windows.splice(index,1);changed();render();},'btn btn-light btn-sm sla-window-remove');remove.setAttribute('aria-label','Hapus interval jam layanan');
                row.append(start,el('span','sla-window-separator','–'),end,remove);area.append(row);
            });
            if(windows.length<4)area.append(button('+ Tambah jam',()=>{windows.push({start:'08:00',end:'17:00'});changed();render();},'sla-inline-button'));return area;
        };
        const addCalendar=button('+ Tambah kalender',()=>{const c={key:`cal_${Date.now().toString(36)}`,name:'',timezone:'Asia/Jakarta',mode:'24x7',weekly:{},holidays:[],exceptions:[]};sla.calendars.push(c);expandedSlaCalendars.add(c);changed();render();},'btn btn-outline-secondary btn-sm');
        const calendars=section('01','Kalender layanan','Tentukan jam operasional, hari libur, dan pengecualian.',addCalendar);
        if(!sla.calendars.length)calendars.append(el('div','sla-empty-inline','Belum ada kalender. Tambahkan kalender untuk menentukan jam layanan.'));
        sla.calendars.forEach((c,index)=>{
            const {card,body}=makeDisclosure(c,expandedSlaCalendars,c.name||'Kalender baru',`${c.timezone} · ${c.key}`,c.mode==='24x7'?'24 jam × 7 hari':'Jam kerja',sla.calendars,index);
            const grid=el('div','builder-grid');grid.append(label('Nama kalender',textInput(c.name,v=>c.name=v)),label('Kode kalender',textInput(c.key,v=>c.key=v)),label('Zona waktu',textInput(c.timezone,v=>c.timezone=v)),label('Jam layanan',select([['business','Sesuai jam kerja'],['24x7','24 jam × 7 hari']],c.mode,v=>{c.mode=v;render();})));body.append(grid);
            if(c.mode==='business'){
                const schedule=el('div','sla-subsection');schedule.append(el('h4','sla-subsection-title','Jadwal mingguan'),el('p','sla-subsection-help','Masukkan jam mulai dan selesai dalam format 24 jam.'));
                const days=el('div','sla-week');['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'].forEach((name,i)=>{const day=String(i+1);if(!c.weekly[day])c.weekly[day]=[];const row=el('div','sla-day');row.append(el('strong','sla-day-name',name),windowEditor(c.weekly[day]));days.append(row);});schedule.append(days);body.append(schedule);
                const holidaySection=el('div','sla-subsection');holidaySection.append(el('h4','sla-subsection-title','Hari libur & pengecualian'),el('p','sla-subsection-help','Tanggal pengecualian menggantikan jadwal mingguan atau hari libur.'));
                const holidays=textInput(c.holidays.join('\n'),v=>c.holidays=v.split(/\s+/).map(x=>x.trim()).filter(Boolean),'textarea');holidays.rows=3;holidays.placeholder='2026-12-25';holidaySection.append(label('Hari libur (YYYY-MM-DD, satu tanggal per baris)',holidays));
                c.exceptions.forEach((e,i)=>{const row=el('div','sla-exception');row.append(label('Tanggal pengecualian',textInput(e.date,v=>e.date=v,'date')),windowEditor(e.windows),button('Hapus tanggal',()=>{c.exceptions.splice(i,1);changed();render();},'btn btn-light btn-sm'));holidaySection.append(row);});
                holidaySection.append(button('+ Tambah pengecualian',()=>{c.exceptions.push({date:'',windows:[]});changed();render();},'sla-inline-button'));body.append(holidaySection);
            }
            calendars.append(card);
        });
        const addPolicy=button('+ Tambah kebijakan',()=>{const targets={};for(const priority of ['P1','P2','P3','P4'])targets[priority]={response:null,restore:null};const p={key:`sla_${Date.now().toString(36)}`,name:'',record_type:'incident',calendar_key:sla.calendars[0]?.key||'',targets,pause:{response:[],restore:[],fulfil:[]},warning_percent:80,escalations:[]};sla.policies.push(p);expandedSlaPolicies.add(p);changed();render();},'btn btn-outline-secondary btn-sm');
        const policies=section('02','Kebijakan SLA','Aturan pertama yang cocok akan digunakan. Tempatkan aturan khusus di atas aturan umum.',addPolicy);
        if(!sla.policies.length)policies.append(el('div','sla-empty-inline','Belum ada kebijakan SLA. Tambahkan kebijakan untuk mengatur target dan eskalasi.'));
        sla.policies.forEach((p,index)=>{
            const kind=p.record_type==='incident'?'restore':'fulfil';const typeName=p.record_type==='incident'?'Incident':'Service Request';const cal=sla.calendars.find(c=>c.key===p.calendar_key);
            const {card,body}=makeDisclosure(p,expandedSlaPolicies,p.name||'Kebijakan baru',`Urutan ${index+1} · ${cal?.name||p.calendar_key||'Kalender belum dipilih'}`,typeName,sla.policies,index);
            const grid=el('div','builder-grid');grid.append(label('Nama kebijakan',textInput(p.name,v=>p.name=v)),label('Kode kebijakan',textInput(p.key,v=>p.key=v)),label('Jenis tiket',select([['incident','Incident'],['service_request','Service Request']],p.record_type,v=>{p.record_type=v;for(const t of Object.values(p.targets)){const old=t.restore??t.fulfil;delete t.restore;delete t.fulfil;t[v==='incident'?'restore':'fulfil']=old;}render();})),label('Kalender',select([['','Pilih kalender'],...sla.calendars.map(c=>[c.key,c.name||c.key])],p.calendar_key,v=>p.calendar_key=v)));
            for(const [key,list,name] of [['catalog_key','catalog','Item katalog'],['service_key','services','Layanan'],['category_key','categories','Kategori']])grid.append(label(name,select(choices(list,'Semua / fallback'),p[key],v=>{if(v)p[key]=v;else delete p[key];})));
            grid.append(label('Ambang peringatan (%)',textInput(p.warning_percent,v=>p.warning_percent=Number(v),'number')));body.append(grid);
            const targetSection=el('div','sla-subsection');targetSection.append(el('h4','sla-subsection-title','Target berdasarkan prioritas'),el('p','sla-subsection-help','Waktu dihitung dalam menit layanan sesuai kalender yang dipilih.'));
            const targetTable=el('div','sla-target-table');const targetHeader=el('div','sla-target-header');targetHeader.append(el('span','','Prioritas'),el('span','','Respons pertama'),el('span','',kind==='restore'?'Pemulihan':'Pemenuhan'));targetTable.append(targetHeader);
            for(const priority of ['P1','P2','P3','P4']){const row=el('div','sla-target-row');row.append(el('span',`sla-priority sla-priority-${priority.toLowerCase()}`,priority));for(const clock of ['response',kind]){const input=textInput(p.targets[priority][clock],v=>p.targets[priority][clock]=v===''?null:Number(v),'number');input.min='1';input.max='43200';input.setAttribute('aria-label',`${priority} ${clock==='response'?'respons pertama':kind==='restore'?'pemulihan':'pemenuhan'} dalam menit`);const inputWrap=el('div','sla-unit-input');inputWrap.append(input,el('span','','menit'));row.append(inputWrap);}targetTable.append(row);}targetSection.append(targetTable);body.append(targetSection);
            const pauseSection=el('div','sla-subsection');pauseSection.append(el('h4','sla-subsection-title','Jeda perhitungan SLA'),el('p','sla-subsection-help','Pilih kondisi yang boleh menjeda setiap perhitungan. Kondisi yang tidak dipilih tetap menghitung waktu.'));
            const pauseNames={waiting_customer:'Menunggu pemohon',waiting_vendor:'Menunggu vendor',waiting_internal:'Pekerjaan internal',waiting_parts:'Menunggu barang',scheduled_change:'Perubahan terjadwal',awaiting_approval:'Menunggu persetujuan',needs_revision:'Revisi pemohon'};
            for(const clock of ['response',kind]){const group=el('fieldset','sla-pause-group');group.append(el('legend','sla-pause-title',clock==='response'?'Respons pertama':kind==='restore'?'Pemulihan':'Pemenuhan'));const choicesBox=el('div','sla-check-grid');for(const [key,name]of Object.entries(pauseNames))choicesBox.append(check(name,p.pause[clock].includes(key),v=>p.pause[clock]=v?[...new Set([...p.pause[clock],key])]:p.pause[clock].filter(x=>x!==key)));group.append(choicesBox);pauseSection.append(group);}body.append(pauseSection);
            const escalation=el('div','sla-subsection');escalation.append(el('h4','sla-subsection-title','Eskalasi & pengingat'),el('p','sla-subsection-help','Atur kapan pemberitahuan dikirim dan siapa yang perlu menindaklanjuti.'));
            if(!p.escalations.length)escalation.append(el('p','sla-empty-inline','Belum ada level eskalasi.'));
            p.escalations.forEach((level,i)=>{const box=el('div','sla-escalation');const top=el('div','sla-escalation-heading');top.append(el('strong','',`Level ${i+1}`),button('Hapus level',()=>{p.escalations.splice(i,1);changed();render();},'btn btn-light btn-sm'));box.append(top);
                const row=el('div','builder-grid');row.append(label('Kode level',textInput(level.key,v=>level.key=v)),label('Saat target tercapai (%)',textInput(level.at_percent,v=>level.at_percent=Number(v),'number')),label('Ulangi setiap (menit layanan)',textInput(level.repeat_minutes,v=>level.repeat_minutes=v?Number(v):null,'number')));
                const recipients=el('select','form-select sla-multi-select');recipients.multiple=true;recipients.disabled=!editable;for(const user of initial.users){const option=el('option','',user.name);option.value=user.id;option.selected=(level.user_ids||[]).map(Number).includes(Number(user.id));recipients.append(option);}recipients.addEventListener('change',()=>{level.user_ids=Array.from(recipients.selectedOptions,o=>Number(o.value));changed();});row.append(label('Penerima tambahan',recipients));box.append(row);
                const hint=el('p','sla-subsection-help mt-2','Kosongkan interval jika pemberitahuan cukup sekali. Gunakan Ctrl atau Command untuk memilih beberapa penerima tambahan. Mereka tetap harus memiliki akses tiket.');box.append(hint,el('span','sla-label','Penerima berdasarkan peran'));
                const rolesBox=el('div','sla-check-grid');for(const [key,name] of [['assignee','PIC'],['coordinators','Koordinator tim'],['service_owner','Service owner']])rolesBox.append(check(name,level.recipients.includes(key),v=>{level.recipients=v?[...new Set([...level.recipients,key])]:level.recipients.filter(x=>x!==key);}));box.append(rolesBox);escalation.append(box);});
            escalation.append(button('+ Tambah level eskalasi',()=>{p.escalations.push({key:`level_${Date.now().toString(36)}`,at_percent:100,repeat_minutes:null,recipients:['coordinators'],user_ids:[]});changed();render();},'sla-inline-button'));body.append(escalation);policies.append(card);
        });
        const defaults=section('03','Aturan default','Dipakai ketika tidak ada kebijakan khusus yang cocok.');const fallback=el('div','builder-grid');
        for(const [key,name]of [['incident','Fallback Incident'],['service_request','Fallback Service Request']])fallback.append(label(name,select([['','Belum ditetapkan'],...sla.policies.filter(p=>p.record_type===key).map(p=>[p.key,p.name||p.key])],sla.fallback[key],v=>sla.fallback[key]=v||null)));
        const reopen=label('Target saat tiket dibuka kembali',select([['remaining','Gunakan sisa waktu dari siklus sebelumnya'],['full','Mulai dengan target penuh pada siklus baru']],sla.reopen_budget,v=>sla.reopen_budget=v));reopen.className='builder-wide';fallback.append(reopen);defaults.append(fallback);
        const previewCard=section('04','Simulasi deadline','Uji deadline dari draft yang sudah disimpan tanpa membuat tiket.');
        let sampleCatalog=config.catalog[0]?.key||'', samplePriority='P2';const local=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,16);
        const start=el('input','form-control');start.type='datetime-local';start.value=local;
        const inputs=el('div','builder-grid');inputs.append(label('Katalog',select(config.catalog.map(c=>[c.key,c.name]),sampleCatalog,v=>sampleCatalog=v,true,false)),label('Prioritas',select(['P1','P2','P3','P4'].map(p=>[p,p]),samplePriority,v=>samplePriority=v,true,false)),label('Waktu mulai',start));
        const output=el('div','sla-preview-result');previewCard.append(inputs,button('Simulasikan deadline',async()=>{
            if(dirty||saving){output.replaceChildren(el('p','text-danger mb-0','Simpan draft terlebih dahulu agar simulasi memakai versi yang sama.'));return;}
            try{
                const response=await fetch(root.dataset.slaPreviewUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({catalog_key:sampleCatalog,priority:samplePriority,started_at:new Date(start.value).toISOString()})});
                const result=await response.json();if(!response.ok)throw new Error(Object.values(result.errors||{message:[result.message]}).flat().join(' '));
                const summary=el('div','sla-preview-summary');summary.append(el('strong','','Hasil simulasi'),el('span','',`Kebijakan ${result.policy} · ${result.calendar} · ${result.timezone}`));output.replaceChildren(summary);
                for(const c of result.clocks){const line=el('div','sla-preview-line');line.append(el('strong','',c.kind==='response'?'Respons pertama':c.kind==='restore'?'Pemulihan':'Pemenuhan'),el('span','',`${c.target_minutes} menit layanan · ${new Date(c.due_at).toLocaleString('id-ID',{timeZone:result.timezone})} (${result.timezone})`),el('small','',`Kondisi jeda: ${c.pause_codes.join(', ')||'tidak ada'}`));output.append(line);}
                output.append(el('small','sla-preview-footnote','Simulasi tidak menyimpan tiket atau menjalankan resolver dan eskalasi.'));
            }catch(error){output.replaceChildren(el('p','text-danger mb-0',error.message));}
        },'btn btn-outline-primary btn-sm sla-preview-button',true),output);
    }
    function render() {
        container.replaceChildren();document.querySelectorAll('[data-config-tab]').forEach(node=>{const active=node.dataset.configTab===tab;node.setAttribute('aria-selected',String(active));node.tabIndex=active?0:-1;});
        container.setAttribute('aria-labelledby',`config-tab-${tab}`);document.getElementById('config-tab-guide').textContent=tabGuide[tab];
        if(tab==='forms')formBuilder();else if(tab==='preview')preview();else if(tab==='operations')operations();else if(tab==='sla')slaEditor();else master(tab);
    }
    document.querySelectorAll('[data-config-tab]').forEach(node=>node.addEventListener('click',()=>{tab=node.dataset.configTab;render();}));
    document.querySelector('.config-tabs')?.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const tabs=Array.from(document.querySelectorAll('[data-config-tab]'));const index=tabs.findIndex(node=>node.dataset.configTab===tab);const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(index+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;tab=tabs[next].dataset.configTab;render();tabs[next].focus();});
    window.addEventListener('beforeunload',event=>{if(dirty||saving){event.preventDefault();event.returnValue='';}});
    document.getElementById('config-save')?.addEventListener('click',async()=>{
        if(saving)return;const reason=document.getElementById('config-save-reason').value.trim();if(reason.length<5){feedback('Isi alasan perubahan minimal 5 karakter.',true);return;}saving=true;
        try {
            const response=await fetch(root.dataset.saveUrl,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({configuration:config,lock_version:lock,reason})});
            const result=await response.json();if(!response.ok){feedback(Object.values(result.errors||{message:[result.message||'Gagal menyimpan.']}).flat().join(' '),true);return;}
            lock=result.lock_version;dirty=false;document.getElementById('config-checksum').textContent=result.checksum.slice(0,12);const activation=document.querySelector('#config-activate [name=lock_version]');if(activation)activation.value=lock;document.getElementById('config-save-status').textContent='Draft tersimpan di server.';feedback('Draft tersimpan. Anda dapat mencoba preview atau meninjau publikasi.');
        } catch {feedback('Draft belum tersimpan. Periksa koneksi lalu coba lagi.',true);}finally{saving=false;}
    });
    document.getElementById('config-activate')?.addEventListener('submit',event=>{if(dirty||saving){event.preventDefault();feedback('Simpan draft dan tunggu proses selesai sebelum publikasi.',true);}});
    render();
})();
