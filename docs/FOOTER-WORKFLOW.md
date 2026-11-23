# Hlavička, patička a verze CSS/JS

Hlavička (`partials/header.html`) a patička (`partials/footer.html`) jsou zdrojové fragmenty, nikoli samostatné
stránky. Neupravujte je přímo v jednotlivých HTML souborech: skript je do každé stránky vkládá sám.

Po změně hlavičky, patičky nebo jakéhokoli souboru v `css/` či `js/` spusťte z kořene projektu:

```bash
python3 scripts/sync.py
```

Skript:

1. vloží hlavičku do každé stránky v kořeni projektu a označí aktivní položku menu (Ceník, Blog, Kvízy),
2. vloží patičku do každé stránky v kořeni projektu,
3. ke každému odkazu na místní `.css` a `.js` (i v `rezervace/`) dopíše `?v=` podle obsahu souboru. Změněný soubor
   tak dostane novou adresu a prohlížeče mohou CSS a JS držet rok (`.htaccess`), aniž by viděly starou verzi.
   `?v=` nikdy neupravujte ručně.

Kontrola bez zápisu (spouští ji i `python3 scripts/check-indexing.py` před každým pushem):

```bash
python3 scripts/sync.py --check
```

Web zůstává statický: stránka nestahuje partial ani skript. Složky `partials`, `scripts` a `docs` se nasazují
s repozitářem, ale `.htaccess` je skrývá (404).

Písma (`fonts/`) se také ukládají na rok, ale bez `?v=`: nové písmo vždy jako nový název souboru.
