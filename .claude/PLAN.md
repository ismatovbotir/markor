# Build Plan — Aslbelgi Manager CRM

## Phase 1 — MVP

Build order: each row = one `/make-module` run (migration + model + service + livewire + views + policy).
Work model-by-model. Within each model, generate migration / model / service / livewire / views **in parallel**.

| # | Model | Domain | Status | Notes |
|---|-------|--------|--------|-------|
| 1 | User | Auth | ✅ | role, locale, qr_token, telegram_chat_id, phone, is_active, SoftDelete |
| 2 | ProductGroup | Catalog | ✅ | code_format enum, serial_length, crypto_length, all with Auditable |
| 3 | Product | Catalog | ✅ | FK product_groups, gtin unique, attributes JSONB, SoftDelete |
| 4 | ImportBatch | Marking | ✅ | type enum, status enum, error_log JSONB |
| 5 | MarkingCode | Marking | ✅ | Indexes on status, product_id+status, aggregation_id, gtin+serial |
| 6 | Aggregation | Aggregation | ✅ | Self-ref parent_id, sscc unique, level 1-3, SoftDelete |
| 7 | LabelTemplate | Printing | ✅ | printer_type enum, template text with placeholders |
| 8 | Printer | Printing | ✅ | connection_mode enum, host, port, SoftDelete |
| 9 | PrintJob | Printing | ✅ | code_ids JSONB, status enum, FK template+printer |
| 10 | AuditLog | Audit | ✅ | payload JSONB, ip, user_agent — write-only |
| 11 | NotificationsLog | Notifications | ✅ | channel enum, status, sent_at |

## Phase 1 — Supporting Infrastructure (after models)

| # | Task | Status |
|---|------|--------|
| A | Auth scaffolding (email+password login + QR scan login) | ✅ |
| B | Sidebar layout with role-based nav | ✅ |
| C | CSV import pipeline (ProcessImportBatchJob + league/csv parser) | ✅ |
| D | Scanning Livewire component (autofocus input, normalizer, AggregationScanner) | ✅ |
| E | PDF print driver (DataMatrix via tc-lib-barcode + RenderPrintJobJob) | ✅ |
| F | Aggregation level-1 workflow | ✅ |
| G | Reports 1–4 (journal, aggregation tree, status counts, single-code trace) | ⏳ Phase 2 |
| H | Telegram notification channel | ⏳ Phase 2 |
| I | i18n setup (uz/ru JSON) | ✅ |
| J | Docker Compose stack (php-fpm + nginx + postgres + redis + horizon) | ⏳ Phase 2 |

## Phase 2 (after MVP sign-off)
- Full 3-level aggregation + SSCC pool
- Direct TCP print (ZPL/EZPL/SBPL)
- Extended reports 5–11
- SMS channel
- 2FA admin

## Conventions
- Domain path: `app/Domain/<Domain>/`
- Livewire path: `app/Http/Livewire/<Domain>/`
- Views: `resources/views/livewire/<domain>/`
- Policies: `app/Policies/`
- Lang files: `lang/uz.json`, `lang/ru.json`
- Design: Tailwind only, stone/amber/emerald/rose palette (see CLAUDE.md §8)
- Every model: SoftDeletes + Auditable
- Operators see only own records — enforce in Service layer via `Auth::user()` scope
