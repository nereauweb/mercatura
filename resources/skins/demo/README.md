# Demo skin

The only skin allowed in the core. It exists to prove the overlay mechanism
(`docs/ARCHITECTURE.md §3`) and to be copied as the starting point of an
installation skin. It overrides:

- `frontend/public/header.blade.php` — logo from `config('brand.logo')` and a
  "demo" ribbon
- `frontend/pages/home.blade.php` — a compact home built from brand config
  and lang keys; pushes the skin stylesheet to the layout's `head` stack
- `brand.php` — identity keys that differ from the neutral defaults
- `lang/it/frontend.php` — two copy keys

Activate it with `MERCATURA_SKIN=demo`. Check it with
`php artisan mercatura:skin-check demo`. Static assets live in
`public/skins/demo/`.
