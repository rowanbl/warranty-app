# Car Sync

Laravel 13 backend, Preact frontend in TypeScript, Vite 8. The frontend owns
page routing, Laravel owns the API under `/api`.

## Read this first

Before touching any code, read the docs in `docs/`. They are short and they
are the rules for this project, not just background. Do not write or change
code until you have read them.

- [docs/ways-of-working.md](docs/ways-of-working.md) — how to approach a task.
  Check the business logic with the user first, then test driven to spec.
- [docs/coding-practices.md](docs/coding-practices.md) — clean, small,
  readable code. Reuse before you build.
- [docs/design-system.md](docs/design-system.md) — the visual rules and where
  styles live.
- [docs/writing-style.md](docs/writing-style.md) — how we write comments,
  copy and docs.

If a change touches the frontend, the design system and coding practices docs
apply. If it touches behaviour, start with ways of working. When the docs and
a request seem to disagree, ask rather than guess.

## Project shape

- `routes/web.php` — a single catch all that returns the Preact shell for any
  path not starting with `/api`.
- `routes/api.php` — the backend. All data and actions live under `/api`.
- `resources/js/app.tsx` — frontend entry and route table.
- `resources/js/pages/` — one component per page, mostly wiring.
- `resources/js/components/` — shared, reusable UI. Icons live in
  `components/icons/`.
- `resources/js/hooks/` — shared hooks, e.g. `useMediaQuery`.
- `resources/css/` — design tokens, base, layout, and one file per component,
  all pulled together by `app.css`.
- `tsconfig.json` — strict TypeScript, Preact JSX.

A note on components. Most are wired into the page and ship as normal. The
accordion is kept as a component but its CSS isn't imported into `app.css`, so
it ships nothing until a page uses it. To use it, add its import. This is the
pattern for heavier components we want available but not always loaded.

## Commands

- `npm run dev` — Vite dev server with hot reload.
- `npm run build` — production build.
- `npm run type-check` — `tsc --noEmit`, must pass before a change is done.
- `php artisan serve` — run the app.
- `php artisan test` — run the test suite.
- `php artisan make:*` — scaffold backend classes. Prefer this over hand
  writing files, then edit what it generates. See coding practices.
