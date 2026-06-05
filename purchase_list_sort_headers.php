<?php
if (!isset($currentSort)) {
  $currentSort = 'date_desc';
}

function sortHeaderClass(string $sortKey, string $currentSort, bool $right = false): string {
  $classes = ['sort-th'];
  if ($currentSort === $sortKey) {
    $classes[] = 'active';
  }
  if ($right) {
    $classes[] = 'sort-th-right';
  }
  return implode(' ', $classes);
}

function sortArrow(string $sortKey, string $currentSort): string {
  return $currentSort === $sortKey ? (str_ends_with($sortKey, '_asc') ? ' ↑' : ' ↓') : '';
}
?>
<th class="w-actions text-center">Actions</th>
<th class="w-no text-center">#</th>
<th><button type="button" class="<?= sortHeaderClass('date_desc', $currentSort) ?>" data-sort-base="date" data-sort-asc="date_asc" data-sort-desc="date_desc">Date<?= sortArrow('date_desc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('supplier_asc', $currentSort) ?>" data-sort-base="supplier" data-sort-asc="supplier_asc" data-sort-desc="supplier_desc">Supplier<?= sortArrow('supplier_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('ref_page_asc', $currentSort) ?>" data-sort-base="ref_page" data-sort-asc="ref_page_asc" data-sort-desc="ref_page_desc">Ref. Page<?= sortArrow('ref_page_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('tin_asc', $currentSort) ?>" data-sort-base="tin" data-sort-asc="tin_asc" data-sort-desc="tin_desc">TIN<?= sortArrow('tin_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('vat_nvat_asc', $currentSort) ?>" data-sort-base="vat_nvat" data-sort-asc="vat_nvat_asc" data-sort-desc="vat_nvat_desc">VAT/NVAT<?= sortArrow('vat_nvat_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('address_asc', $currentSort) ?>" data-sort-base="address" data-sort-asc="address_asc" data-sort-desc="address_desc">Address<?= sortArrow('address_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('description_asc', $currentSort) ?>" data-sort-base="description" data-sort-asc="description_asc" data-sort-desc="description_desc">Description<?= sortArrow('description_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('project_name_asc', $currentSort) ?>" data-sort-base="project_name" data-sort-asc="project_name_asc" data-sort-desc="project_name_desc">Project Name<?= sortArrow('project_name_asc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('input_vat_desc', $currentSort, true) ?>" data-sort-base="input_vat" data-sort-asc="input_vat_asc" data-sort-desc="input_vat_desc">Input VAT<?= sortArrow('input_vat_desc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('vatable_desc', $currentSort, true) ?>" data-sort-base="vatable" data-sort-asc="vatable_asc" data-sort-desc="vatable_desc">VATable<?= sortArrow('vatable_desc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('non_vat_desc', $currentSort, true) ?>" data-sort-base="non_vat" data-sort-asc="non_vat_asc" data-sort-desc="non_vat_desc">NonVAT<?= sortArrow('non_vat_desc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('total_desc', $currentSort, true) ?>" data-sort-base="total" data-sort-asc="total_asc" data-sort-desc="total_desc">Total<?= sortArrow('total_desc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('cash_desc', $currentSort, true) ?>" data-sort-base="cash" data-sort-asc="cash_asc" data-sort-desc="cash_desc">Cash<?= sortArrow('cash_desc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('debit_desc', $currentSort, true) ?>" data-sort-base="debit" data-sort-asc="debit_asc" data-sort-desc="debit_desc">Debit<?= sortArrow('debit_desc', $currentSort) ?></button></th>
<th class="text-end"><button type="button" class="<?= sortHeaderClass('credit_desc', $currentSort, true) ?>" data-sort-base="credit" data-sort-asc="credit_asc" data-sort-desc="credit_desc">Credit<?= sortArrow('credit_desc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('entered_by_asc', $currentSort) ?>" data-sort-base="entered_by" data-sort-asc="entered_by_asc" data-sort-desc="entered_by_desc">Entered By<?= sortArrow('entered_by_asc', $currentSort) ?></button></th>
<th><button type="button" class="<?= sortHeaderClass('remarks_asc', $currentSort) ?>" data-sort-base="remarks" data-sort-asc="remarks_asc" data-sort-desc="remarks_desc">Remarks<?= sortArrow('remarks_asc', $currentSort) ?></button></th>
