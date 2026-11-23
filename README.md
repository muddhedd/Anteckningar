# Anteckningar

*[English version](README.en.md)*

Ett litet, självständigt anteckningsverktyg i PHP och MySQL/MariaDB. Det fungerar lika bra på mobilen som på datorn, kräver inga ramverk och ingen byggprocess. Ladda upp filerna och kör.

## Funktioner

- **En lista** med alla anteckningar. Fästa anteckningar ligger överst, sedan vald sortering: senast redigerad, skapad eller namn.
- **Taggar**: flera per anteckning. Förslag på taggar du redan använt visas medan du skriver, och "Recept" och "recept" räknas som samma tagg.
- **Taggmoln** med antal per tagg. Klicka för att filtrera.
- **Sökning** i namn, text och taggar. Den går att kombinera med en tagg.
- **Markdown**: rubriker, fet och kursiv stil, listor, att göra-rutor (`- [ ]`), citat, kod och länkar. Fliken *Visa* gör webbadresser klickbara.
- **Datum** för skapad och senast redigerad sätts automatiskt.
- **Arkiv**: arkiverade anteckningar syns inte i listan men finns kvar. Radering går bara från arkivet och måste bekräftas.
- **Skydd mot förlorad text**:
  - Sidan varnar om du försöker lämna en anteckning med osparade ändringar.
  - Har anteckningen sparats på en annan enhet efter att du öppnade den får du välja mellan att skriva över, spara som kopia eller hämta den andra versionen.
- **Egen adress per anteckning** (`anteckningar.php?id=12`), så att du kan bokmärka den.
- **Export** av alla anteckningar som en textfil.
- **Ctrl+S / Cmd+S** sparar.

## Krav

- PHP 7.4 eller senare (8.x rekommenderas) med `mysqli` (mysqlnd) och `mbstring`. Båda finns hos nästan alla webbhotell.
- MySQL 5.7+ eller MariaDB 10.3+
- HTTPS rekommenderas starkt, eftersom inloggningen annars skickas okrypterad.

## Installation

1. **Skapa en databas**, eller använd en befintlig. Kör `sql/schema.sql` i den, till exempel i phpMyAdmin under fliken SQL.
2. **Exempeldata (valfritt):** kör `sql/exempeldata.sql` för att få några anteckningar att titta på. Den lägger datan på användar-ID 1, som är det inbyggda inloggningsläget använder.
3. **Kopiera** `config.example.php` till `config.php` och fyll i databasuppgifterna och `LOGIN_USER`.
4. **Ladda upp** mappen till din webbserver.
5. **Öppna** `anteckningar.php` i webbläsaren. Första gången visas en guide:
   1. Välj ett lösenord, så skapas en hash.
   2. Klistra in raden i `config.php` som `LOGIN_PASSWORD_HASH` och ladda om sidan.
   3. Logga in.

`config.php` finns i `.gitignore`, så dina lösenord hamnar aldrig i git.

## Inloggningslägen

Inloggningsläget ställs in med `AUTH_MODE` i `config.php`:

| Läge | Beskrivning |
|---|---|
| `login` | Inbyggd inloggning med ett användarnamn och lösenord. Standardläget. |
| `demo` | Ingen inloggning. Varje besökare får en egen sandlåda med exempeldata som raderas efter `DEMO_TTL_HOURS` timmar. Kräver `sql/demo.sql` i stället för `exempeldata.sql`. Passar för att visa verktyget publikt. |


## Filer

| Fil | Innehåll |
|---|---|
| `anteckningar.php` | Hela verktyget: listan, redigeraren, sparande och export |
| `common.php` | Gemensamt nav: databas, inloggning, CSRF-skydd, meny och demoläge |
| `config.example.php` | Mall för inställningarna |
| `demo.php` | Kopierar exempeldata till en besökares sandlåda (bara demoläget) |
| `minsida.css` | Utseendet |
| `sql/` | `schema.sql` (tabeller), `exempeldata.sql` och `demo.sql` |

## Säkerhet

- Alla databasfrågor använder prepared statements.
- Alla ändringar skyddas med en CSRF-token.
- Markdown-visningen escapar all text och tillåter bara `http(s)`- och `mailto`-länkar.
- Lösenordet lagras som en `password_hash` och aldrig i klartext. Misslyckade inloggningar fördröjs två sekunder.

## Licens

MIT – se [LICENSE](LICENSE).
