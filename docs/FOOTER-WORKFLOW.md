# Hlavička, patička, CSS a verze JS

Hlavička (`partials/header.html`) a patička (`partials/footer.html`) jsou zdrojové fragmenty, nikoli samostatné
stránky. Neupravujte je přímo v jednotlivých HTML souborech: skript je do každé stránky vkládá sám.

Totéž platí pro CSS: celé CSS stránky je jeden vložený blok `<style id="proposal">` a jeho zdroj je
ve složce `partials/theme/` (od 23. 11.; složka `css/` už neexistuje a stránka nesmí odkazovat na žádný stylesheet).

Po změně hlavičky, patičky, souboru v `partials/theme/` nebo jakéhokoli souboru v `js/` spusťte z kořene projektu:

```bash
python3 scripts/sync.py
```

Skript:

1. vloží hlavičku do každé stránky v kořeni projektu a označí aktivní položku menu (Ceník, Blog, Kvízy),
2. vloží patičku do každé stránky v kořeni projektu,
3. ke každému odkazu na místní `.js` (i v `rezervace/`) dopíše `?v=` podle obsahu souboru. Změněný soubor
   tak dostane novou adresu a prohlížeče mohou JS držet rok (`.htaccess`), aniž by viděly starou verzi.
   `?v=` nikdy neupravujte ručně.
4. složí CSS každé stránky v tomto pořadí: `base.css` (+ část `base-*` stránky) + `core.css` + část dané stránky
   + `tail.css`. Která část patří ke které stránce, určuje funkce `theme_for()` ve skriptu:

   | Stránky | Části (mezi `base.css` … `core.css` … `tail.css`) |
   |---|---|
   | `index.html` | `home-first.css` úplně na začátku, pak `home.css` |
   | `cenik.html` | `base-cenik.css`, `cenik.css` |
   | `blog.html`, `kviz.html` | `base-blog-list.css`, `subpage.css` + `blog-list.css` |
   | `blog-*.html` | `base-blog-list.css` + `base-blog-post.css`, `subpage.css` + `blog-post.css` |
   | `quiz-*.html` | `base-quiz.css`, `subpage.css` + `quiz.css` |
   | `404.html` | `subpage.css` + `404.css` |
   | `obchodni-podminky.html`, `ochrana-osobnich-udaju.html`, `rezervace.html` | `subpage.css` + `legal.css` |

   CSS zůstává vložené přímo ve stránce (žádný další požadavek, nic neblokuje vykreslení). Nová stránka
   s tímto blokem potřebuje řádek v `theme_for()`, jinak skript skončí chybou. Stránky v `rezervace/` mají
   vlastní CSS; hlavička na stránce zrušení si bere CSS z `cenik.html` (`rezervace/site-header.js`).

Kontrola bez zápisu (spouští ji i `python3 scripts/check-indexing.py` před každým pushem):

```bash
python3 scripts/sync.py --check
```

Web zůstává statický: stránka nestahuje partial ani skript. Složky `partials`, `scripts` a `docs` se nasazují
s repozitářem, ale `.htaccess` je skrývá (404).

Písma (`fonts/`) se také ukládají na rok, ale bez `?v=`: nové písmo vždy jako nový název souboru.
