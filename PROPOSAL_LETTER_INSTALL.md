# Maorin Builders Proposal Letter Generator

Paste these folders into `C:\xampp\htdocs\maorinbuilders\`.

Run SQL: `database/migrations/20260611_create_proposal_letters.sql`.

Wire in `workspace.php#proposals`:

1. Load CSS in `<head>`:
```html
<link rel="stylesheet" href="assets/css/proposal-letter.css">
```

2. Add this action button per proposal row/card:
```php
<button class="btn btn-sm btn-primary js-proposal-letter-open" data-proposal-id="<?= (int)$proposal['id'] ?>">Generate Letter</button>
```

3. Include modal before closing body:
```php
<?php include __DIR__ . '/proposals/partials/proposal_letter_modal.php'; ?>
<script src="assets/js/proposal-letter.js"></script>
```

Do not touch purchase journal files.
