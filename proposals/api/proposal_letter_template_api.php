<?php
require_once __DIR__.'/../includes/proposal_letter_helpers.php';
require_once __DIR__.'/../includes/proposal_letter_templates.php';
$action=$_GET['action']??'';
if($action==='list_templates') mb_json(['ok'=>true,'templates'=>mb_proposal_templates()]);
if($action==='generate_template'){
  $ctx=mb_fetch_proposal_context($_GET['proposal_id']??0);
  $type=$_GET['template_type']??'Residential Construction Proposal';
  mb_json(['ok'=>true,'context'=>$ctx,'html'=>mb_generate_proposal_letter_html($ctx,$type),'subject'=>'Proposal for '.$ctx['project_name']]);
}
mb_json(['ok'=>false,'message'=>'Unknown action']);
