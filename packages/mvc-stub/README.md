# Celeris MVC Stub

`celeris/mvc` is a base application scaffold for building server-rendered web applications on top of `celeris/framework`.

It is designed to help you start with a working MVC structure instead of a blank project: controllers, views, services, repositories, configuration, asset build hooks, example CRUD pages, and optional integrations are already in place.

## What It Is For

Use this package when you want to build a traditional web application with HTML pages rendered on the server.

It is a good fit for:

- internal dashboards
- admin panels
- business applications with forms and CRUD flows
- projects that prefer server-rendered pages over SPA-heavy architecture
- teams that want clean MVC structure without a large framework footprint

## Included Features

- Server-rendered application bootstrap with a working homepage and contacts CRUD flow
- Attribute-based controller routing
- MVC layering with controllers, services, repositories, and models already separated
- Form request objects and model policies for explicit validation and authorization
- Multiple view engine options with configuration for `php`, `twig`, `plates`, and `latte`
- Shared layouts and partials for starter UI composition
- Asset build scripts for JavaScript, CSS, and static images
- Database-backed or file-backed contacts storage depending on configuration
- Environment-based security toggles for JWT, opaque tokens, cookie sessions, API tokens, mTLS, and CSRF
- Built-in request throttling configuration
- Optional notification integrations for SMTP, in-app notifications, transactional outbox, realtime delivery, and dispatch workers
- CLI wrapper for Celeris commands plus a template smoke-test script

## Advantages

- Faster delivery: you can start from a functioning web app instead of assembling the basics manually
- Flexible rendering: choose the template engine that best fits your team without rewriting the scaffold
- Clean extension points: generated base classes let you customize application code while keeping scaffolding boundaries clear
- Practical defaults: forms, layouts, views, and CRUD pages help teams move from scaffold to real feature work quickly
- Good migration path: the stub begins simple, but leaves room for database-backed workflows, notifications, and stronger security as the app grows

## Default Application Surface

The scaffold currently includes:

- a homepage route with a starter welcome screen
- a contacts section with list, detail, create, edit, and delete pages
- shared layout and partial templates
- example middleware hook points for protected routes
- configurable rendering through the selected view engine

The sample contacts domain is intentionally small. It exists to demonstrate the structure and request flow, not to define how your real application should look.

## Quick Start

Create a project from the package:

```bash
composer create-project celeris/mvc my-app
cd my-app
cp .env.example .env
composer install
php celeris app-key
npm install
npm run build
php -S 127.0.0.1:8000 -t public
```

If you are working inside the Celeris monorepo, the scaffold can also fall back to the local framework bootstrap and CLI binary during development.

## View Engines

The default view engine is plain PHP templates, which keeps the initial setup light.

If your team prefers another renderer, the scaffold is already prepared for:

- `twig/twig`
- `league/plates`
- `latte/latte`

Switching engines is controlled through `VIEW_ENGINE` and the related view configuration in `.env`.

To validate template rendering, you can use:

```bash
php scripts/view-smoke.php
php scripts/view-smoke.php --all
```

## Frontend Assets

The package includes an asset pipeline for CSS, JavaScript, and static images.

Useful commands:

```bash
npm run build
npm run dev
npm run watch
```

Compiled assets are written under `public/assets`.

## Project Structure

- `public/index.php` boots the HTTP kernel and registers page controllers
- `app/Http/Controllers` contains page controllers
- `app/Http/Requests` contains form request classes with `authorize()` and `rules()`
- `app/Services` holds application use cases
- `app/Repositories` contains persistence-facing code
- `app/Models` contains view-facing domain models
- `app/Policies` contains model action policies such as `view`, `create`, `update`, and `delete`
- `app/Views` contains layouts, partials, and page templates
- `config` centralizes app, view, database, and security behavior
- `database/migrations` and `database/seeds` contain starter persistence files
- `resources` contains source frontend assets
- `scripts` contains helper scripts such as the renderer smoke test and asset builder

## Storage and Data

The contacts example can work with database-backed persistence or a simpler file-backed mode, depending on your environment configuration.

That makes the scaffold useful both for quick local prototypes and for real applications that will move into managed database infrastructure.

## Recommendations

- Replace the sample `Contact` domain early with your own domain language and workflows
- Pick one template engine deliberately and remove unused ones if you want a tighter project surface
- Review `.env.example` closely before deployment, especially security and view cache settings
- Set `APP_KEY` before enabling signed cookies or other stateful security features
- Keep CSRF enabled for form-based flows unless you have a strong reason not to
- Use the sample CRUD pages as reference implementations, not as long-term placeholder UI
- Add tests around your real controllers, templates, and form handling as soon as you begin customization

## Tip

This stub works best when you treat it as a strong starting structure, not a finished product. Keep the layering, reuse the rendering and asset pipeline conventions, and let the sample pages guide your first production features rather than survive unchanged for too long.
