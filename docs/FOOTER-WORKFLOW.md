# Sdílený footer

Footer všech veřejných HTML stránek má jediný zdroj:

`partials/footer.html`

Po změně tohoto souboru spusťte z kořene projektu:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\sync-footer.ps1
```

Kontrola, že jsou všechny stránky synchronizované:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\sync-footer.ps1 -Check
```

Skript upravuje pouze jeden element `<footer>…</footer>` v každém HTML souboru přímo v kořeni projektu. Pokud footer chybí nebo je v souboru vícekrát, skript skončí chybou bez pokračování.

## Publikování

Footer zůstává vložený přímo v každé výsledné HTML stránce. Díky tomu nezávisí na JavaScriptu a nezhoršuje SEO, přístupnost ani vykreslení stránky.

Po změně footeru nahrajte na hosting všechny HTML soubory upravené synchronizačním skriptem. Složky `partials` a `scripts` nejsou pro běh webu na hostingu potřeba; slouží pouze pro údržbu zdrojového projektu.
