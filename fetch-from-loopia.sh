#!/bin/bash
# Laddar ner alla filer från Loopia FTP till den här mappen.
# Kör en gång för att fylla repot med källkoden.
# Använd sedan Git + GitHub Actions för fortsatt deploy.

FTP_HOST="ftpcluster.loopia.se"
FTP_USER="ninakp"
REMOTE_DIR="/public_html/"
LOCAL_DIR="$(dirname "$0")"  # Samma mapp som scriptet

echo "Ansluter till $FTP_HOST..."
echo "Hämtar filer från $REMOTE_DIR → $LOCAL_DIR"
echo ""

read -sp "FTP-lösenord: " FTP_PASS
echo ""

lftp -c "
open ftp://$FTP_USER:$FTP_PASS@$FTP_HOST
mirror \
  --continue \
  --parallel=4 \
  --exclude-glob .git/ \
  --exclude-glob wp-content/uploads/ \
  --exclude-glob wp-content/cache/ \
  --exclude-glob wp-content/upgrade/ \
  --exclude-glob wp-content/backup*/ \
  --exclude '\.log$' \
  --verbose \
  $REMOTE_DIR $LOCAL_DIR
bye
"

echo ""
echo "Klar! Filer hämtade till $LOCAL_DIR"
echo "Kör nu: git add . && git commit -m 'Initial import från Loopia' && git push"
