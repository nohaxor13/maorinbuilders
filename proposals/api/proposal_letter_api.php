<?php
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../includes/proposal_letter_helpers.php';
require_once __DIR__.'/../includes/proposal_letter_sanitizer.php';
require_once __DIR__.'/../includes/proposal_letter_templates.php';
if (!is_logged_in()) {
  mb_json(['ok' => false, 'message' => 'Authentication required.']);
}
if (!current_user_can($pdo, 'view_proposals') && !current_user_can($pdo, 'manage_proposals')) {
  http_response_code(403);
  mb_json(['ok' => false, 'message' => 'Forbidden']);
}
$action=$_GET['action']??($_POST['action']??'');
$db=mb_db();
if($action==='get_proposal_context') mb_json(['ok'=>true,'context'=>mb_fetch_proposal_context($_GET['proposal_id']??0),'settings'=>$db instanceof PDO ? mb_fetch_proposal_letter_settings($db,(int)($_GET['proposal_id']??0)) : null]);
if($action==='save_letter'){
  $body=mb_proposal_letter_sanitize_html($_POST['body']??'');
  if(!$body) mb_json(['ok'=>false,'message'=>'Letter body is required.']);
  $proposalId=(int)($_POST['proposal_id']??0);
  $headerMode=trim($_POST['header_mode']??'text');
  $headerTitle=trim($_POST['header_title']??'Maorin Builders');
  $headerSubtitle=trim($_POST['header_subtitle']??'Construction • Renovation • Design & Build');
  $headerLine1=trim($_POST['header_line1']??'Address • Contact Number • Email');
  $headerLine2=trim($_POST['header_line2']??'');
  $showHeader=(int)($_POST['show_header']??1);
  $headerImagePath=trim($_POST['existing_header_image_path']??'');
  if(!empty($_FILES['header_image']['name'])){
    $file=$_FILES['header_image'];
    if(($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) mb_json(['ok'=>false,'message'=>'Header image upload failed.']);
    $ext=strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if($ext !== 'png') mb_json(['ok'=>false,'message'=>'Header image must be a PNG file.']);
    $name='header_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.png';
    $dest=mb_proposal_letter_upload_dir().'/'.$name;
    if(!move_uploaded_file($file['tmp_name'], $dest)) mb_json(['ok'=>false,'message'=>'Unable to save header image.']);
    $headerImagePath=mb_proposal_letter_public_path($name);
  }
  if($db instanceof PDO){
    $db->beginTransaction();
    $stmt=$db->prepare("INSERT INTO proposal_letters (proposal_id,letter_number,template_type,subject,body,paper_size,prepared_by,approved_by,status) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$proposalId,'LTR-'.date('Ymd-His'),$_POST['template_type']??'Custom Template',$_POST['subject']??'', $body, $_POST['paper_size']??'A4', $_POST['prepared_by']??'', $_POST['approved_by']??'', $_POST['status']??'draft']);
    $settingsStmt=$db->prepare("INSERT INTO proposal_letter_settings (proposal_id,header_mode,header_title,header_subtitle,header_line1,header_line2,header_image_path,show_header)
      VALUES (?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE header_mode=VALUES(header_mode),header_title=VALUES(header_title),header_subtitle=VALUES(header_subtitle),header_line1=VALUES(header_line1),header_line2=VALUES(header_line2),header_image_path=VALUES(header_image_path),show_header=VALUES(show_header)");
    $settingsStmt->execute([$proposalId,$headerMode,$headerTitle,$headerSubtitle,$headerLine1,$headerLine2,$headerImagePath,$showHeader]);
    $db->commit();
    mb_json(['ok'=>true,'letter_id'=>$db->lastInsertId()]);
  }
  mb_json(['ok'=>true,'message'=>'No PDO connection detected; wire mb_db() to your config.php if needed.']);
}
if($action==='list_letters'){
  if($db instanceof PDO){$s=$db->prepare('SELECT * FROM proposal_letters WHERE proposal_id=? ORDER BY updated_at DESC');$s->execute([(int)$_GET['proposal_id']]);mb_json(['ok'=>true,'letters'=>$s->fetchAll(PDO::FETCH_ASSOC)]);} mb_json(['ok'=>true,'letters'=>[]]);
}
if($action==='get_letter'){
  if($db instanceof PDO){
    $s=$db->prepare('SELECT * FROM proposal_letters WHERE id=?');
    $s->execute([(int)$_GET['letter_id']]);
    $letter=$s->fetch(PDO::FETCH_ASSOC);
    $settings=['header_mode'=>'text','header_title'=>'Maorin Builders','header_subtitle'=>'Construction • Renovation • Design & Build','header_line1'=>'Address • Contact Number • Email','header_line2'=>'','header_image_path'=>'','show_header'=>1];
    if($letter){
      $settingsStmt=$db->prepare('SELECT * FROM proposal_letter_settings WHERE proposal_id=?');
      $settingsStmt->execute([(int)$letter['proposal_id']]);
      $settings=$settingsStmt->fetch(PDO::FETCH_ASSOC) ?: $settings;
    }
    mb_json(['ok'=>true,'letter'=>$letter,'settings'=>$settings]);
  }
  mb_json(['ok'=>false,'message'=>'No database connection.']);
}
mb_json(['ok'=>false,'message'=>'Unknown action']);
