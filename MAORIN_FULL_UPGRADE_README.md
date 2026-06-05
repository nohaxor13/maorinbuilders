# Maorin Builders Full Production Upgrade

This package is your current website plus the applied all-phase upgrade. It keeps existing files and adds production modules for projects, estimates, proposals, finance, HR, attendance, inventory, documents, plans, and reports.

## Install
1. Backup your current folder and database.
2. Copy these files into `localhost/maorinbuilders/`.
3. Import `sql/maorinbuilders_full_upgrade.sql` in phpMyAdmin.
4. Login as admin.
5. Open `http://localhost/maorinbuilders/modules/projects/index.php`.

The migration is additive and uses `CREATE TABLE IF NOT EXISTS`, so it does not drop your old purchase journal, admin, public pages, client portal, or database tools.
