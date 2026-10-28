#!/bin/sh
# Crea dist/lu3g-mobile-menu.zip (cartella lu3g-mobile-menu/ all'interno), il pacchetto che WordPress installa e aggiorna.
# Esclude i file che servono solo allo sviluppo (git, script, automazioni).
set -e
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist/lu3g-mobile-menu
cp -R lu3g-mobile-menu.php readme.txt assets includes widgets dist/lu3g-mobile-menu/
(cd dist && zip -rq lu3g-mobile-menu.zip lu3g-mobile-menu -x '*.DS_Store')
rm -rf dist/lu3g-mobile-menu
echo "dist/lu3g-mobile-menu.zip"
