# Menu LU3G

Widget Elementor con menu hamburger per mobile: voci dal menu di WordPress con sottomenu su più livelli, voci extra (link, contatti, social, CTA), animazioni configurabili, accessibile, compatibile con WPML e Polylang.

Plugin WordPress di LU3G Agenzia Web.

## Installazione su un sito
1. Scarica `lu3g-mobile-menu.zip` dall'ultima [release](../../releases/latest).
2. WordPress → Plugin → Aggiungi nuovo → Carica plugin → Attiva.
3. Gli aggiornamenti arrivano da soli dalle release di questo repository (Bacheca → Aggiornamenti, entro 6 ore;
   subito con "Controlla di nuovo" o con il link "Controlla aggiornamenti" nella pagina Plugin).
   Facoltativo: `define( 'LU3G_GITHUB_TOKEN', 'github_pat_...' );` in `wp-config.php` alza il limite di richieste all'API GitHub.

## Uso
In Elementor cerca **Menu LU3G** (categoria LU3G) e trascinalo nell'intestazione.
- **Contenuto**: menu di WordPress o posizione del tema, sottomenu a espansione o a slide, voci extra (link, telefono,
  email, WhatsApp, social, testo, CTA), icona hamburger (linee animate o icona/SVG), logo, animazioni, breakpoint.
- **Desktop** (Contenuto → Desktop → Su desktop): nascosto, barra orizzontale con tendine, oppure hamburger anche su desktop.
  Mega menu voce per voce in Aspetto → Menu (riquadro "Mega menu LU3G"): colonne dalle sotto-voci o un template Elementor.
- **Stile**: hamburger, pulsante chiudi, logo, pannello e overlay, voci di menu (anche posizione verticale),
  sottomenu, Indietro e titolo (slide), voci extra; barra desktop, tendine, mega menu, voci extra nella barra.

Nomi: il plugin si chiama "Menu LU3G" e il repository `lu3g-menu`; la cartella del plugin (`lu3g-mobile-menu`) e il
nome interno del widget restano quelli della prima versione, così i siti che lo usano si aggiornano senza reinstallarlo
e i widget già inseriti nelle pagine restano validi.

## Pubblicare una nuova versione
1. Modifica il codice.
2. Aumenta la versione nell'intestazione di `lu3g-mobile-menu.php` (`Version:`), nella costante `LU3G_MM_VERSION` e in `readme.txt` (Stable tag).
3. Aggiungi la sezione della versione in `CHANGELOG.md` (diventa la nota della release).
4. Commit, poi tag e invio: `git tag vX.Y.Z && git push && git push --tags`
5. L'automazione `.github/workflows/release.yml` controlla la versione, crea lo zip e pubblica la release.

Pacchetto in locale: `sh bin/build.sh` → `dist/lu3g-mobile-menu.zip`.
