<?php
function mb_json($data){ header('Content-Type: application/json'); echo json_encode($data); exit; }
function mb_db(){ global $pdo,$conn,$mysqli; if(isset($pdo)&&$pdo instanceof PDO)return $pdo; if(isset($conn)&&$conn instanceof PDO)return $conn; if(isset($mysqli))return $mysqli; return null; }
function mb_fetch_proposal_context($proposal_id){
  $id=(int)$proposal_id;
  return [
    'proposal_id'=>$id,'proposal_number'=>'PROP-'.str_pad($id,5,'0',STR_PAD_LEFT),'proposal_title'=>'Construction Proposal',
    'client_name'=>'Client Name','client_company'=>'','client_address'=>'Client Address','project_name'=>'Project Name',
    'project_location'=>'Project Location','scope_of_work'=>'General construction works based on approved scope and specifications.',
    'materials_cost'=>'0.00','labor_cost'=>'0.00','equipment_cost'=>'0.00','professional_fee'=>'0.00','other_charges'=>'0.00','total_amount'=>'0.00',
    'estimated_duration'=>'To be discussed','payment_terms'=>'Progress billing based on project milestones.','validity_date'=>date('F d, Y',strtotime('+30 days')),
    'prepared_by'=>'Maorin Builders','date_prepared'=>date('F d, Y')
  ];
}
