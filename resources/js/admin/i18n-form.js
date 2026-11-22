// Behaviour of <x-admin.i18n-form>: one language switch for the whole page, completeness and
// length counters read from the DOM, errors per language, unsaved-changes guard, stay-on-page save.
//
// Contract with the Blade components:
//   translatable control  id="{base}-{locale}" data-i18n="{locale}" data-key="{livewire key}"
//                         data-field="{id for copy / paste}" data-label data-max data-soft data-default
//   shared control        data-key="{livewire key}" data-label
//   repeater row          data-row data-key="{row key}" data-label
window.adminI18nForm = (cfg) => ({
    lang: cfg.source,
    source: cfg.source,
    langs: cfg.locales,
    t: cfg.t,
    dirty: false,
    saving: false,
    savedAt: null,
    tick: 0, // bumped on every input so the DOM-based counters re-evaluate
    server: {}, // validation errors of the last save: { key: message }
    leaveOpen: false,
    leaveTo: null,
    pasteOpen: false,
    pasteText: '',
    pasteMsg: '',
    copied: '',

    init() {
        // wire:model fills the controls after Alpine starts: count again once they have values
        this.$nextTick(() => this.tick++);
        setTimeout(() => this.tick++, 150);
        this._unload = (e) => {
            if (this.dirty && !this.saving) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this._unload);
        // a Livewire round trip (add / remove a row, AI fill, "choose product") changes the controls: count again
        this._unhook = window.Livewire?.hook('morphed', () => this.tick++);
    },
    destroy() {
        window.removeEventListener('beforeunload', this._unload);
        this._unhook?.();
    },
    // The server changed the form (rows, picture, filled texts): dispatch('form-changed') from the component.
    touch() {
        this.dirty = true;
        this.savedAt = null;
        this.$nextTick(() => this.tick++);
    },

    // ----- reading the DOM -----
    controls(l) {
        this.tick;
        return [...this.$root.querySelectorAll('[data-i18n="' + l + '"]')];
    },
    el(id) {
        this.tick;
        return document.getElementById(id);
    },
    eff(el) {
        if (!el) return '';
        return (el.value || '').trim() ? el.value : el.dataset.default || '';
    },
    chars(text) {
        return [...text].length;
    },
    over(el) {
        const max = Number(el?.dataset.max || 0);
        return max ? Math.max(0, this.chars(el.value || '') - max) : 0;
    },

    // ----- per field -----
    len(id) {
        return this.chars(this.eff(this.el(id)));
    },
    empty(id) {
        return !(this.el(id)?.value || '').trim();
    },
    err(id) {
        const el = this.el(id);
        if (!el) return '';
        const over = this.over(el);
        if (over) return this.t.too_long.replace(':n', over).replace(':max', el.dataset.max);
        return this.server[el.dataset.key] || '';
    },
    warn(id) {
        const el = this.el(id);
        const soft = Number(el?.dataset.soft || 0);
        return !!soft && this.chars(this.eff(el)) > soft && !this.over(el);
    },
    counterClass(id) {
        const el = this.el(id);
        if (el && this.over(el)) return 'text-rose-600 font-semibold';
        return this.warn(id) ? 'text-amber-700 font-medium' : 'text-stone-500';
    },
    original(base) {
        return this.eff(this.el(base + '-' + this.source));
    },
    // What the site shows for a field in the current language: its text, else the default / Uzbek text (for previews).
    shown(base) {
        const own = this.eff(this.el(base + '-' + this.lang));
        return own.trim() ? own : this.original(base);
    },
    changed(base, l) {
        return !this.empty(base + '-' + (l || this.lang));
    },
    rowErr(key) {
        return this.server[key] || '';
    },

    // ----- per language -----
    total() {
        return this.controls(this.source).length;
    },
    count(l) {
        return this.controls(l).filter((el) => this.eff(el).trim()).length;
    },
    allErrs() {
        this.tick;
        const out = [];
        const seen = new Set();
        for (const l of this.langs) {
            for (const el of this.controls(l)) {
                const over = this.over(el);
                if (over || this.server[el.dataset.key]) {
                    out.push({ lang: l, id: el.id, label: el.dataset.label || '', client: !!over });
                    seen.add(el.dataset.key);
                }
            }
        }
        for (const key of Object.keys(this.server)) {
            if (seen.has(key)) continue;
            const el = [...this.$root.querySelectorAll('[data-key]')].find((n) => n.dataset.key === key);
            out.push({ lang: el?.hasAttribute('data-row') ? this.source : null, id: el?.id || '', label: el?.dataset.label || this.server[key], client: false });
        }
        return out;
    },
    errs(l) {
        return this.allErrs().filter((e) => e.lang === l);
    },
    warns(l) {
        return this.controls(l).filter((el) => this.warn(el.id));
    },
    state(l) {
        if (this.errs(l).length) return 'error';
        const n = this.count(l);
        return n && n === this.total() ? 'complete' : n ? 'partial' : 'empty';
    },
    tabLabel(l, name) {
        const e = this.errs(l).length;
        return name + ': ' + this.count(l) + ' / ' + this.total() + ' ' + this.t.filled + (e ? ', ' + this.t.errors.replace(':n', e) : '');
    },
    errLabel(e) {
        return (e.lang ? e.lang.toUpperCase() + ' · ' : '') + e.label;
    },
    focusTab(l) {
        this.lang = l;
        this.$nextTick(() => document.getElementById('admin-tab-' + l)?.focus());
    },

    // ----- changes -----
    onInput(e) {
        if (e.target.closest?.('[data-no-dirty]')) return;
        this.dirty = true;
        this.savedAt = null;
        this.tick++;
        const key = e.target.dataset?.key;
        if (!key) return;
        const stale = Object.keys(this.server).filter((k) => k === key || key.startsWith(k + '.'));
        if (stale.length) {
            const next = { ...this.server };
            stale.forEach((k) => delete next[k]);
            this.server = next;
        }
    },
    goTo(e) {
        if (e.lang) this.lang = e.lang;
        this.$nextTick(() => {
            const el = e.id ? document.getElementById(e.id) : null;
            if (!el) return;
            // every folded block around the field opens itself (collapsed section, page row, inner tab)
            for (let box = el.closest('[data-collapsible]'); box; box = box.parentElement?.closest('[data-collapsible]')) {
                box.dispatchEvent(new CustomEvent('open-section'));
            }
            // x-show reveals an element in a timeout of its own, so wait until the field is really visible
            setTimeout(() => {
                const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                el.scrollIntoView({ block: 'center', behavior: calm ? 'auto' : 'smooth' });
                (el.matches('input, textarea, select, button, [tabindex]') ? el : el.querySelector('input, textarea, select'))?.focus({ preventScroll: true });
            }, 80);
        });
    },
    // Stay in the current language when it has an error, otherwise open the first language that has one.
    jump() {
        const all = this.allErrs();
        if (!all.length) return;
        this.goTo(all.find((e) => e.lang === this.lang) || all.find((e) => e.lang) || all[0]);
    },
    async submit() {
        if (this.saving) return;
        // over the limit: no need to ask the server, show the field straight away
        if (this.allErrs().some((e) => e.client)) return this.jump();
        this.saving = true;
        let failed = false;
        try {
            await this.$wire.call(cfg.submit);
        } catch (e) {
            failed = true;
        }
        await this.$nextTick();
        const messages = this.$wire.$errors.messages() || {};
        this.server = Object.fromEntries(Object.entries(messages).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
        this.saving = false;
        this.tick++;
        if (failed) return;
        if (Object.keys(this.server).length) return this.jump();
        this.dirty = false;
        this.savedAt = new Date().toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' }); // 24-hour HH:MM
    },

    // ----- leaving with unsaved changes -----
    leave(url) {
        if (!this.dirty) return (window.location.href = url);
        this.leaveTo = url;
        this.leaveOpen = true;
    },
    confirmLeave() {
        this.dirty = false;
        window.location.href = this.leaveTo;
    },
    // any ordinary link on the page (menu, back link) asks first while the form is dirty
    guardLink(e) {
        if (!this.dirty || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
        const a = e.target.closest?.('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.getAttribute('href').startsWith('#')) return;
        e.preventDefault();
        this.leave(a.href);
    },

    // ----- copy / paste for a translator -----
    flash(flag) {
        this.copied = flag;
        setTimeout(() => {
            if (this.copied === flag) this.copied = '';
        }, 1800);
    },
    copy(text, flag) {
        try {
            navigator.clipboard.writeText(text);
        } catch (e) {}
        this.flash(flag);
    },
    copyAll() {
        const text = this.controls(this.source)
            .filter((el) => this.eff(el).trim())
            .map((el) => '### ' + el.dataset.field + '\n' + this.eff(el))
            .join('\n\n');
        this.copy(text, 'all');
    },
    applyPaste() {
        const targets = this.controls(this.lang);
        const blocks = {};
        let current = null;
        this.pasteText.split(/\r?\n/).forEach((line) => {
            const m = line.match(/^#{2,3}\s*([\w.\-]+)\s*$/);
            if (m && targets.some((el) => el.dataset.field === m[1])) {
                current = m[1];
                blocks[current] = [];
            } else if (current) {
                blocks[current].push(line);
            }
        });
        let n = 0;
        targets.forEach((el) => {
            if (!blocks[el.dataset.field]) return;
            el.value = blocks[el.dataset.field].join('\n').trim();
            el.dispatchEvent(new Event('input', { bubbles: true })); // wire:model and the counters pick it up
            n++;
        });
        this.pasteMsg = n ? this.t.pasted.replace(':n', n) : this.t.paste_none;
        if (n) {
            this.pasteText = '';
            setTimeout(() => (this.pasteOpen = false), 1400);
        }
    },
});
