-- ============================================================
-- Anteckningar: exempeldata (valfritt)
-- Kör i phpMyAdmin (fliken SQL) eller: mysql -u ANVÄNDARE -p DATABAS < exempeldata.sql
-- ============================================================
-- Kräver att schema.sql redan är körd. Datumen räknas från när filen körs.
-- @uid = användar-ID som datan hamnar på. Inbyggd inloggning använder 1.
-- OBS: tar först bort ALL befintlig data för den användaren.

SET NAMES utf8mb4;
SET @uid = 1;

DELETE FROM Anteckningar WHERE UserID = @uid;
DELETE FROM AnteckningTaggar WHERE UserID = @uid;

INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'bygg'); SET @tag0 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'böcker'); SET @tag1 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'dator'); SET @tag2 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'demo'); SET @tag3 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'förening'); SET @tag4 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'jul'); SET @tag5 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'möten'); SET @tag6 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'projekt'); SET @tag7 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'recept'); SET @tag8 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'resor'); SET @tag9 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'sommar'); SET @tag10 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'tips'); SET @tag11 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (@uid, 'vardag'); SET @tag12 = LAST_INSERT_ID();

INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Välkommen till Anteckningar', 'Det här är en **demo** – testa fritt! Allt du gör syns bara för dig.

## Det här kan du göra
- Skapa en ny anteckning med **+ Ny anteckning**
- Ge varje anteckning flera **taggar** och klicka på en tagg för att filtrera
- **Sök** i namn, text och taggar samtidigt
- **Fäst** viktiga anteckningar överst med 📌
- **Arkivera** det du inte behöver just nu – radera går bara från arkivet
- **Exportera** allt som en textfil

## Formatering
Växla mellan *Skriv* och *Visa*. I Visa blir länkar klickbara, t.ex. https://sv.wikipedia.org

- [x] Läs den här anteckningen
- [ ] Skapa en egen anteckning
- [ ] Prova att söka på "recept"

Tips: **Ctrl+S** (eller Cmd+S) sparar.', 1, 0, 1, DATE_SUB(NOW(), INTERVAL 43200 MINUTE), DATE_SUB(NOW(), INTERVAL 60 MINUTE)); SET @n0 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n0, @tag3);
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n0, @tag11);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Inköp till altanbygget', '## Material
- [x] Trall 28x120, 40 m
- [x] Reglar 45x145, 12 st
- [ ] Trallskruv rostfri, 2 paket
- [ ] Plintar, 9 st
- [ ] Markduk

## Att komma ihåg
Fråga bygghandeln om **hemleverans** – annars behövs släpvagn.
Lämna gamla virket på återvinningen *innan* nya levereras.', 0, 0, 1, DATE_SUB(NOW(), INTERVAL 30240 MINUTE), DATE_SUB(NOW(), INTERVAL 3010 MINUTE)); SET @n1 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n1, @tag7);
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n1, @tag0);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Pasta med citron och parmesan', 'Snabb vardagsrätt, ca 20 minuter. 4 portioner.

## Ingredienser
- 400 g pasta
- 1 citron (skal och saft)
- 50 g smör
- 1 dl riven parmesan
- Svartpeppar, salt

## Gör så här
1. Koka pastan, spara en kopp pastavatten.
2. Smält smöret, riv i citronskalet.
3. Blanda pasta, smör, citronsaft och parmesan. Späd med pastavatten tills det blir krämigt.
4. Toppa med mycket svartpeppar.

> Gott med en näve ruccola eller rostade pinjenötter.', 0, 0, 1, DATE_SUB(NOW(), INTERVAL 64800 MINUTE), DATE_SUB(NOW(), INTERVAL 13360 MINUTE)); SET @n2 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n2, @tag8);
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n2, @tag12);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Boktips att läsa', '- [x] Utvandrarna – Vilhelm Moberg
- [ ] Röda rummet – August Strindberg
- [ ] Mästaren och Margarita – Michail Bulgakov
- [ ] Hundraåringen som klev ut genom fönstret och försvann – Jonas Jonasson

Tips från bokcirkeln: läs *Mästaren och Margarita* långsamt.', 0, 0, 1, DATE_SUB(NOW(), INTERVAL 86400 MINUTE), DATE_SUB(NOW(), INTERVAL 20160 MINUTE)); SET @n3 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n3, @tag1);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Styrelsemöte – anteckningar', '**Närvarande:** Anna, Bertil, Cecilia, jag

## Beslut
1. Vårstädning lördag vecka 17 kl. 10.
2. Ny offert på belysning i trapphuset tas in.
3. Medlemsavgiften oförändrad.

## Att göra
- [ ] Jag: maila ut kallelse till vårstädningen
- [ ] Bertil: kontakta elfirma
- [x] Cecilia: uppdatera anslagstavlan', 0, 0, 1, DATE_SUB(NOW(), INTERVAL 17280 MINUTE), DATE_SUB(NOW(), INTERVAL 17080 MINUTE)); SET @n4 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n4, @tag4);
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n4, @tag6);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Bra kortkommandon', '## Webbläsaren
- `Ctrl + L` – gå till adressfältet
- `Ctrl + Shift + T` – öppna senast stängda fliken
- `Ctrl + F` – sök på sidan

## Här i Anteckningar
- `Ctrl + S` – spara
- Enter eller komma – lägg till tagg', 0, 0, 1, DATE_SUB(NOW(), INTERVAL 129600 MINUTE), DATE_SUB(NOW(), INTERVAL 43200 MINUTE)); SET @n5 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n5, @tag11);
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n5, @tag2);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Resplan Gotland', '## Plan
- Färja från Nynäshamn fredag morgon
- 3 nätter i Visby, 2 nätter på Fårö

## Vill se
- Ringmuren och Almedalen
- Raukarna på Fårö
- Lummelundagrottan

Mer läsning: https://sv.wikipedia.org/wiki/Gotland', 1, 0, 1, DATE_SUB(NOW(), INTERVAL 11520 MINUTE), DATE_SUB(NOW(), INTERVAL 4370 MINUTE)); SET @n6 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n6, @tag9);
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n6, @tag10);
INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad) VALUES (@uid, 'Julklappar', 'Arkiverad sedan förra julen – öppna arkivet för att se den.

- [x] Mamma – bok
- [x] Pappa – kaffebryggare
- [x] Syster – brädspel', 0, 1, 1, DATE_SUB(NOW(), INTERVAL 172800 MINUTE), DATE_SUB(NOW(), INTERVAL 115200 MINUTE)); SET @n7 = LAST_INSERT_ID();
INSERT INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (@n7, @tag5);
