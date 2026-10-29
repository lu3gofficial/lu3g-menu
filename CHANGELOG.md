# Registro delle modifiche

## 1.2.1
- Corretto: con Elementor Pro l'editor restava fermo su "Caricamento" in tutte le pagine e i template quando il widget
  era nell'header (Theme Builder). Il contenitore del pannello mobile creato dal JavaScript aveva la classe "elementor":
  Elementor lo contava come documento senza id ed Elementor Pro si interrompeva ("`id` and `element` are required").
  Ora ha solo la classe "elementor-<id>", che basta agli stili del widget.

## 1.2.0
- Nuovo nome visibile: "Menu LU3G" (plugin e widget). Repository rinominato in lu3gofficial/lu3g-menu; cartella del
  plugin e nome interno del widget invariati, quindi aggiornamento normale e widget già inseriti ancora validi.
- Versione desktop (Contenuto → Desktop → Su desktop): "Nascosto" (predefinito, come prima), "Barra orizzontale con
  tendine" o "Hamburger anche su desktop"; sotto il breakpoint resta l'hamburger.
- Barra: tendine su più livelli al passaggio del mouse (con ritardo di chiusura) o al click, primo tocco che apre
  la tendina sui touch, ESC, tastiera, tendine laterali che non escono dallo schermo; 6 animazioni delle tendine;
  indicatore freccia, triangolo, più o nessuno.
- Effetti al passaggio sulle voci (anche per la pagina corrente): linea sotto, sopra, doppia, cornice, sfondo, testo,
  ognuno con le sue animazioni.
- Voci extra nella barra (CTA, contatti, social, link) con "Mostra in": pannello e barra, solo pannello, solo barra.
- Mega menu, voce per voce in Aspetto → Menu: colonne dalle sotto-voci oppure un template Elementor salvato
  (tradotto con WPML/Polylang); largo come il contenitore della barra, come lo schermo o a larghezza scelta.
- Nuove sezioni di stile: Barra desktop, Tendine desktop, Mega menu, Voci extra nella barra.

## 1.1.0
- Apertura del pannello, 7 nuove: slide dal basso (bottom sheet, angoli arrotondati), rotazione 3D di lato,
  riquadro dall'angolo (staccato dai bordi), zoom a schermo intero, cerchio che si allarga dall'icona hamburger,
  tenda dall'alto, tenda da sinistra. Nuovi controlli: altezza massima, distanza dai bordi del riquadro, raggio del bordo
  del pannello; "Lato del pannello" vale anche per rotazione 3D e riquadro.
- Hamburger, 6 nuove: X a vortice, X elastica, + che ruota in X, X dal basso, linee asimmetriche → X
  (si allungano anche in hover), freccia a destra.
- Ingresso delle voci, 7 nuovi: dall'alto, sfocatura, rimbalzo, rotazione 3D, rotazione, inclinazione,
  rivelazione dal basso.
- Icona hamburger personalizzata, 3 nuovi passaggi tra le icone: zoom, rotazione 3D, scorrimento verticale.

## 1.0.2
- Sottomenu a slide (Contenuto → Menu → Apertura dei sottomenu): ogni sottomenu entra da destra al posto del livello
  precedente, con pulsante Indietro (testo e icona personalizzabili) e titolo della voce genitore; su più livelli,
  con l'altezza che si adatta; freccia destra entra e freccia sinistra torna indietro da tastiera; alla chiusura del
  pannello si riparte dal primo livello. Stile dedicato in Stile → Indietro e titolo (slide).
- Posizione verticale delle voci (Stile → Voci di menu): in alto, al centro, in basso, più uno spostamento fine in px o vh.
  Sostituisce l'opzione "Contenuto centrato in verticale" dell'overlay a schermo intero (che resta centrato di default).

## 1.0.1
- Icona hamburger personalizzata: icona della libreria o SVG caricato, con icona diversa da aperto e passaggio
  con rotazione o dissolvenza; opzione per colorare l'SVG con i colori dello stile (anche per il pulsante chiudi).
- Logo nel pannello (logo del sito o immagine caricata), con link alla home nella lingua corrente.
- Easing: scegliendo "Personalizzato" la tendina restava nascosta e non si poteva più tornare ai valori della lista.
- Focus: niente più contorno (spesso blu, dal tema) sul pulsante chiudi e sulle voci dopo un tocco o un click;
  il contorno compare solo navigando con la tastiera, con colore impostabile (Stile → Pannello).

## 1.0.0
- Prima versione: widget Elementor "Menu mobile LU3G" con voci dal menu di WordPress (sottomenu su più livelli),
  voci extra (link, telefono, email, WhatsApp, social, testo, CTA), 5 animazioni del pannello, 6 animazioni
  dell'hamburger, ingresso delle voci in sequenza, breakpoint, focus trap, compatibilità WPML e Polylang.
