// Marking-code checks shared by the browser pages (CSV upload, PDF reader): ports of
// MarkingCodeParser (normalize, split) and of ProcessImportBatchJob::parseLine, so the browser
// finds the same codes the server will load. The server still checks everything again.

/** Canonical stored form, same as MarkingCodeParser::normalize(). */
export function normalize(text) {
    let s = text.replace(/^[\s\0﻿]+|[\s\0﻿]+$/g, '');
    if (/^\](d2|d1|C1)/.test(s)) s = s.slice(3);
    return s.replace(/[\x1D\x1E\xE8]/g, '');
}

/** Port of MarkingCodeParser::split(): every product category whose structure fits the code. */
export function matchStructures(code, groups) {
    const found = [];
    for (const g of groups) {
        const s = g.structure || [];
        const len = s.reduce((n, seg) => n + String(seg.ai ?? '').length + Number(seg.length), 0);
        if (!s.length || code.length !== len) continue;
        let pos = 0, gtin = '', ok = true;
        for (const seg of s) {
            const ai = String(seg.ai ?? '');
            if (ai && code.substr(pos, ai.length) !== ai) { ok = false; break; }
            pos += ai.length;
            if (ai === '01') gtin = code.substr(pos, Number(seg.length));
            pos += Number(seg.length);
        }
        if (ok && gtin && !/^\d+$/.test(gtin)) ok = false;
        if (ok) found.push({ group: g, gtin });
    }
    return found;
}

/** First field of a CSV line (like PHP str_getcsv(..., '"', '')[0]). */
function firstField(line, delimiter) {
    if (!line.startsWith('"')) {
        const i = line.indexOf(delimiter);
        return i < 0 ? line : line.slice(0, i);
    }
    let out = '';
    for (let i = 1; i < line.length; i++) {
        if (line[i] === '"') {
            if (line[i + 1] === '"') { out += '"'; i++; continue; }
            const rest = line.slice(i + 1);
            const j = rest.indexOf(delimiter);
            return out + (j < 0 ? rest : rest.slice(0, j));
        }
        out += line[i];
    }
    return out;
}

/** Like ProcessImportBatchJob::parseLine(): the whole line, then the first CSV column. */
export function parseLine(raw, groups) {
    const candidates = [raw];
    for (const d of [',', ';', '\t']) {
        const first = firstField(raw, d);
        if (first !== raw) candidates.push(first);
    }
    for (const c of candidates) {
        const code = normalize(c);
        const found = matchStructures(code, groups);
        if (found.length) return { code, gtin: found[0].gtin, found };
    }
    return null;
}

/**
 * Checks a codes file before it is uploaded: what the server would load.
 * GTIN-only lines (8–14 digits) are counted apart, to say "these are barcodes, not marking codes".
 */
export function checkCodesFile(text, groups) {
    const r = { valid: 0, invalid: 0, gtinOnly: 0, repeated: 0, invalidLines: [], gtins: [] };
    const seen = new Set(), gtins = new Set();
    const lines = text.split('\n');
    lines.forEach((raw, i) => {
        if (raw.trim() === '') return;
        const parsed = parseLine(raw, groups);
        if (!parsed) {
            if (r.valid === 0 && r.invalid === 0 && !raw.includes('01')) return; // header row (same rule as the server)
            r.invalid++;
            if (/^\d{8,14}$/.test(firstField(normalize(raw), ',').replace(/[";\s]/g, ''))) r.gtinOnly++;
            if (r.invalidLines.length < 5) r.invalidLines.push(i + 1);
            return;
        }
        if (seen.has(parsed.code)) { r.repeated++; return; }
        seen.add(parsed.code);
        gtins.add(parsed.gtin);
        r.valid++;
    });
    r.gtins = [...gtins];
    return r;
}
