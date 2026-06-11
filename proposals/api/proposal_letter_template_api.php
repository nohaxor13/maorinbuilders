<?php
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../includes/proposal_letter_helpers.php';
require_once __DIR__.'/../includes/proposal_letter_templates.php';
if (!is_logged_in()) {
  mb_json(['ok' => false, 'message' => 'Authentication required.']);
}
if (!current_user_can($pdo, 'view_proposals') && !current_user_can($pdo, 'manage_proposals')) {
  http_response_code(403);
  mb_json(['ok' => false, 'message' => 'Forbidden']);
}
$action=$_GET['action']??'';
if($action==='list_templates') mb_json(['ok'=>true,'templates'=>mb_proposal_templates()]);
if($action==='generate_template'){
  $ctx=mb_fetch_proposal_context($_GET['proposal_id']??0);
  $type=$_GET['template_type']??'Residential Construction Proposal';
  $db=mb_db();
  $settings=$db instanceof PDO ? mb_fetch_proposal_letter_settings($db,(int)($_GET['proposal_id']??0)) : null;
  mb_json(['ok'=>true,'context'=>$ctx,'settings'=>$settings,'html'=>mb_generate_proposal_letter_html($ctx,$type),'subject'=>'Proposal for '.$ctx['project_name']]);
}
mb_json(['ok'=>false,'message'=>'Unknown action']);
