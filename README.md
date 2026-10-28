# LU3G Mobile Menu

Widget Elementor con menu hamburger per mobile: voci dal menu di WordPress con sottomenu su più livelli, voci extra (link, contatti, social, CTA), animazioni configurabili, accessibile, compatibile con WPML e Polylang.

Plugin WordPress di LU3G Agenzia Web.

## Installazione su un sito
1. Scarica `lu3g-mobile-menu.zip` dall'ultima [release](../../releases/latest).
2. WordPress → Plugin → Aggiungi nuovo → Carica plugin → Attiva.
3. Gli aggiornamenti arrivano da soli dalle release di questo repository (Bacheca → Aggiornamenti, entro 6 ore;
   subito con "Controlla di nuovo" o con il link "Controlla aggiornamenti" nella pagina Plugin).
   Facoltativo: `define( 'LU3G_GITHUB_TOKEN', 'github_pat_...' );` in `wp-config.php` alza il limite di richieste all'API GitHub.

I siti con una versione precedente alla 1.0.0 (senza aggiornamenti automatici) vanno aggiornati a mano una volta:
Plugin → Aggiungi nuovo → Carica plugin → "Sostituisci la versione attuale con quella caricata".

## Pubblicare una nuova versione
1. Modifica il codice.
2. Aumenta la versione nell'intestazione di `lu3g-mobile-menu.php` (`Version:`), nella costante della versione e in `readme.txt` (Stable tag).
3. Aggiungi la sezione della versione in `CHANGELOG.md` (diventa la nota della release).
4. Commit, poi tag e invio: `git tag v1.0.0 && git push && git push --tags`
5. L'automazione `.github/workflows/release.yml` controlla la versione, crea lo zip e pubblica la release.

Pacchetto in locale: `sh bin/build.sh` → `dist/lu3g-mobile-menu.zip`.
