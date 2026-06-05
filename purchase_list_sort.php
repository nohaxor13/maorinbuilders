<?php
if (!isset($sortValue)) {
  $sortValue = 'date_desc';
}
?>
<select id="journalSort" class="form-select form-select-sm">
  <option value="date_desc" <?= $sortValue === 'date_desc' ? 'selected' : '' ?>>Date Down</option>
  <option value="date_asc" <?= $sortValue === 'date_asc' ? 'selected' : '' ?>>Date Up</option>
  <option value="supplier_asc" <?= $sortValue === 'supplier_asc' ? 'selected' : '' ?>>Supplier A-Z</option>
  <option value="supplier_desc" <?= $sortValue === 'supplier_desc' ? 'selected' : '' ?>>Supplier Z-A</option>
  <option value="total_desc" <?= $sortValue === 'total_desc' ? 'selected' : '' ?>>Total Down</option>
  <option value="total_asc" <?= $sortValue === 'total_asc' ? 'selected' : '' ?>>Total Up</option>
</select>
