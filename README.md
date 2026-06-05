# Purchases IO (Import/Export)

Self-contained Import/Export for **purchase_entries** using PhpSpreadsheet.
Works with *reference.xlsx* layout. Scans for the header row, so extra rows/merged cells above are OK.

## Install
```bash
cd /MaorinBuilders/purchases-io
composer install
```
> Requires PHP extensions: `mbstring`, `xml`, `zip`, `gd` (or `imagick`) for PhpSpreadsheet.

## Wire into your app
Ensure these exist one level up:
- `../config.php` provides `$pdo` (PDO, ERRMODE_EXCEPTION) and session bootstrap
- `../helpers.php` provides `redirect_if_not_logged_in()`, `csrf_token()`, `csrf_verify()`

Routes:
- Admin-only journal import UI: `/MaorinBuilders/purchases-io/import.php`
- Admin-only journal export XLSX: `/MaorinBuilders/purchases-io/export.php?date_from=2025-06-01&date_to=2025-06-30&supplier=Acme`
- Admin-only SQL backup export: `/MaorinBuilders/database_export.php`

## Mapping
See `mapping.php`. Add aliases if headers vary.

## Notes
- **Dry Run** previews without inserting.
- Export writes a flat **Purchase Journal** sheet, matching reference headers.
- Totals: uses your `calc_purchase()` if available; otherwise computes `vatable + non_vat + freight_handling`.
- Journal export/import and SQL backup are restricted to `admin` accounts.
