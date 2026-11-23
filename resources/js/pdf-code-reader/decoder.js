// Reads every GS1 DataMatrix from a PDF, entirely in the browser: the PDF is never uploaded.
// pdf.js draws each page to a canvas, zxing-cpp (WebAssembly) finds and decodes the symbols.
// Loaded on demand (dynamic import) so other pages don't download pdf.js or the wasm file.
import * as pdfjs from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { prepareZXingModule, readBarcodes } from 'zxing-wasm/reader';
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
// self-hosted wasm: the site also works on LAN / without access to a CDN
prepareZXingModule({ overrides: { locateFile: (path, prefix) => (path.endsWith('.wasm') ? wasmUrl : prefix + path) } });

const PT_PER_MM = 72 / 25.4;
const FIRST_DPI = 300;   // enough for codes from ~10 mm (tested); 150 dpi already loses them
const RETRY_DPI = 600;   // only for pages where some symbol was seen but not decoded
const MAX_TILE_PX = 4096; // canvas side limit kept well under mobile browser limits
const OVERLAP_MM = 40;   // tiles overlap by more than the largest expected code
const READER = { formats: ['DataMatrix'], maxNumberOfSymbols: 255, textMode: 'Plain', returnErrors: true, tryHarder: true };

// normalize / matchStructures are shared with the CSV upload check
export { normalize, matchStructures } from '../marking-codes.js';

/** Decodes one area of a page (in PDF points) at the given dpi; positions come back in points. */
async function scanArea(page, dpi, x, y, w, h) {
    const scale = dpi / 72;
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(w * scale);
    canvas.height = Math.ceil(h * scale);
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    const viewport = page.getViewport({ scale, offsetX: -x * scale, offsetY: -y * scale });
    await page.render({ canvasContext: ctx, viewport, canvas }).promise;
    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
    canvas.width = canvas.height = 0; // free the memory now, not at the next GC

    return (await readBarcodes(image, READER)).map((r) => {
        const p = r.position;
        const cx = (p.topLeft.x + p.topRight.x + p.bottomRight.x + p.bottomLeft.x) / 4;
        const cy = (p.topLeft.y + p.topRight.y + p.bottomRight.y + p.bottomLeft.y) / 4;
        return { valid: r.isValid, text: r.text, gs1: r.symbologyIdentifier === ']d2', x: x + cx / scale, y: y + cy / scale };
    });
}

/** The page cut into overlapping tiles small enough for the canvas at this dpi. */
async function scanPage(page, dpi) {
    const [x0, y0, x1, y1] = page.view;
    const W = x1 - x0, H = y1 - y0;
    const maxPt = (MAX_TILE_PX / dpi) * 72;
    const overlap = OVERLAP_MM * PT_PER_MM;
    const step = Math.max(maxPt - overlap, maxPt / 2);
    const results = [];
    for (let y = 0; y < H; y += step) {
        for (let x = 0; x < W; x += step) {
            results.push(...await scanArea(page, dpi, x, y, Math.min(maxPt, W - x), Math.min(maxPt, H - y)));
            if (x + maxPt >= W) break;
        }
        if (y + maxPt >= H) break;
    }
    return results;
}

/** Symbols seen in several tiles / passes are counted once (same text, or same place). */
function merge(results) {
    const near = (a, b) => Math.hypot(a.x - b.x, a.y - b.y) < 3 * PT_PER_MM;
    const valid = [];
    for (const r of results.filter((r) => r.valid)) {
        if (!valid.some((v) => v.text === r.text)) valid.push(r);
    }
    const unreadable = [];
    for (const r of results.filter((r) => !r.valid)) {
        if (!valid.some((v) => near(v, r)) && !unreadable.some((u) => near(u, r))) unreadable.push(r);
    }
    return { valid, unreadable };
}

/**
 * Reads one PDF file. onPage({ page, pages, decoded, unreadable }) is called after every page;
 * isCancelled() is checked between pages. Returns [{ page, decoded:[{text, gs1}], unreadable }].
 */
export async function readPdf(file, { onPage, isCancelled } = {}) {
    const task = pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()) });
    const doc = await task.promise;
    const pages = [];
    try {
        for (let n = 1; n <= doc.numPages; n++) {
            if (isCancelled?.()) break;
            const page = await doc.getPage(n);
            let found = merge(await scanPage(page, FIRST_DPI));
            if (found.unreadable.length) {
                // a symbol is there but too small / blurry at 300 dpi: look closer. Only what the closer
                // look still can't read counts — text or pictures can look like a symbol at 300 dpi.
                found = merge([...found.valid, ...await scanPage(page, RETRY_DPI)]);
            }
            page.cleanup();
            const entry = { page: n, decoded: found.valid.map((r) => ({ text: r.text, gs1: r.gs1 })), unreadable: found.unreadable.length };
            pages.push(entry);
            onPage?.({ ...entry, pages: doc.numPages });
        }
    } finally {
        await task.destroy();
    }
    return pages;
}
