# mool.se

WordPress-sajt för mool.se, hostad på Loopia.

## Teknikstack

| Komponent | Detalj |
|---|---|
| CMS | WordPress |
| Tema | Twenty Twenty-Four + child theme |
| Sidbyggare | Elementor |
| E-handel | WooCommerce + WooCommerce Subscriptions |
| Checkout | Elementor Checkout |
| Tack-sida | Anpassad (ny/återkommande kund) |
| Bokning | Amelia Lite (utan kundpanel) |
| Hosting | Loopia |
| Deploy | GitHub Actions → FTP |

## Deploy

Varje push till `main`-branchen triggar automatisk deploy via FTP till Loopia.

### GitHub Secrets som krävs

Gå till repots **Settings → Secrets and variables → Actions** och lägg till:

| Secret | Värde |
|---|---|
| `FTP_USERNAME` | `ninakp` |
| `FTP_PASSWORD` | *(Loopia FTP-lösenordet)* |

### FTP-inställningar

- **Server:** `ftpcluster.loopia.se`
- **Målmapp:** `/public_html/`

### Vad deployas INTE

Följande exkluderas alltid (se `.ftpignore`):

- `wp-content/uploads/` — befintliga uppladdade filer skrivs aldrig över
- `wp-content/cache/` — cache hanteras på servern
- `.env`-filer — känslig konfiguration lagras inte i repot
- `.github/`, `.git/` — CI-filer deployas inte

## Lokal utveckling

Kopiera filer till din lokala WordPress-installation och jobba mot en lokal databas.
Pusha till `main` när ändringar är redo att gå live.

## Viktigt

- `wp-config.php` på Loopia-servern ska **inte** skrivas över om den innehåller produktionsdatabasuppgifter. Lägg i så fall till `wp-config.php` i `.ftpignore`.
- Testa gärna workflowen med en liten ändring första gången för att verifiera att deploy fungerar.
