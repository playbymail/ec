---
paths:
  - 'resources/views/**'
---

# Views

## app.blade.php resolves the theme before first paint
The `<head>` of `app.blade.php` is load-bearing for the theme, and the reasoning lives in [js.md](js.md) under "The appearance cookie is the only theme store". Read it before editing that file.

In short: the blocking script must stay ahead of `@vite`, and the `appearance` cookie must be interpolated with `@js()` rather than `{{ }}` because it is attacker-settable and sits inside a script body. `tests/Feature/AppearanceTest.php` fails on either change.

Compiled views are cached in `storage/framework/views`, so run `php artisan view:clear` before checking any edit to this directory by hand — otherwise the old template is what answers.
