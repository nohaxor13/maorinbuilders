# Maorin Builders Workspace Estimate Modal Fix

This package fixes the estimate builder modal tab problem where Materials, Labor, Equipment, and Fees tabs still showed the Project Info fields.

## Fixes applied

- Added explicit CSS tab-pane hiding rules for the professional estimate modal.
- Added JavaScript fallback tab switching so the SPA works even when Bootstrap tab behavior is blocked or not loaded in the same order.
- Kept existing estimate builder, live outcome panel, edit/update/delete, and SPA workflow.

## Open

http://localhost/maorinbuilders/workspace.php#estimates
