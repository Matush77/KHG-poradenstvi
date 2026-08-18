# Jednotný footer

Soubor `partials/footer.html` je zdrojový fragment, nikoli samostatná webová stránka. Neupravujte footer přímo v jednotlivých HTML souborech.

Po změně footeru spusťte z kořene projektu:

```powershell
.\scripts\sync-footer.ps1
```

Kontrola, zda mají všechny stránky aktuální footer:

```powershell
.\scripts\sync-footer.ps1 -Check
```

Skript vkládá kompletní footer přímo do všech HTML souborů v kořeni projektu. Web proto zůstává statický a při načítání stránky nestahuje partial ani synchronizační skript.

Na hosting nahrajte změněné HTML soubory. Složky `partials`, `scripts` a `docs` jsou pouze vývojové a na hosting se nahrávat nemusí.
