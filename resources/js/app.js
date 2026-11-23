// Visual label editor (drag to move, corner handle to resize). All geometry is in mm.
window.labelEditor = (initial, sampleDm, sample) => ({
    name: initial.name,
    width: initial.width_mm,
    height: initial.height_mm,
    columns: initial.columns || 1,
    gap: initial.gap_mm || 0,
    labelsPerPdf: initial.labels_per_pdf || 2000,
    elements: JSON.parse(JSON.stringify(initial.elements)).map((el, i) => ({ ...el, id: i + 1 })),
    selectedId: null,
    nextId: initial.elements.length + 1,
    sampleDm,
    sample,
    drag: null,
    saving: false,
    snap: 0.5,
    // Public studio only: elements are bound to spreadsheet columns through a dropdown.
    headers: [],
    rows: [],
    useSources: initial.useSources || false,
    hasHeader: true,
    // Fixed data fields instead of spreadsheet columns (Asl Belgisi templates): [{value, label}]
    fields: initial.fields || [],
    // The Asl Belgisi marking DataMatrix is available as an element kind (admin templates).
    allowMarking: initial.allowMarking || false,
    // Company logo element (admin templates): the editor shows a neutral sample logo.
    allowLogo: initial.allowLogo || false,
    sampleLogo: initial.sampleLogo || '',
    // The small "markor.uz" mark in the bottom-right corner (same numbers as LabelTemplate::WATERMARK_*).
    watermark: initial.watermark ?? false,
    get watermarkFits() {
        return Number(this.width) >= 20 && Number(this.height) >= 10;
    },
    get watermarkShown() {
        return !!this.watermark && this.watermarkFits;
    },
    get watermarkBox() {
        const w = 7.4, h = 1.5, m = 0.5;
        return { x: Number(this.width) - m - w, y: Number(this.height) - m - h, w, h };
    },
    watermarkStyle() {
        const b = this.watermarkBox;
        return `left:${this.px(b.x)};top:${this.px(b.y)};width:${this.px(b.w)};height:${this.px(b.h)};`
            + `font-size:${4 * 0.3528 * this.scale}px;line-height:${this.px(1.4)};`;
    },
    // Barcodes under the mark would not scan: they are listed in a warning.
    get watermarkOverlap() {
        if (!this.watermarkShown) return false;
        const b = this.watermarkBox;
        return this.elements.some((el) => (el.type === 'barcode' || el.type === 'datamatrix')
            && el.x < b.x + b.w && b.x < el.x + el.w && el.y < b.y + b.h && b.y < el.y + el.h);
    },
    // Logo / own image: shown fitted in the box; black-and-white like on the printed label.
    imageSrc(el) {
        if (el.type === 'mark') return `/images/marks/${el.mark || 'aslbelgi'}.png`;
        return el.type === 'logo' ? this.sampleLogo : (el.src || '');
    },
    imageFilter(el) {
        if (el.type !== 'image') return '';
        return el.bw === false ? 'filter:grayscale(1);' : 'filter:grayscale(1) contrast(100);';
    },
    get sourceOptions() {
        return this.fields.length ? this.fields : this.headers.map((h) => ({ value: h, label: h }));
    },
    sourceLabel(key) {
        return (this.sourceOptions.find((o) => o.value === key) || {}).label || key;
    },

    // Labels across the roll, as shown in the preview.
    get cols() {
        return Math.min(10, Math.max(1, parseInt(this.columns) || 1));
    },
    get gapMm() {
        return Math.min(30, Math.max(0, Number(this.gap) || 0));
    },
    get scale() {
        const w = Math.max(10, Number(this.width) || 10);
        const h = Math.max(10, Number(this.height) || 10);
        const rowW = this.cols * w + (this.cols - 1) * this.gapMm;
        return Math.max(1.5, Math.min(10, (this.cols > 1 ? 640 : 560) / rowW, 420 / h));
    },
    // The other labels of the row are read-only copies; with a spreadsheet they show the next rows.
    ghostSample(n) {
        return this.rows[n] || this.sample;
    },
    get selected() {
        return this.elements.find((el) => el.id === this.selectedId) || null;
    },

    px(mm) {
        return (Number(mm) || 0) * this.scale + 'px';
    },
    boxStyle(el) {
        return `left:${this.px(el.x)};top:${this.px(el.y)};width:${this.px(el.w)};height:${this.px(el.h)};`;
    },
    // Content sits inside the box, turned by el.rotation (0/90/180/270, clockwise).
    innerStyle(el) {
        const rotation = Number(el.rotation) || 0;
        const side = rotation % 180 !== 0;
        const w = side ? el.h : el.w;
        const h = side ? el.w : el.h;
        return `position:absolute;left:${this.px((el.w - w) / 2)};top:${this.px((el.h - h) / 2)};`
            + `width:${this.px(w)};height:${this.px(h)};transform:rotate(${rotation}deg);`;
    },
    rotate(el) {
        const cx = el.x + el.w / 2, cy = el.y + el.h / 2;
        [el.w, el.h] = [el.h, el.w];
        el.x = this.round(cx - el.w / 2);
        el.y = this.round(cy - el.h / 2);
        el.rotation = ((Number(el.rotation) || 0) + 90) % 360;
        this.clamp(el);
    },
    // How the selected element is shown: plain text or one of the barcode kinds.
    // The data binding (column or typed value), position and rotation are kept.
    kindOf(el) {
        if (el.type === 'datamatrix') return 'marking';
        if (el.type === 'logo' || el.type === 'image' || el.type === 'mark') return el.type;
        return el.type === 'barcode' ? el.symbology : el.type;
    },
    setKind(el, kind) {
        if (kind === this.kindOf(el)) return;
        if (kind === 'marking') {
            el.type = 'datamatrix';
            ['symbology', 'source', 'text', 'caption', 'font_size', 'bold', 'align'].forEach((k) => delete el[k]);
            el.w = el.h = Math.max(14, Math.min(el.w, el.h));
            this.clamp(el);
            return;
        }
        if (el.type === 'datamatrix') {
            el.source = this.sourceOptions[0]?.value || '';
            el.text = '';
        }
        if (kind === 'text') {
            el.type = 'text';
            delete el.symbology;
            el.font_size = el.font_size || 7;
            el.bold = !!el.bold;
            el.align = el.align || 'left';
            el.caption = !!el.caption;
        } else {
            const wasText = el.type === 'text';
            el.type = 'barcode';
            el.symbology = kind;
            el.caption = false;
            if (['ean13', 'code128'].includes(kind)) { if (el.hrt === undefined) el.hrt = true; } else delete el.hrt;
            if (!el.source && !(el.text || '').trim()) el.text = kind === 'ean13' ? '4780000000007' : '123456';
            if (['datamatrix', 'qr', 'asl'].includes(kind)) {
                el.w = el.h = Math.max(12, Math.min(el.w, el.h));
            } else if (wasText || el.w === el.h) {
                // linear codes need room: at least 8 mm tall and wider than tall
                const side = (Number(el.rotation) || 0) % 180 !== 0;
                const long = Math.max(30, side ? el.h : el.w), short = Math.max(8, Math.min(side ? el.w : el.h, 15));
                [el.w, el.h] = side ? [short, long] : [long, short];
            }
        }
        this.clamp(el);
    },
    // Turn the whole label 90° clockwise: width and height swap and every element moves and turns with it.
    rotateLabel() {
        const W = Number(this.width), H = Number(this.height);
        this.elements.forEach((el) => {
            const x = H - (el.y + el.h);
            const y = el.x;
            [el.w, el.h] = [el.h, el.w];
            el.x = Math.round(x * 100) / 100;
            el.y = Math.round(y * 100) / 100;
            el.rotation = ((Number(el.rotation) || 0) + 90) % 360;
        });
        this.width = H;
        this.height = W;
        this.clampAll();
    },
    // Ids of elements whose boxes overlap another element (more than a hairline).
    get overlapping() {
        const ids = new Set();
        const eps = 0.2;
        this.elements.forEach((a, i) => {
            this.elements.slice(i + 1).forEach((b) => {
                if (a.x + a.w - eps > b.x && b.x + b.w - eps > a.x && a.y + a.h - eps > b.y && b.y + b.h - eps > a.y) {
                    ids.add(a.id);
                    ids.add(b.id);
                }
            });
        });
        return ids;
    },
    textStyle(el) {
        // 1pt = 0.3528 mm
        return `font-size:${(Number(el.font_size) || 7) * 0.3528 * this.scale}px;line-height:1.2;`
            + `font-weight:${el.bold ? 700 : 400};text-align:${el.align || 'left'};`;
    },
    // Generated values: the print date/time in a chosen format, and a running number per label.
    formatDate(format, offsetDays = 0) {
        const d = new Date(Date.now() + (Number(offsetDays) || 0) * 86400000);
        const two = (n) => String(n).padStart(2, '0');
        const parts = {
            YYYY: d.getFullYear(), YY: two(d.getFullYear() % 100), MM: two(d.getMonth() + 1), DD: two(d.getDate()),
            HH: two(d.getHours()), mm: two(d.getMinutes()), ss: two(d.getSeconds()),
        };
        return String(format || 'DD.MM.YYYY').replace(/YYYY|YY|MM|DD|HH|mm|ss/g, (token) => parts[token]);
    },
    serialValue(el, index = 0) {
        const number = (Number(el.start) || 0) + index * (Number(el.step) || 0);
        const digits = String(Math.abs(number)).padStart(Math.min(12, Math.max(0, Number(el.pad) || 0)), '0');
        return `${el.prefix || ''}${number < 0 ? '-' : ''}${digits}${el.suffix || ''}`;
    },
    isGenerated(el) {
        return el.source === '@date' || el.source === '@serial';
    },
    // Called when the data source dropdown changes: give generated sources sensible defaults.
    sourceChanged(el) {
        if (el.source === '@date' && !el.fmt) {
            el.fmt = 'DD.MM.YYYY';
            el.offset_days = 0;
        }
        if (el.source === '@serial' && el.start === undefined) {
            Object.assign(el, { prefix: '', suffix: '', start: 1, step: 1, pad: 4 });
        }
        if (this.isGenerated(el)) el.caption = false;
    },
    preview(el, sample = this.sample, index = 0) {
        if (el.source === '@date') return this.formatDate(el.fmt, el.offset_days);
        if (el.source === '@serial') return this.serialValue(el, index);
        if (el.source) {
            const value = sample[el.source] ?? `‹${this.sourceLabel(el.source)}›`;
            return el.type === 'text' && el.caption ? `${this.sourceLabel(el.source)}: ${value}` : value;
        }
        return (el.text || '').replace(/\{([^{}]+)\}/g, (m, key) => (key in sample ? sample[key] : m));
    },
    isSquare(el) {
        return el.type === 'datamatrix' || (el.type === 'barcode' && ['datamatrix', 'qr', 'asl'].includes(el.symbology));
    },
    // Live barcode preview for the public studio (generic barcodes bound to a column).
    barcodeUrl(el, sample = this.sample, index = 0) {
        const data = String(el.source && !this.isGenerated(el) ? sample[el.source] ?? '' : this.preview(el, sample, index)).trim();
        return data ? `/label-design/barcode.png?type=${el.symbology}&data=${encodeURIComponent(data)}${el.hrt ? '&hrt=1' : ''}` : '';
    },

    round(v) {
        return Math.round(v / this.snap) * this.snap;
    },
    clamp(el) {
        const W = Number(this.width), H = Number(this.height);
        el.w = Math.min(Math.max(Number(el.w) || 1, 1), W);
        el.h = Math.min(Math.max(Number(el.h) || 1, 1), H);
        if (this.isSquare(el)) {
            el.w = el.h = Math.min(el.w, el.h, W, H);
        }
        el.x = Math.min(Math.max(Number(el.x) || 0, 0), W - el.w);
        el.y = Math.min(Math.max(Number(el.y) || 0, 0), H - el.h);
    },
    clampAll() {
        this.elements.forEach((el) => this.clamp(el));
    },

    start(event, el, mode) {
        this.selectedId = el.id;
        this.drag = { id: el.id, mode, sx: event.clientX, sy: event.clientY, x: el.x, y: el.y, w: el.w, h: el.h };
        event.preventDefault();
    },
    move(event) {
        if (!this.drag) return;
        const el = this.elements.find((e) => e.id === this.drag.id);
        if (!el) return;
        const dx = (event.clientX - this.drag.sx) / this.scale;
        const dy = (event.clientY - this.drag.sy) / this.scale;

        if (this.drag.mode === 'move') {
            el.x = this.round(this.drag.x + dx);
            el.y = this.round(this.drag.y + dy);
        } else if (this.isSquare(el)) {
            el.w = el.h = this.round(Math.max(5, this.drag.w + Math.max(dx, dy)));
            el.w = el.h = Math.min(el.w, this.width - el.x, this.height - el.y);
        } else {
            el.w = Math.min(this.round(Math.max(2, this.drag.w + dx)), this.width - el.x);
            el.h = Math.min(this.round(Math.max(2, this.drag.h + dy)), this.height - el.y);
        }
        this.clamp(el);
    },
    stop() {
        this.drag = null;
    },
    nudge(dx, dy) {
        if (!this.selected) return;
        this.selected.x += dx * this.snap;
        this.selected.y += dy * this.snap;
        this.clamp(this.selected);
    },

    add(type, text = '', symbology = 'qr', source = '') {
        let el;
        if (type === 'datamatrix') {
            el = { type, x: 2, y: 2, w: 20, h: 20 };
        } else if (type === 'mark') {
            // a standard sign (Asl Belgisi…): square, 8 mm by default (6 mm is the smallest that prints clearly at 203 dpi)
            const size = Math.max(6, Math.min(8, this.width - 4, this.height - 4));
            el = { type, mark: text || 'aslbelgi', x: 2, y: 2, w: size, h: size };
        } else if (type === 'logo') {
            el = { type, x: 2, y: 2, w: Math.min(16, this.width - 4), h: Math.min(8, this.height - 4) };
        } else if (type === 'barcode') {
            const square = ['datamatrix', 'qr', 'asl'].includes(symbology);
            el = { type, symbology, text, x: 2, y: 2, w: square ? 18 : 34, h: square ? 18 : 10 };
            if (['ean13', 'code128'].includes(symbology)) { el.hrt = true; el.h = 14; } // digits under the bars by default
        } else {
            el = { type: 'text', text, x: 2, y: 2, w: Math.min(30, this.width - 2), h: 4, font_size: 7, bold: false, align: 'left' };
        }
        if (this.useSources && type !== 'logo' && type !== 'mark') {
            el.source = source;
            if (type === 'text') el.caption = false;
            this.sourceChanged(el);
        }
        el.id = this.nextId++;
        this.clamp(el);
        this.elements.push(el);
        this.selectedId = el.id;
    },
    remove() {
        this.elements = this.elements.filter((el) => el.id !== this.selectedId);
        this.selectedId = null;
    },

    async save() {
        this.saving = true;
        this.clampAll();
        try {
            await this.$wire.save({
                name: this.name,
                width_mm: Number(this.width),
                height_mm: Number(this.height),
                columns: Number(this.columns) || 1,
                gap_mm: Number(this.gap) || 0,
                labels_per_pdf: Number(this.labelsPerPdf) || 2000,
                watermark: !!this.watermark,
                elements: this.elements.map(({ id, ...el }) => el),
            });
        } finally {
            this.saving = false;
        }
    },
});

