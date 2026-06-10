<?php
require_once __DIR__.'/../includes/proposal_letter_helpers.php';
require_once __DIR__.'/../includes/proposal_letter_sanitizer.php';
require_once __DIR__.'/../includes/proposal_letter_templates.php';
$action=$_GET['action']??($_POST['action']??'');
$db=mb_db();
if($action==='get_proposal_context') mb_json(['ok'=>true,'context'=>mb_fetch_proposal_context($_GET['proposal_id']??0)]);
if($action==='save_letter'){
  $body=mb_proposal_letter_sanitize_html($_POST['body']??'');
  if(!$body) mb_json(['ok'=>false,'message'=>'Letter body is required.']);
  if($db instanceof PDO){
    $stmt=$db->prepare("INSERT INTO proposal_letters (proposal_id,letter_number,template_type,subject,body,paper_size,prepared_by,approved_by,status) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([(int)$_POST['proposal_id'],'LTR-'.date('Ymd-His'),$_POST['template_type']??'Custom Template',$_POST['subject']??'', $body, $_POST['paper_size']??'A4', $_POST['prepared_by']??'', $_POST['approved_by']??'', $_POST['status']??'draft']);
    mb_json(['ok'=>true,'letter_id'=>$db->lastInsertId()]);
  }
  mb_json(['ok'=>true,'message'=>'No PDO connection detected; wire mb_db() to your config.php if needed.']);
}
if($action==='list_letters'){
  if($db instanceof PDO){$s=$db->prepare('SELECT * FROM proposal_letters WHERE proposal_id=? ORDER BY updated_at DESC');$s->execute([(int)$_GET['proposal_id']]);mb_json(['ok'=>true,'letters'=>$s->fetchAll(PDO::FETCH_ASSOC)]);} mb_json(['ok'=>true,'letters'=>[]]);
}
if($action==='get_letter'){
  if($db instanceof PDO){$s=$db->prepare('SELECT * FROM proposal_letters WHERE id=?');$s->execute([(int)$_GET['letter_id']]);mb_json(['ok'=>true,'letter'=>$s->fetch(PDO::FETCH_ASSOC)]);} mb_json(['ok'=>false,'message'=>'No database connection.']);
}
mb_json(['ok'=>false,'message'=>'Unknown action']);
