<?php
/**
 * Column header names as they appear in the Excel header row → DB columns.
 * The reader will normalize headers (trim, collapse spaces, case-insensitive).
 * Provide multiple aliases per field to tolerate variations in reference.xlsx.
 */
return [
  // Excel Header/aliases              // DB column in purchase_entries
  'date'                     => 'date',
  'supplier'                 => 'supplier',
  'ref page'                 => 'ref_page',
  'voucher no'               => 'ref_page',         // fallback alias
  'tin'                      => 'tin',
  'vat/nvat'                 => 'vat_nvat',
  'address'                  => 'address',
  'category'                 => 'category',
  'description'              => 'description',
  'project name'             => 'project_name',
  'reference'                => 'reference',
  'input vat'                => 'input_vat',
  'vatable'                  => 'vatable',
  'nonvat'                   => 'non_vat',
  'non-vat'                  => 'non_vat',
  'freight & handling'       => 'freight_handling',
  'freight and handling'     => 'freight_handling',
  'freight_handling'         => 'freight_handling',
  'cash'                     => 'cash',
  'account title'            => 'account_title',
  'debit'                    => 'debit',
  'credit'                   => 'credit',
  'remarks'                  => 'remarks',
];