// "Generate & download labels PDF" dialog on the import page.
// The server renders ~24 ms per label, so the bar runs on that estimate and
// stops at 95% until the file actually arrives.
// While a PDF is generated the dialog shows one of our products, for at least this long.
// Checks a chosen file before it is sent: size and extension. Returns an error text or ''.
window.checkUpload = (file, rule) => {
    if (!file) return '';
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!rule.extensions.includes(ext)) return rule.typeError;
    if (file.size > rule.maxKb * 1024) return rule.sizeError;
    if (file.size === 0) return rule.emptyError;
    return '';
};

const PROMO_MIN_MS = 5000; // modals with a product offer stay at least 5 s
const promoWait = (started, offersCount = 1) =>
    new Promise((resolve) => setTimeout(resolve, offersCount ? Math.max(0, PROMO_MIN_MS - (Date.now() - started)) : 0));
window.promoWait = promoWait;

window.labelDownload = (defaultTemplateId, offersCount = 0) => ({
    offersCount,
    promoIndex: -1,
    batch: null,
    templateId: defaultTemplateId,
    state: 'choose',
    progress: 0,
    remaining: 0,
    timer: null,

    parts: [],
    mode: 'pdf', // which button opened the dialog: 'print' or 'pdf' (both actions stay available)
    open(batch, mode = 'pdf') {
        this.batch = batch;
        this.mode = mode;
        this.state = 'choose';
        this.progress = 0;
        this.loadParts();
    },
    // a big batch is split into PDF parts (labels per PDF is set on the design)
    async loadParts() {
        this.parts = [];
        if (!this.batch || !this.templateId) return;
        try {
            const response = await fetch(`${this.batch.partsUrl}?template=${this.templateId}`, { headers: { Accept: 'application/json' } });
            this.parts = response.ok ? await response.json() : [];
        } catch (e) {
            this.parts = [];
        }
    },
    close() {
        if (this.state === 'working') return;
        this.batch = null;
    },
    // same template and parts as the PDF; opens the browser print page (it starts the print dialog itself)
    printPart(part = 1) {
        if (!this.batch?.printUrl || !this.templateId) return;
        window.open(`${this.batch.printUrl}?template=${this.templateId}&part=${part}`, '_blank');
        this.$wire.$refresh();
    },
    async generate(part = 1) {
        const info = this.parts.find((p) => p.part === part);
        const labels = info ? info.labels : this.batch.count;
        const estimate = Math.max(this.offersCount ? PROMO_MIN_MS / 1000 : 1, info && info.ready ? 1 : labels * 0.025 + 0.5);
        const started = Date.now();
        this.promoIndex = this.offersCount ? Math.floor(Math.random() * this.offersCount) : -1;
        this.state = 'working';
        this.progress = 0;
        this.remaining = Math.ceil(estimate);
        this.timer = setInterval(() => {
            const elapsed = (Date.now() - started) / 1000;
            this.progress = Math.min(95, (elapsed / estimate) * 100);
            this.remaining = Math.max(1, Math.ceil(estimate - elapsed));
        }, 250);

        try {
            const response = await fetch(`${this.batch.url}?template=${this.templateId}&part=${part}`, { credentials: 'same-origin' });
            if (!response.ok || !(response.headers.get('content-type') || '').includes('application/pdf')) throw new Error(response.status);
            const blob = await response.blob();
            await promoWait(started, this.offersCount);
            const url = URL.createObjectURL(blob);
            const suffix = this.parts.length > 1 ? `_part${part}of${this.parts.length}` : '';
            const link = Object.assign(document.createElement('a'), { href: url, download: `${this.batch.name}${suffix}.pdf` });
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            this.progress = 100;
            if (info) info.ready = true;
            this.state = this.parts.length > 1 ? 'choose' : 'done';
            this.$wire.$refresh();
        } catch (e) {
            this.state = 'error';
        } finally {
            clearInterval(this.timer);
        }
    },
});

