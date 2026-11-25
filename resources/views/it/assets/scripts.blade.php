<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-asset-save]').forEach(form => form.addEventListener('submit', event => {
        if (event.submitter) {event.submitter.disabled = true; event.submitter.textContent = 'Menyimpan…';}
    }));
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[list]').forEach(input => {
        const data = document.getElementById(input.getAttribute('list'));
        if (!data) return;
        const values = [...data.options].map(option => option.value);
        const box = document.createElement('div');
        box.className = 'asset-autocomplete';
        input.parentNode.insertBefore(box, input);
        box.append(input);
        const menu = document.createElement('div');
        menu.id = input.id + '-suggestions';
        menu.className = 'asset-suggestions';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        box.append(menu);
        input.removeAttribute('list');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', menu.id);
        input.setAttribute('aria-expanded', 'false');
        let active = -1;
        const normalized = value => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('id-ID');
        const close = () => {menu.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1;};
        const choose = button => {input.value = button.dataset.value; close(); input.focus(); close();};
        const render = () => {
            menu.replaceChildren();
            active = -1;
            const term = normalized(input.value);
            const matches = values.filter(value => normalized(value).includes(term)).slice(0, 8);
            const exact = values.some(value => normalized(value) === term);
            const options = matches.map(value => ({value, label:value}));
            if (term && !exact) options.push({value:input.value.trim().replace(/\s+/g, ' '), label:'Buat "' + input.value.trim() + '" · disimpan bersama aset'});
            options.forEach((option, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.id = menu.id + '-' + index;
                button.dataset.value = option.value;
                button.textContent = option.label;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.addEventListener('mousedown', event => event.preventDefault());
                button.addEventListener('click', () => choose(button));
                menu.append(button);
            });
            menu.hidden = options.length === 0;
            input.setAttribute('aria-expanded', String(!menu.hidden));
        };
        input.addEventListener('input', render);
        input.addEventListener('focus', render);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') return close();
            if (event.key === 'Tab') return close();
            const options = [...menu.children];
            if (menu.hidden || !options.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = active < 0 ? (event.key === 'ArrowDown' ? 0 : options.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
                input.setAttribute('aria-activedescendant', options[active].id);
                options[active].scrollIntoView({block:'nearest'});
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (active >= 0) choose(options[active]); else close();
            }
        });
        document.addEventListener('click', event => {if (!box.contains(event.target)) close();});
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-portal-picker]').forEach(container => {
        const selected = container.querySelector('[data-portal-employee]');
        const search = container.querySelector('[data-portal-search]');
        const results = container.querySelector('[data-portal-results]');
        const message = container.querySelector('[data-portal-message]');
        const more = container.querySelector('[data-portal-more]');
        let timer, controller, page = 0, lastTerm = '', generation = 0;
        const load = async (append = false) => {
            if (controller) controller.abort();
            controller = new AbortController();
            const current = ++generation;
            const term = search.value.trim();
            if (append && term !== lastTerm) append = false;
            const nextPage = append ? page + 1 : 1;
            if (!append) results.replaceChildren();
            more.hidden = true;
            message.textContent = 'Mencari karyawan aktif di Portal…';
            try {
                const url = new URL(container.dataset.portalUrl, window.location.origin);
                url.searchParams.set('search', term);
                url.searchParams.set('page', nextPage);
                const response = await fetch(url, {headers:{Accept:'application/json'}, signal:controller.signal});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Direktori karyawan Portal belum tersedia. Coba lagi.');
                if (current !== generation) return;
                page = data.page;
                lastTerm = term;
                data.employees.forEach(employee => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.textContent = employee.name + ' · ' + employee.email + (employee.department ? ' · ' + employee.department : '');
                    option.addEventListener('click', () => {
                        selected.value = employee.id;
                        search.value = employee.name + ' · ' + employee.email;
                        selected.dispatchEvent(new Event('change', {bubbles:true}));
                        results.hidden = true;
                        more.hidden = true;
                        message.textContent = employee.name + ' dipilih dari Portal.';
                    });
                    results.append(option);
                });
                results.hidden = results.children.length === 0;
                more.hidden = !data.has_more;
                message.textContent = results.children.length ? 'Pilih karyawan dari hasil Portal berikut.' : 'Tidak ada karyawan aktif yang cocok di Portal.';
            } catch (error) {
                if (error.name === 'AbortError' || current !== generation) return;
                results.replaceChildren();results.hidden = true;more.hidden = true;
                message.textContent = error.message || 'Direktori Portal tidak dapat dihubungi.';
            }
        };
        search.addEventListener('input', () => {
            selected.value = '';
            clearTimeout(timer);
            if (controller) controller.abort();
            generation++;
            results.hidden = true;
            more.hidden = true;
            if (search.value.trim().length < 2) {message.textContent = 'Ketik minimal 2 karakter untuk mencari karyawan Portal.';return;}
            timer = setTimeout(() => load(), 350);
        });
        search.addEventListener('keydown', event => {if (event.key === 'Enter') {event.preventDefault();clearTimeout(timer);load();}});
        container.querySelector('[data-portal-find]').addEventListener('click', () => {clearTimeout(timer);load();});
        container.querySelector('[data-portal-clear]').addEventListener('click', () => {
            clearTimeout(timer);if (controller) controller.abort();generation++;
            selected.value = '';search.value = '';results.hidden = true;more.hidden = true;
            message.textContent = 'Tanpa filter / tanpa pemegang. Ketik untuk mencari di Portal.';
            search.focus();
        });
        search.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' && !results.hidden) {event.preventDefault();results.querySelector('button')?.focus();}
            if (event.key === 'Escape') {results.hidden = true;more.hidden = true;}
        });
        results.addEventListener('keydown', event => {
            const buttons = [...results.querySelectorAll('button')], index = buttons.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {event.preventDefault();buttons[(index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length]?.focus();}
            if (event.key === 'Escape') {results.hidden = true;search.focus();}
        });
        document.addEventListener('click', event => {if (!container.contains(event.target)) {results.hidden = true;more.hidden = true;}});
        more.addEventListener('click', () => load(true));
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-specifications]').forEach(section => {
        const rows = section.querySelector('[data-spec-rows]'), empty = section.querySelector('[data-spec-empty]');
        let next = Math.max(-1, ...[...rows.querySelectorAll('input')].map(input => Number(input.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
        const update = () => {empty.hidden = rows.children.length > 0;section.querySelector('[data-spec-add]').disabled = rows.children.length >= 100;};
        section.querySelector('[data-spec-add]').addEventListener('click', () => {
            if (rows.children.length >= 100) return;
            const row = document.createElement('div'), index = next++;
            row.className = 'asset-spec-row';row.dataset.specRow = '';
            row.innerHTML = `<div><label for="spec-name-${index}" class="form-label small">Nama spesifikasi</label><input id="spec-name-${index}" name="specifications[${index}][name]" class="form-control" maxlength="100" placeholder="Contoh: RAM"></div><div><label for="spec-value-${index}" class="form-label small">Nilai / detail</label><input id="spec-value-${index}" name="specifications[${index}][value]" class="form-control" maxlength="1000" placeholder="Contoh: 16 GB DDR5"></div><button type="button" class="btn btn-outline-secondary" data-spec-remove aria-label="Hapus spesifikasi"><i class="bi bi-trash"></i></button>`;
            rows.append(row);update();row.querySelector('input').focus();
        });
        rows.addEventListener('click', event => {
            const button = event.target.closest('[data-spec-remove]');
            if (button) {button.closest('[data-spec-row]').remove();update();section.querySelector('[data-spec-add]').focus();}
        });
        update();
    });
});
</script>
