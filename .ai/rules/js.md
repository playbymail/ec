---
paths:
  - 'resources/js/**'
---

# Js

## Regenerate Wayfinder helpers through Vite, not bare artisan
vite.config.ts configures the Wayfinder plugin with `formVariants: true`. Running a bare `php artisan wayfinder:generate` regenerates resources/js/routes and resources/js/actions WITHOUT the form variants, which breaks `tsc --noEmit` across every page that uses `.form` (auth pages, settings, two-factor components).

After adding or renaming a route, run `npm run build` (or `npm run dev`) to regenerate, or pass `--with-form` to the artisan command. The flag is `--with-form`, not `--with-form-variants`; wayfinder 0.1.21 rejects the latter. Both directories are gitignored, so a bad regeneration only breaks the local typecheck — it never reaches the repo.

Adding a page also requires `npm run build` before feature tests will pass: Inertia resolves the page through the Vite manifest, so a page missing from `public/build/manifest.json` makes the whole request 500 with `ViteException: Unable to locate file in Vite manifest`.
