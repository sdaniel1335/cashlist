# Cashlist 0.1.0

Egyszerű bevétel- és kiadáslista VC Framework alapon, a Lifelist, Todolist és Playlist mellé.

- Közös `users` tábla és belépési munkamenet (`auth_user_id`, `auth_username`).
- B/P/W kategória minden pénzmozgáson, akár ugyanazon bankszámlán belül is.
- Tetszőleges számú bankszámla, pénztárca és boríték, saját devizával és KP/nem KP típussal.
- Számlánkénti egyenleg, teljes KP/számla összesítés és külön B/P/W bontás.
- Bevétel, kiadás, nyitóegyenleg és számlák vagy kategóriák közötti átvezetés.
- Eredeti devizás összeg és a számlán ténylegesen terhelt/jóváírt összeg külön rögzíthető.
- Dátumválasztós egyenleg; alapból a mai nap vége. Jövőbeli tételek is felvehetők.
- Szerkesztés, törlés, archiválás, szűrés, lapozás és mobilnézet.

## Telepítés

1. Tedd a projektet a többi app mellé, például `/cashlist` alá. Apache alatt a mod_rewrite és az `.htaccess` szabályok engedélyezése szükséges. A `public_html/.htaccess` az alkalmazás része; a `private_html` és más belső mappák nem lehetnek közvetlenül elérhetők.
2. Másold a `private_html/app/config/db-sample.php` fájlt `db.php` névre. Állítsd be **ugyanazt az adatbázist és DB_PREFIX értéket**, mint a többi appnál. A PHP MySQLi és a biztonságos véletlenszámokhoz OpenSSL kiterjesztés szükséges PHP 5.3 alatt.
3. Importáld az `install.sql` fájlt. A meglévő `users` rekordokat nem módosítja, nem hoz létre alapértelmezett adminfiókot. Ha van DB_PREFIX, importálás előtt minden táblanévhez és REFERENCES hivatkozáshoz add hozzá, a `users` táblánál is. A meglévő `users.id` oszlop azonos típusú, `INT UNSIGNED` legyen; a csatolt appok ezt használják.
4. Használd a meglévő felhasználódat. Friss közös telepítésnél először a felhasználókezelő appban hozz létre fiókot. Minden app azonos domainen, azonos PHP session névvel, session tárolóval és `/` cookie útvonallal fusson. Eltérő aldomainek és külön session tárolók nem biztosítanak automatikus közös belépést.
5. A Beállításokban válaszd ki a fődevizát (alapból HUF), majd vedd fel a számlákat. Éles környezetben az alkalmazáskonfigurációban állítsd az APP_ENV értékét `prod` értékre, és használj HTTPS-t.

A framework és a közös topbar változatlan maradt. PHP 5.3–8.x kompatibilis alkalmazáskód; Composer és külső frontendfüggőség nélkül.

## Első lépések és számítás

Először hozd létre például az OTP (HUF, nem KP), Pénztárca (HUF, KP) és Boríték (HUF, KP) számlákat. Mindegyik kezdőösszegét **Nyitóegyenleg** tétellel vedd fel a megfelelő B/P/W kategóriára és kezdőnapra. Negatív nyitóegyenleg is megadható. A kezdőnap előtti egyenleg nulla; a nyitóegyenleg nem korábbi teljes történet.

20 USD-s vásárlás HUF-os OTP-számláról: Kiadás → OTP → összeg 7 400 HUF, az eredeti devizás résznél 20 USD. Az OTP egyenlege 7 400 forinttal csökken.

USD-s számlánál a számlaösszeg mellett a fődevizás értéket is add meg. A fődevizás összesítés a rögzített értékeket adja össze, **nem az aktuális piaci árfolyamon értékeli újra a devizaállományt**. A fődeviza az első tételtől rögzített. A számla devizája és KP típusa létrehozás után nem változtatható; ez megőrzi a történeti bontás értelmét.

Készpénzfelvétel: Átvezetés OTP → Pénztárca. Az összes pénz nem változik, csak a helye. B→P vagy más kategóriamozgatás ugyanazon a számlán is lehetséges. Eltérő számladeviza esetén a forrás- és célösszeget is meg kell adni; a fődevizás érték mindkét oldalon azonos. Banki díj külön kiadás.

A nyitóegyenlegen kívül pozitív összegeket adj meg, az előjelet a típus határozza meg. A felület két tizedesjegyet kezel, egy tételben legfeljebb 999 999 999,99 egységet. Az egyenleg lehet negatív. A tranzakciólista minden dátumot mutat, a kiválasztott nap utáni tételek egyenlegen kívül jelölést kapnak. A jövőbeli tétel terv; elérve a dátumát automatikusan beleszámít, ezért elmaradt vagy megváltozott pénzmozgásnál javítsd/töröld.

Az archiválás csak elrejti a számlát az új tételek választójából. Az előzmények és az egyenleg megmaradnak. Szerkesztés és törlés az egyenlegeket is újraszámolja. Ugyanazon űrlap ismételt beküldése nem hoz létre még egy tranzakciót; az elavult szerkesztést az app jelzi.

## Ellenőrzés

`php tests/money.php` — pénzösszegek, tizedesvessző, negatív és nagy összegek, dátumok és hibás bemenetek regressziós ellenőrzése. PHP 5.3.29 és 8.4.27 alatt ellenőrizve.

Külön, ideiglenes MariaDB-adatbázison ellenőrizve: közös formátumú belépés, számlafelvétel, devizás tételek, jövőbeli egyenlegek, átvezetések, B/P átcsoportosítás, jogosultságok, sikertelen mentés visszagörgetése, dupla beküldés, szerkesztési ütközés, törlés és CSRF. Böngészős ellenőrzés asztali és 390 px széles mobilnézeten.

A teljes integrációs ellenőrzés megismételhető a `node tests/integration.js` paranccsal. Playwright és egy **friss, kizárólag tesztre létrehozott**, `_test` végű adatbázis szükséges, az `install.sql` tábláival, prefix nélkül. A teszt két fiókot és mintatételeket hoz létre; üres `users` táblát követel meg. A tesztelt PHP-webszerver ugyanerre az izolált adatbázisra mutasson. Éles adatbázison ne futtasd.

Környezeti változók: `CASH_TEST_MYSQL` (MariaDB/MySQL kliens elérési útja), `CASH_TEST_PHP` (PHP CLI), `CASH_TEST_DATABASE`, `CASH_TEST_PASSWORD`; opcionálisan `CASH_TEST_USER` (root), `CASH_TEST_PORT` (33369), `CASH_TEST_URL` (http://127.0.0.1:8096), `PLAYWRIGHT_MODULE` (playwright modul elérési útja), `CASH_TEST_CHROME` (telepített Chrome futtatható fájlja). A `.cashlist-test` mappába kerülnek az előnézetek.
