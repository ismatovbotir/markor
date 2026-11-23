# Client products and client label designs

Decided with the owner on 23.11.2026.

## Products: per company, never shared

- Every product belongs to one company (`products.company_id`). A GTIN/EAN is unique **within a company** (`unique(company_id, gtin)`); the same GTIN in two companies is two independent products.
- A plain index on `products.gtin` keeps cross-company analytics for the super admin.
- Migration `2026_11_23_100004_make_products_per_company` split the old shared rows: each company got its own copy of every product it had codes of (its own name from `company_product_names`, else the shared name, else the GTIN) and its codes, batches and aggregations moved to that copy. Products nobody had codes for were soft-deleted (audit log `product.orphan_deleted`). `company_product_names` is no longer used.

### Product card (client)

One card, no language versions — the client writes what they want:

| Field | Column |
|---|---|
| GTIN / EAN (required) | `gtin` (stored as GTIN-14) |
| Name | `name` |
| Description | `description` |
| SKU | `sku` |
| Weight / volume | `weight` |
| Manufacturer | `manufacturer` |
| Country of origin | `country` |
| Shelf life | `shelf_life` |

- **No barcode?** "Generate" makes an internal EAN-13: `20` + company id (4 digits) + item number (6 digits, `gtin_seq`) + GS1 check digit. Example: company 7, item 15 → `2000070000151`. The 20–29 prefix is GS1's in-store range: fine for the company's own labels, **not valid for Asl Belgisi / Честный ЗНАК** (they need a registered GS1 GTIN) — the UI says so.
- **Import:** a GTIN the company doesn't have yet → product created automatically; the name comes from tasnif.soliq.uz (AI-cleaned), or is the GTIN itself when tasnif doesn't know it. Until the client edits the name, it follows the classifier; after an edit (`attributes.name_by_client`) the classifier never overwrites it.
- A product can be deleted only while it has no marking codes.

### Product label

- "Print labels" on each product: choose a template from the client's "Mine", then print (browser) or PDF.
- **One label, no marking code.** The template's DataMatrix area shows the Markor logo; text fields come from the card (name, GTIN, SKU, description, weight, manufacturer, country, shelf life — also available in the template designer). Copies are set in the system print dialog.

### Super admin

- Read-only list of every company's products with the owner company; filter by company.
- "GTIN analytics": per GTIN — number of companies, number of marking codes.
- Cannot create, edit, delete or print client products.

## Client label designs

- The templates page has two blocks:
  - **Mine** — the client's own designs (tag "Mine") and the global templates it starred (tag "Markor" with the Markor logo). PDF/print offer exactly these; when empty, all templates.
  - **Global templates** — made by Markor, or client designs published by the super admin.
- A client may keep `companies.template_limit` designs (5 by default; the super admin changes it in company settings). Deleted ones don't count.
- New designs start with the grey `markor.uz` watermark in the corner switched on (the client may switch it off).
- A client design is private to its company. The super admin sees it with the owner but never edits it.
- **Consent:** on the designer page the client may tick "the admin may open this design for all clients". Without it the super admin can't make the design global; removing the tick closes a published design again.
- **Client deletes a design:** hidden from the client; the super admin sees it as "deleted by the client" and may accept it as a global template — only if consent was given.
- Another company's private design is never visible or usable: lists, preview, PDF/print, Telegram bot, marketplace, public pages, llms.txt.
