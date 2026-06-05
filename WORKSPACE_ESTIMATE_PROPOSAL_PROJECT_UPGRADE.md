# Workspace Estimate / Proposal / Project Upgrade

This build replaces the plain workspace CRUD experience with a more professional construction operations workflow.

## What changed

- Estimates can now be opened in a modal for full details.
- Estimates can be edited, updated, and deleted inside the SPA.
- Estimate builder keeps material, labor, and equipment line items.
- Estimate builder calculates direct cost, contingency, markup, tax, client price, profit, profit margin, and risk level live.
- Proposals are now wired to estimates and projects.
- Approved proposals automatically create/link a project file when no project is selected.
- Projects now use a structured project control modal instead of a plain manual form.
- Overview now shows contractor/owner control data, not tutorial labels.
- Workspace remains a single-page app under `workspace.php` and keeps the existing website navbar/style.

## Install

1. Back up your project folder and database.
2. Copy this project over your existing `maorinbuilders` folder.
3. Import or run `sql/maorinbuilders_full_upgrade.sql`.
4. Open `http://localhost/maorinbuilders/workspace.php#estimates`.

The runtime also uses guarded table/column creation in `helpers.php` and `modules/workspace_api.php`, so opening the workspace can self-heal missing columns for this upgrade.
