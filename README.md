# E-Com Minutes — PHP tenant storefront

A low-cost, shared-hosting friendly e-commerce tenant storefront built with **PHP 8.1+** and **SQLite**. It does not require WordPress, MySQL, Node.js, or a paid SaaS platform.

## Included

- Store setup wizard: brand identity, logo uploads, first product, multi-category assignment, theme, colors, and publish flow.
- Public storefront with responsive themes, product detail pages, full-text catalog search, category filters, cart, coupon application, checkout requests, and order confirmation.
- Protected admin workspace for products, multi-category catalog assignments, categories, hero sale banners, top offer/coupon announcements, orders, and every storefront setting.
- Image uploads with MIME/type and size validation.
- SQLite FTS5 product indexing with a LIKE-based fallback for hosts that do not compile SQLite with FTS5.
- Theme options: Minimal, Neo-brutalist, Editorial, Playful, and Luxe.
- Server-side Gemini content generation in the admin workspace, using compact prompts, automatic Gemini-model fallback, and a six-generation browser-session limit. The API key is never rendered to the storefront.

## Run locally

```bash
php -S 0.0.0.0:8000 index.php
```

Open `http://localhost:8000/setup.php` to create the first store administrator and configure the tenant.

## Shared hosting deployment

1. Upload the repository contents to the PHP web root.
2. Ensure PHP 8.1+ has `pdo_sqlite`, `sqlite3`, `fileinfo`, and `curl` enabled.
3. Give the web server write permission to `data/` and `uploads/` (usually `775`).
4. Open `/setup.php`, finish setup, and log in at `/admin.php`.
5. Point your custom domain to the hosting directory. The included `.htaccess` enables clean paths on Apache; query-string routes work without rewrite support.
6. Optional AI copy generation: set `GEMINI_API_KEY`, `GEMINI_MODEL`, and `GEMINI_FALLBACK_MODELS` in the host’s server-side environment. See `.env.example`; never upload a real `.env.local` file.

The SQLite database is created automatically at `data/ecom-minutes.sqlite`; it is intentionally ignored by Git. Back it up regularly along with `uploads/`.

## Tenant routing

Each tenant has a unique slug in the `stores` table. The public app resolves the store from `?store=your-slug`, or from the first subdomain label on hosts configured for wildcard subdomains. This lets the same PHP codebase serve multiple tenant storefronts.