// Public label studio on the main page: spreadsheet -> design -> PDF. Builds on labelEditor.
window.labelStudio = (config) => {
    const base = window.labelEditor(
        {
            name: 'label',
            width_mm: 60,
            height_mm: 40,
            elements: [
                { type: 'barcode', symbology: 'qr', text: 'https://example.uz', x: 3, y: 3, w: 22, h: 22 },
                { type: 'text', text: config.sampleTitle, x: 28, y: 4, w: 29, h: 10, font_size: 9, bold: true, align: 'left' },
                { type: 'text', text: config.sampleText, x: 28, y: 16, w: 29, h: 8, font_size: 6.5, bold: false, align: 'left' },
            ],
        },
        null,
        {}
    );

    const extra = {
        headers: [],
        rows: [],
        total: 0,
        fileName: '',
        copies: 1,
        maxLabels: config.maxLabels,
        useSources: true,
        hasHeader: true,
        uploading: false,
        error: '',
        state: 'idle', // idle | working | done
        progress: 0,
        offersCount: config.offersCount || 0,
        promoIndex: -1,

        get labelCount() {
            return this.rows.length || Number(this.copies) || 1;
        },
        // Asl Belgisi codes found in the file: { column, header_is_code, count, limit } or null
        marking: null,
        get usesMarking() {
            return this.elements.some((el) => el.symbology === 'asl');
        },
        get overMarkingLimit() {
            return this.usesMarking && this.labelCount > config.markingLimit;
        },
        // labels that go into the PDF: real Asl Belgisi marks only for the first N codes (free trial)
        get pdfCount() {
            return this.usesMarking ? Math.min(this.labelCount, config.markingLimit) : this.labelCount;
        },
        markingModal: false,

        // "markor.uz" mark: on by default in the public studio (the visitor can turn it off)
        watermark: true,

        // The visitor's own image (logo): PNG / JPG, downscaled in the browser to a data URI that
        // lives in the design only (never stored on the server). Too big for localStorage → memory only.
        imageError: '',
        imageNotSaved: false,
        imageTarget: null, // element whose image is being replaced
        pickImage(el = null) {
            this.imageTarget = el;
            this.imageError = '';
            this.$refs.imageInput.value = '';
            this.$refs.imageInput.click();
        },
        async imageChosen(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.imageError = window.checkUpload(file, config.imageUpload);
            if (this.imageError) return;
            try {
                const image = await this.readImage(file);
                if (this.imageTarget) {
                    this.imageTarget.src = image.src;
                    this.imageTarget.h = this.round(Math.max(2, this.imageTarget.w * image.h / image.w));
                    this.clamp(this.imageTarget);
                } else {
                    const w = Math.max(4, Math.min(20, Number(this.width) - 4));
                    const el = { id: this.nextId++, type: 'image', src: image.src, bw: true, x: 2, y: 2, w, h: this.round(Math.max(2, Math.min(w * image.h / image.w, Number(this.height) - 4))) };
                    this.clamp(el);
                    this.elements.push(el);
                    this.selectedId = el.id;
                }
            } catch (e) {
                this.imageError = config.imageUpload.typeError;
            }
            this.imageTarget = null;
        },
        readImage(file) {
            return new Promise((resolve, reject) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')); };
                img.onload = () => {
                    URL.revokeObjectURL(url);
                    const k = Math.min(1, 600 / Math.max(img.naturalWidth, img.naturalHeight));
                    const w = Math.max(1, Math.round(img.naturalWidth * k)), h = Math.max(1, Math.round(img.naturalHeight * k));
                    const canvas = Object.assign(document.createElement('canvas'), { width: w, height: h });
                    const ctx = canvas.getContext('2d');
                    const draw = (background) => {
                        ctx.clearRect(0, 0, w, h);
                        if (background) { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h); }
                        ctx.drawImage(img, 0, 0, w, h);
                    };
                    let src = '';
                    if (file.type === 'image/png') { draw(false); src = canvas.toDataURL('image/png'); }
                    // photos and big PNGs: JPEG on white (the label is white anyway), well under 1 MB
                    if (!src || src.length > 1300000) { draw(true); src = canvas.toDataURL('image/jpeg', 0.9); }
                    resolve({ src, w, h });
                };
                img.src = url;
            });
        },

        // The design (not the table) can be kept in this browser's Local Storage — only after the
        // visitor agrees (consent window on entering the page). Nothing goes to the server.
        consentKey: 'markor.studio.consent', // 'yes' / 'no' — remembers the answer only
        consent: null,
        consentModal: false,
        // The work in progress (design + loaded table) is kept in this browser's localStorage,
        // so a language switch, reload or accidental close does not lose it. Nothing goes to the server.
        storageKey: 'markor.studio.v1',
        saveTimer: null,
        init() {
            try { this.consent = localStorage.getItem(this.consentKey); } catch (e) { this.consent = null; }
            if (this.consent === 'yes') this.restore();
            else if (this.consent !== 'no') this.consentModal = true;  // first visit: ask before saving anything
            ['elements', 'width', 'height', 'columns', 'gap', 'copies', 'headers', 'hasHeader', 'watermark']
                .forEach((prop) => this.$watch(prop, () => this.persist()));
        },
        acceptStorage() {
            const firstTime = this.consent !== 'yes';
            this.consent = 'yes';
            this.consentModal = false;
            try { localStorage.setItem(this.consentKey, 'yes'); } catch (e) { /* private mode: nothing can be saved */ }
            // a design saved before (e.g. earlier visit) comes back only now; otherwise save the current one
            if (firstTime && !this.fileName && this.hasSavedDesign()) this.restore(); else this.persist();
        },
        declineStorage() {
            this.consent = 'no';
            this.consentModal = false;
            clearTimeout(this.saveTimer);
            try {
                localStorage.removeItem(this.storageKey);   // delete anything saved earlier
                localStorage.setItem(this.consentKey, 'no'); // remember only the answer, so we don't ask every time
            } catch (e) { /* ignore */ }
        },
        hasSavedDesign() {
            try { return !!localStorage.getItem(this.storageKey); } catch (e) { return false; }
        },
        restore() {
            try {
                const saved = JSON.parse(localStorage.getItem(this.storageKey) || 'null');
                if (!saved || !Array.isArray(saved.elements)) return;
                this.width = saved.width;
                this.height = saved.height;
                this.columns = saved.columns || 1;
                this.gap = saved.gap || 0;
                this.copies = saved.copies || 1;
                this.watermark = saved.watermark !== false;
                // design only: no table is restored (upload the file again); column names are kept so the
                // elements reconnect to the same columns
                this.headers = saved.headers || [];
                this.rows = [];
                this.total = 0;
                this.hasHeader = saved.hasHeader !== false;
                this.fileName = '';
                this.sample = {};
                this.elements = saved.elements.map((el, i) => ({ ...el, id: i + 1 }));
                this.nextId = this.elements.length + 1;
            } catch (e) {
                localStorage.removeItem(this.storageKey);
            }
        },
        persist() {
            if (this.consent !== 'yes') return; // no consent: nothing is written to the browser
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => {
                const state = {
                    width: this.width, height: this.height, columns: this.columns, gap: this.gap, copies: this.copies, watermark: this.watermark,
                    hasHeader: this.hasHeader, fileName: this.fileName, total: this.total, headers: this.headers,
                    elements: this.elements.map(({ id, ...el }) => el),
                };
                // only the design: the table rows and file name are never saved (column names stay, so
                // elements reconnect when the same file is uploaded again); Asl Belgisi codes — not even those
                state.rows = []; state.fileName = ''; state.total = 0;
                if (this.marking) state.headers = [];
                // own images make the design big: over ~1.5 MB (or if the browser refuses) they are kept
                // in memory only, and the visitor is told to add them again after a reload
                const withoutImages = () => ({ ...state, elements: state.elements.map((el) => (el.type === 'image' ? { ...el, src: '' } : el)) });
                let json = JSON.stringify(state);
                this.imageNotSaved = false;
                if (json.length > 1500000) { json = JSON.stringify(withoutImages()); this.imageNotSaved = true; }
                try {
                    localStorage.setItem(this.storageKey, json);
                } catch (e) {
                    try {
                        localStorage.setItem(this.storageKey, JSON.stringify(withoutImages()));
                        this.imageNotSaved = state.elements.some((el) => el.type === 'image');
                    } catch (e2) { /* private mode / storage blocked: nothing saved */ }
                }
            }, 300);
        },
        startOver() {
            clearTimeout(this.saveTimer);
            try { localStorage.removeItem(this.storageKey); } catch (e) { /* ignore */ }
            window.location.reload();
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async upload(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.error = window.checkUpload(file, config.upload);
            if (this.error) { event.target.value = ''; return; }
            this.uploading = true;
            try {
                const body = new FormData();
                body.append('file', file);
                const response = await fetch(config.parseUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                    body,
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.errors?.file?.[0] || data.message);
                const known = this.headers.join('|') === data.headers.join('|');
                this.hasHeader = true;
                this.headers = data.headers;
                this.rows = data.rows;
                this.total = data.total;
                this.fileName = file.name;
                this.sample = data.rows[0];
                this.marking = data.marking || null;
                // Same columns as before: keep the design. Otherwise start from the new columns.
                if (!known) this.layoutFromColumns();
                if (this.marking) { this.setupMarking(); this.markingModal = true; }
            } catch (e) {
                this.error = e.message || config.genericError;
            } finally {
                this.uploading = false;
                event.target.value = '';
            }
        },
        // The file has no header row: the first row is data too, columns get generic names.
        // Elements stay bound to the same column position.
        toggleHeader() {
            const old = this.headers;
            let headers, rows;
            if (this.hasHeader) {
                // generic names -> real names taken back from the first row
                const first = this.rows[0] || {};
                headers = old.map((h, i) => (first[h] || '').trim() || `${config.columnLabel} ${i + 1}`);
                rows = this.rows.slice(1);
            } else {
                headers = old.map((h, i) => `${config.columnLabel} ${i + 1}`);
                rows = [Object.fromEntries(old.map((h) => [h, h])), ...this.rows].slice(0, this.maxLabels);
            }
            this.rows = rows.map((row) => Object.fromEntries(old.map((h, i) => [headers[i], row[h] ?? ''])));
            this.elements.forEach((el) => {
                const i = old.indexOf(el.source);
                if (i !== -1) el.source = headers[i];
                if (!this.hasHeader) el.caption = false;
            });
            this.headers = headers;
            this.sample = this.rows[0] || {};
        },
        clearFile() {
            this.marking = null;
            this.elements.forEach((el) => {
                if (el.source && !this.isGenerated(el)) {
                    el.text = el.type === 'text' ? this.preview(el) : String(this.sample[el.source] ?? '');
                    el.source = '';
                    el.caption = false;
                }
            });
            this.headers = [];
            this.rows = [];
            this.total = 0;
            this.fileName = '';
            this.sample = {};
        },
        // A file of Asl Belgisi codes: no header row (the first line is a code too), and one
        // GS1 DataMatrix element bound to the code column instead of text lines.
        setupMarking() {
            if (this.marking.header_is_code && this.hasHeader) {
                this.hasHeader = false;
                this.toggleHeader();
                this.marking.column = this.headers[0];
            }
            const column = this.marking.header_is_code ? this.headers[0] : this.marking.column;
            this.marking.column = column;
            if (this.elements.some((el) => el.symbology === 'asl' && el.source === column)) return;
            this.elements = this.elements.filter((el) => el.source !== column);
            const size = Math.min(22, this.height - 4, this.width - 4);
            this.elements.push({ id: this.nextId++, type: 'barcode', symbology: 'asl', text: '', source: column, x: 2, y: 2, w: size, h: size });
            this.selectedId = null;
        },
        // First upload: replace the demo label with one line per column (up to 4), so the data is visible at once.
        layoutFromColumns() {
            this.elements = [];
            this.headers.slice(0, 4).forEach((header, i) => {
                this.elements.push({
                    id: this.nextId++, type: 'text', text: '', source: header, caption: i > 0,
                    x: 3, y: 3 + i * 6, w: this.width - 6, h: 5,
                    font_size: i === 0 ? 9 : 7, bold: i === 0, align: 'left',
                });
            });
            this.selectedId = null;
        },

        // design + rows sent to the server (PDF download and printing)
        designPayload(extra = {}) {
            return {
                width_mm: Number(this.width),
                height_mm: Number(this.height),
                columns: Number(this.columns) || 1,
                gap_mm: Number(this.gap) || 0,
                copies: this.usesMarking ? Math.min(Number(this.copies) || 1, config.markingLimit) : Number(this.copies) || 1,
                watermark: !!this.watermark,
                elements: this.elements.map(({ id, ...el }) => el),
                rows: this.usesMarking ? this.rows.slice(0, config.markingLimit) : this.rows,
                ...extra,
            };
        },

        // Print without a PDF: the server returns the labels as an HTML page sized for the label
        // printer; it opens in a new window, which calls the system print dialog (installed printers).
        printing: false,
        async printLabels(offset = 0) {
            this.error = '';
            this.clampAll();
            // open the window right away (inside the click), otherwise popup blockers stop it
            const win = window.open('', '_blank');
            if (!win) { this.error = config.popupBlocked; return; }
            win.document.write(`<p style="font:14px system-ui;padding:24px;color:#57534e">${config.printLoading}</p>`);
            this.printing = true;
            try {
                const response = await fetch(config.printUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'text/html, application/json' },
                    body: JSON.stringify(this.designPayload({ offset })),
                });
                if (!response.ok || !(response.headers.get('content-type') || '').includes('text/html')) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(response.status === 429 ? config.tooManyError : Object.values(data.errors || {})[0]?.[0] || data.message || config.genericError);
                }
                const html = await response.text();
                win.document.open();
                win.document.write(html);
                win.document.close();
            } catch (e) {
                win.close();
                this.error = e.message || config.genericError;
            } finally {
                this.printing = false;
            }
        },

        async generate() {
            this.error = '';
            this.clampAll();
            const estimate = Math.max(this.offersCount ? PROMO_MIN_MS / 1000 : 1, this.pdfCount * 0.03 + 0.5);
            const started = Date.now();
            this.promoIndex = this.offersCount ? Math.floor(Math.random() * this.offersCount) : -1;
            this.state = 'working';
            this.progress = 0;
            const timer = setInterval(() => {
                this.progress = Math.min(95, ((Date.now() - started) / 1000 / estimate) * 100);
            }, 200);
            try {
                const response = await fetch(config.generateUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'application/json, application/pdf' },
                    body: JSON.stringify(this.designPayload()),
                });
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(response.status === 429 ? config.tooManyError : Object.values(data.errors || {})[0]?.[0] || data.message);
                }
                // never save an HTML page (redirect, session expired…) as labels.pdf
                if (!(response.headers.get('content-type') || '').includes('application/pdf')) throw new Error(config.genericError);
                const blob = await response.blob();
                await promoWait(started, this.offersCount);
                const url = URL.createObjectURL(blob);
                const link = Object.assign(document.createElement('a'), { href: url, download: 'labels.pdf' });
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 60000);
                this.progress = 100;
                this.state = 'done';
            } catch (e) {
                this.error = e.message || config.genericError;
                this.state = 'idle';
            } finally {
                clearInterval(timer);
            }
        },
    };

    return Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra));
};

