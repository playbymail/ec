---
paths:
  - 'resources/js/**'
---

# Js

## Regenerate Wayfinder helpers through Vite, not bare artisan
vite.config.ts configures the Wayfinder plugin with `formVariants: true`. Running a bare `php artisan wayfinder:generate` regenerates resources/js/routes and resources/js/actions WITHOUT the form variants, which breaks `tsc --noEmit` across every page that uses `.form` (auth pages, settings, two-factor components).

After adding or renaming a route, run `npm run build` (or `npm run dev`) to regenerate, or pass `--with-form` to the artisan command. The flag is `--with-form`, not `--with-form-variants`; wayfinder 0.1.21 rejects the latter. Both directories are gitignored, so a bad regeneration only breaks the local typecheck — it never reaches the repo.

Adding a page also requires `npm run build` before feature tests will pass: Inertia resolves the page through the Vite manifest, so a page missing from `public/build/manifest.json` makes the whole request 500 with `ViteException: Unable to locate file in Vite manifest`.

## The appearance cookie is the only theme store
`resources/views/app.blade.php` paints the first frame from the `appearance` cookie — an `@class` directive for an explicit choice, plus a blocking script that resolves `system` against `prefers-color-scheme`, which the server cannot know. `resources/js/hooks/use-appearance.tsx` reads that same cookie back, so hydration re-applies what is already on screen.

Do not add localStorage back. It was there, read first, and drifted: a cookie has its own expiry and its own "clear cookies" button, so whichever store outlived the other won and the loser was what the server had already painted — a theme that flips a moment after the page appears. One store the server can see beats two that can disagree. `initializeTheme()` writes the value straight back to refresh the cookie's expiry.

Three things hold it up and each has a test in `tests/Feature/AppearanceTest.php`: the script must stay ahead of `@vite` (resolving after the stylesheet is requested is the flash it exists to prevent), the cookie must be interpolated with `@js()` and never `{{ }}` (it is attacker-settable and lands inside a script body), and `appearance` must stay in `encryptCookies(except:)` in `bootstrap/app.php` or the middleware discards the plainly-written cookie.

Verifying a Blade edit needs `php artisan view:clear` first — compiled views are cached in `storage/framework/views`, and without it a mutation appears to pass while changing nothing.
