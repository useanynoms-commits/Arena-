# E-Com Minutes — Hostinger PHP/MySQL build

Production storefront implementation using the requested stack:

- **Frontend:** server-rendered HTML + compiled Tailwind CSS; small Alpine.js interactions for mobile menus and banner/offer rotation.
- **Backend:** PHP 8.1+ with PHP sessions.
- **Database:** MySQL 8 / MariaDB 10.4+.
- **Images:** signed, server-side Cloudinary uploads.
- **Checkout:** internal order capture with an optional Fastrr hosted-checkout handoff; Shiprocket shipment creation/tracking hooks.
- **Hosting:** Hostinger Single plan with a custom domain and SSL.

## Install on Hostinger

1. Create a MySQL database and user in hPanel.
2. Import `database/schema.sql` through phpMyAdmin.
3. Copy `.env.example` to `.env`, fill in database, Cloudinary and Shiprocket credentials, and keep it **outside `public/`**.
4. On a development machine run `npm run build:php-css`; upload the generated `public/assets/tailwind.css`.
5. Set the domain document root to `php-app/public`.
6. Open `/setup` to create the first isolated tenant and administrator.
7. Sign in at `/admin/login`.

## Cloudinary and Shiprocket

Cloudinary credentials remain only in PHP server environment. Product, logo, hero, and banner uploads go through `cloudinary_upload()`.

Shiprocket login, adhoc shipment creation and tracking are implemented in `app/services.php`. Add API email/password and a pickup location to enable them. Fastrr Checkout requires merchant activation and the hosted checkout URL provided by Shiprocket; set `FASTRR_CHECKOUT_URL` and `FASTRR_MERCHANT_ID` when activated. Until then, the checkout stores an order and uses the normal Shiprocket fulfillment flow.

## SEO and performance

The server renders product and collection pages, canonical URLs, meta descriptions, Open Graph data, and JSON-LD product/schema markup. Product images include lazy loading; Tailwind is compiled and purged from the PHP templates rather than delivered as the Tailwind CDN runtime.
