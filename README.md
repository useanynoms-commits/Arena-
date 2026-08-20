# E-Com Minutes

## Production stack

The production-ready implementation is in [`php-app/`](php-app/):

- HTML + compiled Tailwind CSS + Alpine.js
- PHP 8.x + MySQL + PHP Sessions
- Cloudinary image uploads
- Shiprocket logistics and optional Fastrr hosted checkout handoff
- Hostinger Single deployment configuration, custom domain, and SSL readiness
- Git/GitHub workflow

See [`php-app/README.md`](php-app/README.md) for installation and deployment instructions.

The existing React/Vite application remains in the repository as the interactive design prototype. The PHP application is the server-rendered production deployment target.
