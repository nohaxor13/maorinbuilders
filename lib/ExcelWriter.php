<?php
namespace PurchasesIO;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelWriter {
  public static function write(array $rows, string $outPath): void {
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Purchase Journal');

    // Reference-like headers (order matters)
    $headers = [
      'Date','Supplier','Ref Page','TIN','VAT/NVAT','Address','Category',
      'Description','Project Name','Reference','Input VAT','Vatable','NonVAT',
      'Freight & Handling','Cash','Account Title','Debit','Credit','Remarks'
    ];

    // Header row
    $r = 1; $c = 1;
    foreach ($headers as $h) {
      $sheet->setCellValueByColumnAndRow($c++, $r, $h);
    }

    // Data rows
    foreach ($rows as $row) {
      $r++; $c = 1;
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['date'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['supplier'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['ref_page'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['tin'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['vat_nvat'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['address'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['category'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['description'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['project_name'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['reference'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['input_vat'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['vatable'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['non_vat'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['freight_handling'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['cash'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['account_title'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['debit'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['credit'] ?? null);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['remarks'] ?? null);
    }

    // Basic styling (freeze header)
    $sheet->freezePane('A2');

    $writer = new Xlsx($ss);
    $writer->save($outPath);
  }
}
