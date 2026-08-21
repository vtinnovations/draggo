# Font Awesome 5 (lokal) — optionale Icon-Quelle für Draggo

Draggo nutzt eigene Inline-SVG-Icons (keine Abhängigkeit). **Optional** ist hier
Font Awesome **5.15.4 Free** lokal eingebunden — komplett ohne CDN, DSGVO-konform.

## Status: INSTALLIERT ✓

Bereits vorhanden:

```
Resources/public/vendor/fontawesome/
  css/all.min.css
  webfonts/   (fa-solid-900, fa-brands-400, fa-regular-400 als woff2/woff/ttf/…)
```

Nach dem Upload auf dem Server nur noch:

```
vendor/bin/contao-console assets:install
vendor/bin/contao-console cache:clear
```

Draggo erkennt `css/all.min.css` automatisch und lädt es im Editor + Frontend.
Im Icon-Picker erscheint der Tab **„Font Awesome"**.

## Verwendung (FA5-Syntax!)

- **Icon-Element, Button-Icon, Box-Icon, Icon-Liste, Flip-Box** etc.: im
  Icon-Picker Tab „Font Awesome" wählen.
- **Social-Icons** (Schlüssel manuell eintragen), FA5-Klassen:
  `fab fa-instagram`, `fab fa-linkedin-in`, `fab fa-facebook-f`, `fab fa-youtube`,
  `fab fa-whatsapp`, `fab fa-twitter`.
- Solid-Icons: `fas fa-star`, `fas fa-home`, … (FA5 nutzt `fas`/`fab`/`far`,
  NICHT die FA6-Langform `fa-solid`).
- Gespeichert wird die Klasse als String; gerendert `<i class="draggo-fa fas fa-star">`.

Ohne FA bleibt alles funktionsfähig — der FA-Tab ist dann leer, die eingebauten
SVG-Icons stehen weiter zur Verfügung.

> Upgrade auf FA6 möglich: einfach `css/all.min.css` + `webfonts/` ersetzen. Dann
> in `src/Icon/FaIcons.php` die Klassen auf FA6-Syntax (`fa-solid fa-…`) umstellen.
