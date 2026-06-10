<?php
function mb_json($data){ header('Content-Type: application/json'); echo json_encode($data); exit; }
function mb_db(){ global $pdo,$conn,$mysqli; if(isset($pdo)&&$pdo instanceof PDO)return $pdo; if(isset($conn)&&$conn instanceof PDO)return $conn; if(isset($mysqli))return $mysqli; return null; }
function mb_proposal_letter_upload_dir(): string {
  $dir = __DIR__ . '/../../storage/uploads/proposal_letters';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  return $dir;
}
function mb_proposal_letter_public_path(string $fileName): string {
  return 'storage/uploads/proposal_letters/' . $fileName;
}
function mb_fetch_proposal_letter_settings(PDO $pdo, int $proposalId): array {
  $stmt = $pdo->prepare("SELECT * FROM proposal_letter_settings WHERE proposal_id=?");
  $stmt->execute([$proposalId]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'proposal_id' => $proposalId,
    'header_mode' => 'text',
    'header_title' => 'Maorin Builders',
    'header_subtitle' => 'Construction • Renovation • Design & Build',
    'header_line1' => 'Address • Contact Number • Email',
    'header_line2' => '',
    'header_image_path' => '',
    'show_header' => 1,
  ];
}
function mb_proposal_letter_value(array $row, array $keys, string $fallback=''): string {
  foreach ($keys as $key) {
    if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
      return (string)$row[$key];
    }
  }
  return $fallback;
}
function mb_fetch_proposal_context($proposal_id){
  $id=(int)$proposal_id;
  $db = mb_db();
  if ($db instanceof PDO && $id > 0) {
    $sql = "SELECT p.*, 
                   e.estimate_no, e.title AS estimate_title, e.client_name AS estimate_client_name, e.location AS estimate_location,
                   e.project_type AS estimate_project_type, e.grand_total AS estimate_grand_total, e.subtotal AS estimate_subtotal,
                   e.material_cost, e.labor_cost, e.equipment_cost, e.overhead_cost, e.markup_amount, e.tax_amount,
                   pr.project_code, pr.name AS project_name, pr.location AS project_location, pr.client_name AS project_client_name,
                   pr.project_type AS project_project_type, pr.contract_amount, pr.estimated_cost
            FROM mb_proposals p
            LEFT JOIN mb_estimates e ON e.id = p.estimate_id
            LEFT JOIN mb_projects pr ON pr.id = p.project_id
            WHERE p.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $estimateTotal = (float)($row['estimate_grand_total'] ?? $row['amount'] ?? 0);
      $materialCost = (float)($row['material_cost'] ?? 0);
      $laborCost = (float)($row['labor_cost'] ?? 0);
      $equipmentCost = (float)($row['equipment_cost'] ?? 0);
      $otherCharges = (float)($row['overhead_cost'] ?? 0) + (float)($row['markup_amount'] ?? 0) + (float)($row['tax_amount'] ?? 0);
      $projectName = mb_proposal_letter_value($row, ['project_name','estimate_title','title'], 'Project Name');
      $projectLocation = mb_proposal_letter_value($row, ['location','project_location','estimate_location'], 'Project Location');
      $clientName = mb_proposal_letter_value($row, ['client_name','estimate_client_name','project_client_name'], 'Client Name');
      $projectType = mb_proposal_letter_value($row, ['project_type','estimate_project_type','project_project_type'], '');
      $timelineDays = (int)($row['timeline_days'] ?? 0);
      $paymentTerms = mb_proposal_letter_value($row, ['payment_terms'], 'Progress billing based on project milestones.');
      $scope = mb_proposal_letter_value($row, ['scope'], 'General construction works based on approved scope and specifications.');
      $exclusions = mb_proposal_letter_value($row, ['exclusions'], '');
      return [
        'proposal_id' => $id,
        'proposal_number' => $row['proposal_no'] ?: ('PROP-'.str_pad($id,5,'0',STR_PAD_LEFT)),
        'proposal_title' => $row['title'] ?: 'Construction Proposal',
        'client_name' => $clientName,
        'client_company' => '',
        'client_address' => mb_proposal_letter_value($row, ['location','project_location','estimate_location'], ''),
        'project_name' => $projectName,
        'project_code' => mb_proposal_letter_value($row, ['project_code'], ''),
        'project_location' => $projectLocation,
        'project_type' => $projectType,
        'estimate_no' => mb_proposal_letter_value($row, ['estimate_no'], ''),
        'estimate_title' => mb_proposal_letter_value($row, ['estimate_title'], ''),
        'estimate_total' => number_format((float)($row['estimate_grand_total'] ?? 0), 2, '.', ''),
        'scope_of_work' => $scope,
        'exclusions' => $exclusions,
        'materials_cost' => number_format($materialCost, 2, '.', ''),
        'labor_cost' => number_format($laborCost, 2, '.', ''),
        'equipment_cost' => number_format($equipmentCost, 2, '.', ''),
        'professional_fee' => number_format((float)($row['professional_fee'] ?? 0), 2, '.', ''),
        'other_charges' => number_format($otherCharges, 2, '.', ''),
        'total_amount' => number_format((float)($row['amount'] ?? $estimateTotal), 2, '.', ''),
        'estimated_duration' => $timelineDays > 0 ? ($timelineDays.' days') : 'To be discussed',
        'payment_terms' => $paymentTerms,
        'validity_date' => $row['valid_until'] ? date('F d, Y', strtotime($row['valid_until'])) : date('F d, Y', strtotime('+30 days')),
        'prepared_by' => 'Maorin Builders',
        'date_prepared' => date('F d, Y', strtotime($row['created_at'] ?? 'now')),
      ];
    }
  }
  return [
    'proposal_id'=>$id,'proposal_number'=>'PROP-'.str_pad($id,5,'0',STR_PAD_LEFT),'proposal_title'=>'Construction Proposal',
    'client_name'=>'Client Name','client_company'=>'','client_address'=>'Client Address','project_name'=>'Project Name',
    'project_location'=>'Project Location','scope_of_work'=>'General construction works based on approved scope and specifications.',
    'materials_cost'=>'0.00','labor_cost'=>'0.00','equipment_cost'=>'0.00','professional_fee'=>'0.00','other_charges'=>'0.00','total_amount'=>'0.00',
    'estimated_duration'=>'To be discussed','payment_terms'=>'Progress billing based on project milestones.','validity_date'=>date('F d, Y',strtotime('+30 days')),
    'prepared_by'=>'Maorin Builders','date_prepared'=>date('F d, Y')
  ];
}
