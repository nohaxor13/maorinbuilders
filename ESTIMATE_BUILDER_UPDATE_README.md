# Maorin Builders Estimate Builder Update

This package keeps the existing Maorin Builders website and upgrades the Workspace estimate module into a professional contractor estimate builder.

## Main changes

- Workspace Estimates now open a full professional estimate builder modal.
- Added project details, status dropdown, project type, floor area, floors, dates, and duration.
- Added dynamic material rows with unit, quantity, unit cost, waste %, supplier, and line total.
- Added dynamic labor rows with worker type, worker count, daily rate, days, and line total.
- Added dynamic equipment rows with rate type, rate, duration, and line total.
- Added professional fee, permit fee, mobilization fee, supervision fee, overhead, contingency, markup, tax, and discount.
- Added live right-side cost prediction: materials, labor, equipment, fees, overhead, contingency, total contractor cost, markup, tax, client price, estimated profit, profit margin, and risk.
- Added warnings for low margin, loss estimate, low contingency, and high labor ratio.
- Added database tables for estimate materials, labor, and equipment line items.
- Existing estimate records remain supported.

## Install

1. Back up your current files and database.
2. Copy this package over your Maorin Builders root folder.
3. Import or run:

```sql
sql/maorinbuilders_full_upgrade.sql
```

4. Open:

```text
http://localhost/maorinbuilders/workspace.php#estimates
```

## Notes

The old standalone `modules/estimates/index.php` now redirects to the SPA workspace estimate screen so the UI stays consistent with the main website.
