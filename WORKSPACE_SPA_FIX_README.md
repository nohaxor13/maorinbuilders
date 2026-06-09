# Maorin Builders Workspace SPA UI Fix

This package updates the previous all-phases workspace so it matches the existing Maorin Builders website instead of opening a separate dark-sidebar workspace.

## What changed

- Added `workspace.php` as the single-page workspace shell.
- Updated `templates/header.php` so **Workspace** is now a dropdown in the existing top navigation.
- Added `modules/workspace_api.php` for SPA content loading and CRUD actions.
- Added `assets/js/workspace-spa.js` for hash-based SPA navigation, search, refresh, modal forms, and delete actions.
- Updated `assets/css/app.css` with matching Bootstrap/card styles instead of a separate sidebar UI.
- Kept the older module pages in place for compatibility, but the intended route is now `workspace.php`.

## Open

```text
http://localhost/maorinbuilders/workspace.php
```

Workspace sections can be opened directly:

```text
http://localhost/maorinbuilders/workspace.php#projects
http://localhost/maorinbuilders/workspace.php#estimates
http://localhost/maorinbuilders/workspace.php#proposals
http://localhost/maorinbuilders/workspace.php#expenses
http://localhost/maorinbuilders/workspace.php#inventory
```

## Database

Use the existing upgrade SQL if you have not imported it yet:

```text
sql/maorinbuilders_full_upgrade.sql
```

The PHP also calls `ensure_maorin_workspace_tables($pdo)` as a safety guard.