// Order request for an offer item / label size. Like every client modal, it stays at least
// PROMO_MIN_MS on screen while sending, with one of our products shown in it.
window.orderModal = (config) => ({
    visible: false,
    state: 'form', // form | sending | done
    title: '',
    target: {},
    error: '',
    progress: 0,
    form: { name: config.name || '', phone: config.phone || '', quantity: '', comment: '', website: '' },

    open(detail) {
        this.target = detail.offer ? { offer_id: detail.offer } : (detail.category ? { offer_category_id: detail.category } : { label_template_id: detail.template });
        this.title = detail.title;
        this.state = 'form';
        this.error = '';
        this.visible = true;
    },
    close() {
        if (this.state === 'sending') return;
        this.visible = false;
    },
    async submit() {
        this.error = '';
        this.state = 'sending';
        const started = Date.now();
        const timer = setInterval(() => (this.progress = Math.min(100, ((Date.now() - started) / PROMO_MIN_MS) * 100)), 200);
        try {
            const response = await fetch(config.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ ...this.target, ...this.form }),
            });
            const data = await response.json().catch(() => ({}));
            await promoWait(started);
            if (!response.ok) {
                throw new Error(response.status === 429 ? config.tooMany : Object.values(data.errors || {})[0]?.[0] || config.error);
            }
            this.form.quantity = '';
            this.form.comment = '';
            this.state = 'done';
        } catch (e) {
            this.error = e.message || config.error;
            this.state = 'form';
        } finally {
            clearInterval(timer);
        }
    },
});

// Super admin: the multilingual form (x-admin.i18n-form)
import './admin/i18n-form.js';

// Client: read DataMatrix codes from the client's own PDF files, in the browser (the PDF is not
// uploaded), and save them as a CSV for the normal import. Prototype (beta).
window.pdfCodeReader = (groups, systems, fallback) => ({
    state: 'idle', // idle | reading | done
    files: [],
    progress: { file: '', page: 0, pages: 0 },
    pages: [],      // [{ file, page, decoded, unreadable }]
    codes: [],      // unique, valid codes in reading order
    rejected: [],   // [{ file, page, text }] decoded but not a marking code of any category
    duplicates: 0,
    notGs1: 0,
    votes: {},      // system => codes that fit only that system
    hints: {},      // system => ambiguous codes whose GTIN prefix points to it (460–469 RU, 478 UZ)
    error: '',
    cancelled: false,
    started: 0,
    seconds: 0,

    // like MarkingCodeParser::detectSystem(): codes that fit one system only decide; else the company's usual
    get system() {
        const pick = (o) => Object.entries(o).sort((a, b) => b[1] - a[1])[0]?.[0];
        return pick(this.votes) || pick(this.hints) || fallback;
    },
    get systemDetected() { return Object.keys(this.votes).length > 0; },
    get systemLabel() { return systems[this.system] || this.system; },
    get unreadable() { return this.pages.reduce((n, p) => n + p.unreadable, 0); },
    get problemPages() { return this.pages.filter((p) => p.unreadable > 0); },
    get byGtin() {
        const m = {};
        for (const c of this.codes) {
            const g = (c.found.find((f) => f.group.system === this.system) || c.found[0]).group.name;
            const k = c.gtin + '|' + g;
            (m[k] ??= { gtin: c.gtin, group: g, count: 0 }).count++;
        }
        return Object.values(m).sort((a, b) => b.count - a.count);
    },

    pick(event) {
        this.files = [...event.target.files].filter((f) => /\.pdf$/i.test(f.name) || f.type === 'application/pdf');
        event.target.value = '';
        if (this.files.length) this.read();
    },

    async read() {
        Object.assign(this, { state: 'reading', pages: [], codes: [], rejected: [], duplicates: 0, notGs1: 0, votes: {}, hints: {}, error: '', cancelled: false, started: Date.now() });
        const seen = new Set();
        try {
            const { readPdf, normalize, matchStructures } = await import('./pdf-code-reader/decoder.js');
            for (const file of this.files) {
                if (this.cancelled) break;
                this.progress = { file: file.name, page: 0, pages: 0 };
                await readPdf(file, {
                    isCancelled: () => this.cancelled,
                    onPage: ({ page, pages, decoded, unreadable }) => {
                        this.progress = { file: file.name, page, pages };
                        this.pages.push({ file: file.name, page, decoded: decoded.length, unreadable });
                        for (const d of decoded) {
                            const code = normalize(d.text);
                            const found = matchStructures(code, groups);
                            if (!found.length) { this.rejected.push({ file: file.name, page, text: code }); continue; }
                            if (seen.has(code)) { this.duplicates++; continue; }
                            seen.add(code);
                            if (!d.gs1) this.notGs1++;
                            const fits = [...new Set(found.map((f) => f.group.system))];
                            if (fits.length === 1) {
                                this.votes[fits[0]] = (this.votes[fits[0]] || 0) + 1;
                            } else {
                                // GS1 prefix of the GTIN as a tie-breaker (like MarkingCodeParser::gtinHint)
                                const prefix = Number(code.slice(3, 6));
                                const hint = prefix >= 460 && prefix <= 469 ? 'chestny_znak' : prefix === 478 ? 'aslbelgisi' : null;
                                if (hint && fits.includes(hint)) this.hints[hint] = (this.hints[hint] || 0) + 1;
                            }
                            this.codes.push({ code, gtin: found[0].gtin, found });
                        }
                    },
                });
            }
        } catch (e) {
            this.error = e?.message || String(e);
        }
        this.seconds = Math.round((Date.now() - this.started) / 1000);
        this.state = 'done';
    },

    // one code per line, exactly what the CSV import reads (codes may contain quotes and commas)
    download() {
        const blob = new Blob([this.codes.map((c) => c.code).join('\n') + '\n'], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (this.files.length === 1 ? this.files[0].name.replace(/\.pdf$/i, '') : 'codes') + '.csv';
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    },

    reset() { Object.assign(this, { state: 'idle', files: [], pages: [], codes: [], rejected: [], error: '' }); },
});
