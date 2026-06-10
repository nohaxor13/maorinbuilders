<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
redirect_if_not_logged_in();
ensure_maorin_workspace_tables($pdo);

function ws_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ws_money($v): string { return '₱' . mb_money($v); }
function ws_num($v): float { return is_numeric($v) ? (float)$v : 0.0; }
function ws_json(array $data, int $status = 200): void { http_response_code($status); header('Content-Type: application/json'); echo json_encode($data); exit; }
function ws_column_exists(PDO $pdo, string $table, string $column): bool { $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"); $s->execute([$table,$column]); return (int)$s->fetchColumn()>0; }
function ws_add_col(PDO $pdo, string $table, string $column, string $definition): void { if(!ws_column_exists($pdo,$table,$column)){ $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition"); } }
function ws_index_exists(PDO $pdo, string $table, string $index): bool { $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?"); $s->execute([$table,$index]); return (int)$s->fetchColumn()>0; }
function ws_rate_type_options(): array { return ['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','hourly'=>'Hourly','project'=>'Project Based']; }
function ws_payroll_period_type_options(): array { return ['daily'=>'Daily','weekly'=>'Weekly','semi_monthly'=>'Semi-Monthly','monthly'=>'Monthly','custom'=>'Custom']; }
function ws_attendance_status_options(): array { return ['present'=>'Present','late'=>'Late','half_day'=>'Half Day','absent'=>'Absent','paid_leave'=>'Paid Leave','unpaid_leave'=>'Unpaid Leave','rest_day'=>'Rest Day','holiday'=>'Holiday','suspended'=>'Suspended']; }
function ws_time_to_minutes(?string $time): ?int { $time=trim((string)$time); if($time==='' || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/',$time)) return null; [$h,$m]=array_map('intval',explode(':',substr($time,0,5))); return ($h*60)+$m; }
function ws_minutes_to_hours(int $minutes): float { return round(max(0,$minutes) / 60, 2); }
function ws_time_12(?string $time): string {
  $minutes=ws_time_to_minutes($time);
  if($minutes===null) return '';
  $hours=(int)floor($minutes/60)%24;
  $mins=$minutes%60;
  $suffix=$hours>=12 ? 'PM' : 'AM';
  $hours12=$hours%12;
  if($hours12===0) $hours12=12;
  return sprintf('%d:%02d %s',$hours12,$mins,$suffix);
}
function ws_time_input_to_24(?string $value): ?string {
  $value=trim((string)$value);
  if($value==='') return null;
  if(preg_match('/^\d{2}:\d{2}(?::\d{2})?$/',$value)) return substr($value,0,5);
  if(!preg_match('/^(\d{1,2}):(\d{2})\s*([AaPp][Mm])$/',$value,$m)) return null;
  $hours=(int)$m[1];
  $minutes=(int)$m[2];
  $suffix=strtoupper($m[3]);
  if($hours < 1 || $hours > 12 || $minutes < 0 || $minutes > 59) return null;
  if($suffix==='AM' && $hours===12) $hours=0;
  if($suffix==='PM' && $hours!==12) $hours+=12;
  return sprintf('%02d:%02d',$hours,$minutes);
}
function ws_period_range(string $type, string $anchorDate, ?string $startOverride=null, ?string $endOverride=null): array {
  $anchor=preg_match('/^\d{4}-\d{2}-\d{2}$/',$anchorDate) ? $anchorDate : date('Y-m-d');
  $date=new DateTimeImmutable($anchor);
  return match($type){
    'daily' => ['start'=>$date->format('Y-m-d'),'end'=>$date->format('Y-m-d')],
    'weekly' => ['start'=>$date->modify('monday this week')->format('Y-m-d'),'end'=>$date->modify('sunday this week')->format('Y-m-d')],
    'semi_monthly' => ((int)$date->format('d') <= 15)
      ? ['start'=>$date->modify('first day of this month')->format('Y-m-d'),'end'=>$date->modify('first day of this month')->modify('+14 days')->format('Y-m-d')]
      : ['start'=>$date->modify('first day of this month')->modify('+15 days')->format('Y-m-d'),'end'=>$date->modify('last day of this month')->format('Y-m-d')],
    'monthly' => ['start'=>$date->modify('first day of this month')->format('Y-m-d'),'end'=>$date->modify('last day of this month')->format('Y-m-d')],
    default => ['start'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$startOverride)?(string)$startOverride:$anchor,'end'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$endOverride)?(string)$endOverride:$anchor],
  };
}
function ws_bootstrap(PDO $pdo): void {
  ws_add_col($pdo,'mb_proposals','location',"VARCHAR(255) NULL");
  ws_add_col($pdo,'mb_proposals','project_type',"VARCHAR(80) NULL");
  ws_add_col($pdo,'mb_proposals','payment_terms',"TEXT NULL");
  ws_add_col($pdo,'mb_proposals','exclusions',"TEXT NULL");
  ws_add_col($pdo,'mb_proposals','timeline_days',"INT NOT NULL DEFAULT 0");
  ws_add_col($pdo,'mb_proposals','approved_at',"DATETIME NULL");
  ws_add_col($pdo,'mb_proposals','approved_by',"INT NULL");
  ws_add_col($pdo,'mb_projects','contract_start_date',"DATE NULL");
  ws_add_col($pdo,'mb_projects','site_contact',"VARCHAR(180) NULL");
  ws_add_col($pdo,'mb_projects','priority',"VARCHAR(32) NOT NULL DEFAULT 'normal'");
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_project_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    target_date DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_milestones_project(project_id),
    FOREIGN KEY(project_id) REFERENCES mb_projects(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ws_bootstrap($pdo);

function ws_estimate_status_options(): array { return ['draft'=>'Draft','for_review'=>'For Review','sent'=>'Sent to Client','approved'=>'Approved','rejected'=>'Rejected','revised'=>'Revised']; }
function ws_project_status_options(): array { return ['proposed'=>'Proposed','approved'=>'Approved','ongoing'=>'Ongoing','paused'=>'Paused','completed'=>'Completed','cancelled'=>'Cancelled']; }
function ws_proposal_status_options(): array { return ['draft'=>'Draft','for_review'=>'For Review','sent'=>'Sent to Client','approved'=>'Approved','rejected'=>'Rejected','revised'=>'Revised']; }
function ws_project_type_options(): array { return ['residential'=>'Residential','commercial'=>'Commercial','renovation'=>'Renovation','fit_out'=>'Fit-out','warehouse'=>'Warehouse','land_development'=>'Land Development','other'=>'Other']; }
function ws_risk_class($risk): string { return ['safe'=>'success','review'=>'warn','danger'=>'danger'][strtolower((string)$risk)] ?? 'warn'; }
function ws_estimate_totals_from_post(array $post): array {
  $materials=0; foreach(($post['materials']??[]) as $r){ $qty=ws_num($r['quantity']??0); $cost=ws_num($r['unit_cost']??0); $waste=ws_num($r['waste_percent']??0); if(trim((string)($r['material_name']??''))!=='') $materials += $qty*$cost*(1+$waste/100); }
  $labor=0; foreach(($post['labor']??[]) as $r){ if(trim((string)($r['role_name']??''))!=='') $labor += ws_num($r['worker_count']??0)*ws_num($r['daily_rate']??0)*ws_num($r['days_count']??0); }
  $equipment=0; foreach(($post['equipment']??[]) as $r){ if(trim((string)($r['equipment_name']??''))!=='') $equipment += ws_num($r['rate']??0)*ws_num($r['duration']??0); }
  $fees=ws_num($post['professional_fee']??0)+ws_num($post['permit_fee']??0)+ws_num($post['mobilization_fee']??0)+ws_num($post['supervision_fee']??0);
  $overhead=ws_num($post['overhead_cost']??0); $base=$materials+$labor+$equipment+$fees+$overhead;
  $cont=$base*(ws_num($post['contingency_percent']??0)/100); $subtotal=$base+$cont;
  $markup=$subtotal*(ws_num($post['markup_percent']??0)/100); $tax=($subtotal+$markup)*(ws_num($post['tax_percent']??0)/100); $discount=ws_num($post['discount_amount']??0);
  $grand=max(0,$subtotal+$markup+$tax-$discount); $profit=$grand-$subtotal-$tax; $margin=$grand>0?($profit/$grand*100):0; $target=ws_num($post['target_margin_percent']??15);
  $risk='safe'; if($profit<0 || $grand<$subtotal) $risk='danger'; elseif($margin<$target || ws_num($post['contingency_percent']??0)<5) $risk='review';
  return compact('materials','labor','equipment','fees','overhead','cont','subtotal','markup','tax','discount','grand','profit','margin','risk');
}
function ws_fetch_estimate(PDO $pdo, int $id): array { $s=$pdo->prepare("SELECT * FROM mb_estimates WHERE id=?"); $s->execute([$id]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ?: []; }
function ws_fetch_estimate_lines(PDO $pdo, int $id): array { $out=[]; foreach(['materials'=>'mb_estimate_materials','labor'=>'mb_estimate_labor','equipment'=>'mb_estimate_equipment'] as $k=>$t){ $s=$pdo->prepare("SELECT * FROM $t WHERE estimate_id=? ORDER BY sort_order,id"); $s->execute([$id]); $out[$k]=$s->fetchAll(PDO::FETCH_ASSOC); } return $out; }
function ws_projects_for_select(PDO $pdo): array { return $pdo->query("SELECT id,project_code,name,client_name,location,project_type,contract_amount FROM mb_projects ORDER BY updated_at DESC,id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); }
function ws_estimates_for_select(PDO $pdo): array { return $pdo->query("SELECT id,estimate_no,title,client_name,location,project_type,grand_total,status FROM mb_estimates ORDER BY updated_at DESC,id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); }

function ws_render_overview(PDO $pdo): void { mb_require_any_permission($pdo,['view_projects','view_estimates','view_proposals','view_finance','view_hr','view_inventory','view_documents','view_reports']);
  $projects=(int)$pdo->query("SELECT COUNT(*) FROM mb_projects")->fetchColumn();
  $ongoing=(int)$pdo->query("SELECT COUNT(*) FROM mb_projects WHERE status IN ('approved','ongoing')")->fetchColumn();
  $contract=(float)$pdo->query("SELECT COALESCE(SUM(contract_amount),0) FROM mb_projects")->fetchColumn();
  $actual=(float)$pdo->query("SELECT COALESCE(SUM(actual_cost),0) FROM mb_projects")->fetchColumn();
  $estimates=(int)$pdo->query("SELECT COUNT(*) FROM mb_estimates")->fetchColumn();
  $proposalPending=(int)$pdo->query("SELECT COUNT(*) FROM mb_proposals WHERE status IN ('draft','for_review','sent','revised')")->fetchColumn();
  $proposalAmount=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM mb_proposals WHERE status IN ('sent','approved')")->fetchColumn();
  $expenses=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM mb_expenses")->fetchColumn();
  $inventory=(float)$pdo->query("SELECT COALESCE(SUM(quantity*unit_cost),0) FROM mb_inventory_items")->fetchColumn();
  $lowStock=(int)$pdo->query("SELECT COUNT(*) FROM mb_inventory_items WHERE quantity<=min_quantity AND min_quantity>0")->fetchColumn();
  $employees=(int)$pdo->query("SELECT COUNT(*) FROM mb_employees WHERE status='active'")->fetchColumn();
  $docs=(int)$pdo->query("SELECT COUNT(*) FROM mb_documents")->fetchColumn();
  $margin=$contract>0?($contract-$actual)/$contract*100:0;
  $r=$pdo->query("SELECT name,client_name,status,progress_percent,contract_amount,actual_cost FROM mb_projects ORDER BY updated_at DESC,id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="workspace-pro-dashboard">
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Active Projects</div><div class="stat-main"><?= $ongoing ?></div><div class="stat-sub"><?= $projects ?> total projects</div></div></div>
    <div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Contract Value</div><div class="stat-main"><?= ws_money($contract) ?></div><div class="stat-sub">Actual cost <?= ws_money($actual) ?></div></div></div>
    <div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Gross Balance</div><div class="stat-main"><?= ws_money($contract-$actual) ?></div><div class="stat-sub">Margin <?= number_format($margin,2) ?>%</div></div></div>
    <div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Open Pipeline</div><div class="stat-main"><?= ws_money($proposalAmount) ?></div><div class="stat-sub"><?= $proposalPending ?> active proposals · <?= $estimates ?> estimates</div></div></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="workspace-mini-card"><b><?= ws_money($expenses) ?></b><span>Recorded Expenses</span></div></div>
    <div class="col-md-3"><div class="workspace-mini-card <?= $lowStock?'danger':'' ?>"><b><?= $lowStock ?></b><span>Low Stock Items</span></div></div>
    <div class="col-md-3"><div class="workspace-mini-card"><b><?= $employees ?></b><span>Active Employees</span></div></div>
    <div class="col-md-3"><div class="workspace-mini-card"><b><?= $docs ?></b><span>Documents Stored</span></div></div>
  </div>
  <div class="workspace-section-card"><div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Recent Project Control</h5><span class="text-muted small">Progress, cost, and contract health</span></div><div class="table-responsive"><table class="table table-sm align-middle workspace-table"><thead><tr><th>Project</th><th>Status</th><th>Progress</th><th>Contract</th><th>Cost</th><th>Balance</th></tr></thead><tbody><?php foreach($r as $p): ?><tr><td><strong><?= ws_h($p['name']) ?></strong><div class="text-muted small"><?= ws_h($p['client_name']) ?></div></td><td><span class="mb-badge <?= in_array($p['status'],['completed','approved','ongoing'])?'success':(in_array($p['status'],['paused','cancelled'])?'danger':'warn') ?>"><?= ws_h($p['status']) ?></span></td><td><div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= (int)$p['progress_percent'] ?>%"></div></div><span class="small text-muted"><?= (int)$p['progress_percent'] ?>%</span></td><td><?= ws_money($p['contract_amount']) ?></td><td><?= ws_money($p['actual_cost']) ?></td><td><?= ws_money(((float)$p['contract_amount'])-((float)$p['actual_cost'])) ?></td></tr><?php endforeach; if(!$r): ?><tr><td colspan="6" class="text-center text-muted py-4">No project records yet.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php }

function ws_render_estimate_modal(PDO $pdo, ?array $e=null): void { $isEdit=!empty($e['id']); $lines=$isEdit?ws_fetch_estimate_lines($pdo,(int)$e['id']):['materials'=>[],'labor'=>[],'equipment'=>[]]; $projects=ws_projects_for_select($pdo); ?>
<div class="modal fade" id="estimateBuilderModal"><div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable"><form class="modal-content professional-estimate-modal" data-spa-form data-estimate-builder><input type="hidden" name="module" value="estimates"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($e['id']??0) ?>"><div class="modal-header"><div><h5 class="modal-title"><?= $isEdit?'Edit':'New' ?> Professional Estimate</h5><div class="text-muted small">Build true contractor cost with live profit, margin, tax, and risk prediction.</div></div><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="estimate-builder-grid"><div class="estimate-builder-left">
  <ul class="nav nav-pills estimate-subtabs mb-3"><li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#estInfo" type="button">Project Info</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#estMaterials" type="button">Materials</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#estLabor" type="button">Labor</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#estEquipment" type="button">Equipment</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#estFees" type="button">Fees & Markup</button></li></ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="estInfo"><div class="mb-form-grid"><label>Estimate No.<input class="form-control" name="estimate_no" value="<?= ws_h($e['estimate_no']??'') ?>" placeholder="Auto if blank"></label><label>Title<input required class="form-control" name="title" value="<?= ws_h($e['title']??'') ?>" placeholder="Two-storey residential estimate"></label><label>Linked Project<select class="form-select" name="project_id" data-project-picker><option value="">Not linked yet</option><?php foreach($projects as $p): ?><option value="<?= (int)$p['id'] ?>" data-client="<?= ws_h($p['client_name']) ?>" data-location="<?= ws_h($p['location']) ?>" data-type="<?= ws_h($p['project_type']) ?>" <?= ((int)($e['project_id']??0)===(int)$p['id']?'selected':'') ?>><?= ws_h(($p['project_code']?$p['project_code'].' · ':'').$p['name']) ?></option><?php endforeach; ?></select></label><label>Status<select class="form-select" name="status"><?php foreach(ws_estimate_status_options() as $k=>$v): ?><option value="<?= $k ?>" <?= (($e['status']??'draft')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Client<input class="form-control" name="client_name" data-project-client value="<?= ws_h($e['client_name']??'') ?>"></label><label>Location<input class="form-control" name="location" data-project-location value="<?= ws_h($e['location']??'') ?>"></label><label>Project Type<select class="form-select" name="project_type" data-project-type><?php foreach(ws_project_type_options() as $k=>$v): ?><option value="<?= $k ?>" <?= (($e['project_type']??'')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Floor Area (sqm)<input class="form-control" type="number" step="0.01" name="floor_area" value="<?= ws_h($e['floor_area']??0) ?>"></label><label>No. of Floors<input class="form-control" type="number" step="1" name="floors" value="<?= ws_h($e['floors']??1) ?>"></label><label>Duration Days<input class="form-control" type="number" step="1" name="duration_days" value="<?= ws_h($e['duration_days']??0) ?>"></label><label>Target Start<input class="form-control" type="date" name="target_start_date" value="<?= ws_h($e['target_start_date']??'') ?>"></label><label>Target End<input class="form-control" type="date" name="target_end_date" value="<?= ws_h($e['target_end_date']??'') ?>"></label><label class="full">Notes<textarea class="form-control" name="notes" rows="3"><?= ws_h($e['notes']??'') ?></textarea></label></div></div>
    <div class="tab-pane fade" id="estMaterials"><div class="line-head"><h6>Materials with waste allowance</h6><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="materials">Add Material</button></div><div data-lines="materials"><?php foreach($lines['materials'] as $i=>$r): ?><div class="estimate-line" data-line="materials"><div><label>Material</label><input class="form-control form-control-sm" name="materials[<?= $i ?>][material_name]" value="<?= ws_h($r['material_name']) ?>"></div><div><label>Unit</label><input class="form-control form-control-sm" name="materials[<?= $i ?>][unit]" value="<?= ws_h($r['unit']) ?>"></div><div><label>Qty</label><input class="form-control form-control-sm" name="materials[<?= $i ?>][quantity]" type="number" step="0.001" value="<?= ws_h($r['quantity']) ?>"></div><div><label>Unit Cost</label><input class="form-control form-control-sm" name="materials[<?= $i ?>][unit_cost]" type="number" step="0.01" value="<?= ws_h($r['unit_cost']) ?>"></div><div><label>Waste %</label><input class="form-control form-control-sm" name="materials[<?= $i ?>][waste_percent]" type="number" step="0.01" value="<?= ws_h($r['waste_percent']) ?>"></div><div><label>Supplier</label><input class="form-control form-control-sm" name="materials[<?= $i ?>][supplier]" value="<?= ws_h($r['supplier']) ?>"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div><?php endforeach; ?></div></div>
    <div class="tab-pane fade" id="estLabor"><div class="line-head"><h6>Labor manpower and duration</h6><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="labor">Add Labor</button></div><div data-lines="labor"><?php foreach($lines['labor'] as $i=>$r): ?><div class="estimate-line labor-line" data-line="labor"><div><label>Worker Type</label><input class="form-control form-control-sm" name="labor[<?= $i ?>][role_name]" value="<?= ws_h($r['role_name']) ?>"></div><div><label>Workers</label><input class="form-control form-control-sm" name="labor[<?= $i ?>][worker_count]" type="number" step="0.01" value="<?= ws_h($r['worker_count']) ?>"></div><div><label>Daily Rate</label><input class="form-control form-control-sm" name="labor[<?= $i ?>][daily_rate]" type="number" step="0.01" value="<?= ws_h($r['daily_rate']) ?>"></div><div><label>Days</label><input class="form-control form-control-sm" name="labor[<?= $i ?>][days_count]" type="number" step="0.01" value="<?= ws_h($r['days_count']) ?>"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div><?php endforeach; ?></div></div>
    <div class="tab-pane fade" id="estEquipment"><div class="line-head"><h6>Equipment, rentals, and site resources</h6><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="equipment">Add Equipment</button></div><div data-lines="equipment"><?php foreach($lines['equipment'] as $i=>$r): ?><div class="estimate-line equipment-line" data-line="equipment"><div><label>Equipment</label><input class="form-control form-control-sm" name="equipment[<?= $i ?>][equipment_name]" value="<?= ws_h($r['equipment_name']) ?>"></div><div><label>Rate Type</label><select class="form-select form-select-sm" name="equipment[<?= $i ?>][rate_type]"><?php foreach(['daily'=>'Daily','hourly'=>'Hourly','fixed'=>'Fixed'] as $k=>$v): ?><option value="<?= $k ?>" <?= (($r['rate_type']??'daily')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></div><div><label>Rate</label><input class="form-control form-control-sm" name="equipment[<?= $i ?>][rate]" type="number" step="0.01" value="<?= ws_h($r['rate']) ?>"></div><div><label>Duration</label><input class="form-control form-control-sm" name="equipment[<?= $i ?>][duration]" type="number" step="0.01" value="<?= ws_h($r['duration']) ?>"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div><?php endforeach; ?></div></div>
    <div class="tab-pane fade" id="estFees"><div class="mb-form-grid"><label>Professional Fee<input class="form-control" type="number" step="0.01" name="professional_fee" value="<?= ws_h($e['professional_fee']??0) ?>"></label><label>Permit Processing Fee<input class="form-control" type="number" step="0.01" name="permit_fee" value="<?= ws_h($e['permit_fee']??0) ?>"></label><label>Mobilization Fee<input class="form-control" type="number" step="0.01" name="mobilization_fee" value="<?= ws_h($e['mobilization_fee']??0) ?>"></label><label>Site Supervision Fee<input class="form-control" type="number" step="0.01" name="supervision_fee" value="<?= ws_h($e['supervision_fee']??0) ?>"></label><label>Overhead Cost<input class="form-control" type="number" step="0.01" name="overhead_cost" value="<?= ws_h($e['overhead_cost']??0) ?>"></label><label>Contingency %<input class="form-control" type="number" step="0.01" name="contingency_percent" value="<?= ws_h($e['contingency_percent']??10) ?>"></label><label>Markup %<input class="form-control" type="number" step="0.01" name="markup_percent" value="<?= ws_h($e['markup_percent']??20) ?>"></label><label>Tax %<input class="form-control" type="number" step="0.01" name="tax_percent" value="<?= ws_h($e['tax_percent']??12) ?>"></label><label>Discount<input class="form-control" type="number" step="0.01" name="discount_amount" value="<?= ws_h($e['discount_amount']??0) ?>"></label><label>Target Margin %<input class="form-control" type="number" step="0.01" name="target_margin_percent" value="<?= ws_h($e['target_margin_percent']??15) ?>"></label></div></div>
  </div>
</div><aside class="estimate-live-panel"><div class="live-panel-head"><span>Live Outcome</span><b data-risk-label class="mb-risk warn">Review</b></div><?php foreach(['materials'=>'Materials','labor'=>'Labor','equipment'=>'Equipment','fees'=>'Professional / Site Fees','overhead'=>'Overhead','contingency'=>'Contingency','subtotal'=>'True Project Cost','markup'=>'Markup','tax'=>'Tax','discount'=>'Discount','grand'=>'Client Price','profit'=>'Predicted Profit'] as $k=>$v): ?><div class="live-row <?= in_array($k,['subtotal','grand','profit'])?'strong':'' ?>"><span><?= $v ?></span><b data-sum="<?= $k ?>">₱0.00</b></div><?php endforeach; ?><div class="live-row strong"><span>Profit Margin</span><b data-sum="margin">0.00%</b></div><div data-warnings class="estimate-warnings"></div></aside></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Estimate</button></div></form></div></div>
<?php }

function ws_render_estimate_view(PDO $pdo, int $id): void { $e=ws_fetch_estimate($pdo,$id); if(!$e){ echo '<div class="alert alert-warning">Estimate not found.</div>'; return; } $lines=ws_fetch_estimate_lines($pdo,$id); ?>
<div class="estimate-view"><div class="row g-3"><div class="col-md-8"><h4><?= ws_h($e['title']) ?></h4><div class="text-muted"><?= ws_h($e['estimate_no'] ?: 'EST-'.$e['id']) ?> · <?= ws_h($e['client_name']) ?> · <?= ws_h($e['location']) ?></div></div><div class="col-md-4 text-md-end"><span class="mb-badge <?= ws_risk_class($e['risk_level']) ?>"><?= strtoupper(ws_h($e['risk_level'])) ?></span><div class="fs-4 fw-bold mt-2"><?= ws_money($e['grand_total']) ?></div><div class="text-muted small">Client Price</div></div></div><hr><div class="row g-3 mb-3"><div class="col-md-3"><div class="workspace-mini-card"><b><?= ws_money($e['material_cost']) ?></b><span>Materials</span></div></div><div class="col-md-3"><div class="workspace-mini-card"><b><?= ws_money($e['labor_cost']) ?></b><span>Labor</span></div></div><div class="col-md-3"><div class="workspace-mini-card"><b><?= ws_money($e['equipment_cost']) ?></b><span>Equipment</span></div></div><div class="col-md-3"><div class="workspace-mini-card"><b><?= number_format((float)$e['profit_margin_percent'],2) ?>%</b><span>Profit Margin</span></div></div></div>
<?php foreach(['materials'=>'Materials','labor'=>'Labor','equipment'=>'Equipment'] as $key=>$label): ?><h6 class="mt-3"><?= $label ?></h6><div class="table-responsive"><table class="table table-sm workspace-table"><tbody><?php foreach($lines[$key] as $r): ?><tr><?php if($key==='materials'): ?><td><?= ws_h($r['material_name']) ?></td><td><?= ws_h($r['quantity'].' '.$r['unit']) ?></td><td><?= ws_money($r['unit_cost']) ?></td><td><?= ws_h($r['waste_percent']) ?>% waste</td><td class="text-end"><?= ws_money($r['line_total']) ?></td><?php elseif($key==='labor'): ?><td><?= ws_h($r['role_name']) ?></td><td><?= ws_h($r['worker_count']) ?> worker(s)</td><td><?= ws_money($r['daily_rate']) ?>/day</td><td><?= ws_h($r['days_count']) ?> day(s)</td><td class="text-end"><?= ws_money($r['line_total']) ?></td><?php else: ?><td><?= ws_h($r['equipment_name']) ?></td><td><?= ws_h($r['rate_type']) ?></td><td><?= ws_money($r['rate']) ?></td><td><?= ws_h($r['duration']) ?></td><td class="text-end"><?= ws_money($r['line_total']) ?></td><?php endif; ?></tr><?php endforeach; if(!$lines[$key]): ?><tr><td class="text-muted">No <?= strtolower($label) ?> lines.</td></tr><?php endif; ?></tbody></table></div><?php endforeach; ?><div class="mt-3"><strong>Notes</strong><div class="text-muted"><?= nl2br(ws_h($e['notes'])) ?></div></div></div>
<?php }

function ws_render_estimates(PDO $pdo, string $q=''): void { require_permission($pdo,'view_estimates'); $can=current_user_can($pdo,'manage_estimates'); $where=''; $params=[]; if($q!==''){ $where="WHERE estimate_no LIKE ? OR title LIKE ? OR client_name LIKE ? OR status LIKE ? OR location LIKE ?"; $params=array_fill(0,5,'%'.$q.'%'); } $st=$pdo->prepare("SELECT * FROM mb_estimates $where ORDER BY updated_at DESC,id DESC LIMIT 200"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-0">Professional Estimates</h5><div class="text-muted small">Open each estimate to review line items, edit assumptions, convert later to proposal, and prevent loss before submitting.</div></div><?php if($can): ?><button class="btn btn-primary btn-sm" data-workspace-open="estimateBuilderModal">New Estimate</button><?php endif; ?></div>
<div class="table-responsive workspace-table-wrap"><table class="table table-hover align-middle workspace-table"><thead><tr><th>No.</th><th>Title / Client</th><th>Status</th><th>Direct Cost</th><th>Client Price</th><th>Profit</th><th>Margin</th><th>Risk</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): $direct=(float)$r['material_cost']+(float)$r['labor_cost']+(float)$r['equipment_cost']; ?><tr><td class="fw-semibold"><?= ws_h($r['estimate_no'] ?: ('EST-'.$r['id'])) ?></td><td><div class="fw-semibold"><?= ws_h($r['title']) ?></div><div class="text-muted small"><?= ws_h($r['client_name']) ?><?= $r['location']?' · '.ws_h($r['location']):'' ?></div></td><td><span class="mb-badge"><?= ws_h(ws_estimate_status_options()[$r['status']] ?? $r['status']) ?></span></td><td><?= ws_money($direct) ?></td><td class="fw-bold"><?= ws_money($r['grand_total']) ?></td><td><?= ws_money($r['profit_amount']) ?></td><td><?= number_format((float)$r['profit_margin_percent'],2) ?>%</td><td><span class="mb-badge <?= ws_risk_class($r['risk_level']) ?>"><?= strtoupper(ws_h($r['risk_level'])) ?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary" data-ws-view="estimates" data-id="<?= (int)$r['id'] ?>">View</button><?php if($can): ?> <button class="btn btn-sm btn-outline-primary" data-ws-edit="estimates" data-id="<?= (int)$r['id'] ?>">Edit</button> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete estimate?" data-id="<?= (int)$r['id'] ?>">Delete</button><?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No estimates yet.</td></tr><?php endif; ?></tbody></table></div><?php if($can) ws_render_estimate_modal($pdo); }

function ws_render_project_modal(PDO $pdo, ?array $p=null): void { $is=!empty($p['id']); ?>
<div class="modal fade" id="projectModal"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content professional-project-modal" data-spa-form><input type="hidden" name="module" value="projects"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($p['id']??0) ?>"><div class="modal-header"><div><h5 class="modal-title"><?= $is?'Edit':'New' ?> Project Control File</h5><div class="text-muted small">Create structured project records with client, scope, cost control, schedule, and milestone notes.</div></div><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="project-form-layout"><section><h6>Project Identity</h6><div class="mb-form-grid"><label>Project Code<input class="form-control" name="project_code" value="<?= ws_h($p['project_code']??'') ?>" placeholder="Auto/Manual"></label><label>Project Name<input required class="form-control" name="name" value="<?= ws_h($p['name']??'') ?>"></label><label>Type<select class="form-select" name="project_type"><?php foreach(ws_project_type_options() as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['project_type']??'')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Status<select class="form-select" name="status"><?php foreach(ws_project_status_options() as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['status']??'proposed')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Priority<select class="form-select" name="priority"><?php foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['priority']??'normal')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Location<input class="form-control" name="location" value="<?= ws_h($p['location']??'') ?>"></label></div></section><section><h6>Client and Site Contact</h6><div class="mb-form-grid"><label>Client Name<input class="form-control" name="client_name" value="<?= ws_h($p['client_name']??'') ?>"></label><label>Client Email<input class="form-control" name="client_email" value="<?= ws_h($p['client_email']??'') ?>"></label><label>Client Phone<input class="form-control" name="client_phone" value="<?= ws_h($p['client_phone']??'') ?>"></label><label>Site Contact<input class="form-control" name="site_contact" value="<?= ws_h($p['site_contact']??'') ?>"></label></div></section><section><h6>Schedule and Cost Control</h6><div class="mb-form-grid"><label>Contract Start<input class="form-control" type="date" name="contract_start_date" value="<?= ws_h($p['contract_start_date']??'') ?>"></label><label>Target End<input class="form-control" type="date" name="target_end_date" value="<?= ws_h($p['target_end_date']??'') ?>"></label><label>Progress %<input class="form-control" type="number" min="0" max="100" name="progress_percent" value="<?= ws_h($p['progress_percent']??0) ?>"></label><label>Estimated Cost<input class="form-control" type="number" step="0.01" name="estimated_cost" value="<?= ws_h($p['estimated_cost']??0) ?>"></label><label>Actual Cost<input class="form-control" type="number" step="0.01" name="actual_cost" value="<?= ws_h($p['actual_cost']??0) ?>"></label><label>Contract Amount<input class="form-control" type="number" step="0.01" name="contract_amount" value="<?= ws_h($p['contract_amount']??0) ?>"></label><label class="full">Scope / Notes<textarea class="form-control" name="notes" rows="4"><?= ws_h($p['notes']??'') ?></textarea></label></div></section></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Project</button></div></form></div></div>
<?php }
function ws_render_project_view(PDO $pdo, int $id): void { $s=$pdo->prepare("SELECT *,(contract_amount-actual_cost) profit FROM mb_projects WHERE id=?"); $s->execute([$id]); $p=$s->fetch(PDO::FETCH_ASSOC); if(!$p){ echo '<div class="alert alert-warning">Project not found.</div>'; return; } ?>
<div class="project-view"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><h4><?= ws_h($p['name']) ?></h4><div class="text-muted"><?= ws_h($p['project_code']) ?> · <?= ws_h($p['client_name']) ?> · <?= ws_h($p['location']) ?></div></div><div class="text-end"><span class="mb-badge <?= in_array($p['status'],['approved','ongoing','completed'])?'success':(in_array($p['status'],['paused','cancelled'])?'danger':'warn') ?>"><?= ws_h($p['status']) ?></span><div class="mt-2 fw-bold fs-4"><?= ws_money($p['contract_amount']) ?></div></div></div><hr><div class="row g-3"><div class="col-md-3"><div class="workspace-mini-card"><b><?= (int)$p['progress_percent'] ?>%</b><span>Progress</span></div></div><div class="col-md-3"><div class="workspace-mini-card"><b><?= ws_money($p['estimated_cost']) ?></b><span>Estimated Cost</span></div></div><div class="col-md-3"><div class="workspace-mini-card"><b><?= ws_money($p['actual_cost']) ?></b><span>Actual Cost</span></div></div><div class="col-md-3"><div class="workspace-mini-card <?= ((float)$p['profit']<0?'danger':'') ?>"><b><?= ws_money($p['profit']) ?></b><span>Balance / Profit</span></div></div></div><div class="mt-3"><div class="progress" style="height:12px"><div class="progress-bar" style="width:<?= (int)$p['progress_percent'] ?>%"></div></div></div><div class="row mt-3"><div class="col-md-6"><strong>Client</strong><p class="text-muted mb-0"><?= ws_h($p['client_name']) ?><br><?= ws_h($p['client_email']) ?><br><?= ws_h($p['client_phone']) ?></p></div><div class="col-md-6"><strong>Scope / Notes</strong><p class="text-muted mb-0"><?= nl2br(ws_h($p['notes'])) ?></p></div></div></div>
<?php }
function ws_render_projects(PDO $pdo, string $q=''): void { require_permission($pdo,'view_projects'); $can=current_user_can($pdo,'manage_projects'); $where='';$params=[]; if($q!==''){ $where="WHERE name LIKE ? OR client_name LIKE ? OR project_code LIKE ? OR status LIKE ? OR location LIKE ?"; $params=array_fill(0,5,'%'.$q.'%'); } $st=$pdo->prepare("SELECT *,(contract_amount-actual_cost) profit FROM mb_projects $where ORDER BY updated_at DESC,id DESC LIMIT 200"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-0">Projects</h5><div class="text-muted small">Professional control file for contracts, costs, schedule, progress, client data, and project health.</div></div><?php if($can): ?><button class="btn btn-primary btn-sm" data-workspace-open="projectModal">New Project</button><?php endif; ?></div>
<div class="table-responsive workspace-table-wrap"><table class="table table-hover align-middle workspace-table"><thead><tr><th>Code</th><th>Project / Client</th><th>Type</th><th>Status</th><th>Progress</th><th>Contract</th><th>Actual Cost</th><th>Balance</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= ws_h($r['project_code']?:'—') ?></td><td><strong><?= ws_h($r['name']) ?></strong><div class="text-muted small"><?= ws_h($r['client_name']) ?><?= $r['location']?' · '.ws_h($r['location']):'' ?></div></td><td><?= ws_h($r['project_type']) ?></td><td><span class="mb-badge <?= in_array($r['status'],['approved','ongoing','completed'])?'success':(in_array($r['status'],['paused','cancelled'])?'danger':'warn') ?>"><?= ws_h($r['status']) ?></span></td><td><div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= (int)$r['progress_percent'] ?>%"></div></div><span class="small text-muted"><?= (int)$r['progress_percent'] ?>%</span></td><td><?= ws_money($r['contract_amount']) ?></td><td><?= ws_money($r['actual_cost']) ?></td><td><?= ws_money($r['profit']) ?></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary" data-ws-view="projects" data-id="<?= (int)$r['id'] ?>">View</button><?php if($can): ?> <button class="btn btn-sm btn-outline-primary" data-ws-edit="projects" data-id="<?= (int)$r['id'] ?>">Edit</button> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete project?" data-id="<?= (int)$r['id'] ?>">Delete</button><?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No projects yet.</td></tr><?php endif; ?></tbody></table></div><?php if($can) ws_render_project_modal($pdo); }

function ws_render_proposal_modal(PDO $pdo, ?array $p=null): void { $is=!empty($p['id']); $projects=ws_projects_for_select($pdo); $estimates=ws_estimates_for_select($pdo); ?>
<div class="modal fade" id="proposalModal"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content professional-proposal-modal" data-spa-form data-proposal-builder><input type="hidden" name="module" value="proposals"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($p['id']??0) ?>"><div class="modal-header"><div><h5 class="modal-title"><?= $is?'Edit':'New' ?> Proposal</h5><div class="text-muted small">Wire proposal to an estimate/project. Approved proposals can automatically create the project.</div></div><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="mb-form-grid"><label>Proposal No.<input class="form-control" name="proposal_no" value="<?= ws_h($p['proposal_no']??'') ?>" placeholder="Auto if blank"></label><label>Status<select class="form-select" name="status"><?php foreach(ws_proposal_status_options() as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['status']??'draft')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Linked Estimate<select class="form-select" name="estimate_id" data-proposal-estimate><option value="">No estimate</option><?php foreach($estimates as $e): ?><option value="<?= (int)$e['id'] ?>" data-title="<?= ws_h($e['title']) ?>" data-client="<?= ws_h($e['client_name']) ?>" data-location="<?= ws_h($e['location']) ?>" data-type="<?= ws_h($e['project_type']) ?>" data-amount="<?= ws_h($e['grand_total']) ?>" <?= ((int)($p['estimate_id']??0)===(int)$e['id']?'selected':'') ?>><?= ws_h(($e['estimate_no']?$e['estimate_no'].' · ':'').$e['title'].' · '.mb_money($e['grand_total'])) ?></option><?php endforeach; ?></select></label><label>Linked Project<select class="form-select" name="project_id"><option value="">Create when approved / no project yet</option><?php foreach($projects as $pr): ?><option value="<?= (int)$pr['id'] ?>" <?= ((int)($p['project_id']??0)===(int)$pr['id']?'selected':'') ?>><?= ws_h(($pr['project_code']?$pr['project_code'].' · ':'').$pr['name']) ?></option><?php endforeach; ?></select></label><label>Title<input required class="form-control" name="title" data-proposal-title value="<?= ws_h($p['title']??'') ?>"></label><label>Client<input class="form-control" name="client_name" data-proposal-client value="<?= ws_h($p['client_name']??'') ?>"></label><label>Location<input class="form-control" name="location" data-proposal-location value="<?= ws_h($p['location']??'') ?>"></label><label>Project Type<select class="form-select" name="project_type" data-proposal-type><?php foreach(ws_project_type_options() as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['project_type']??'')===$k?'selected':'') ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Amount<input class="form-control" type="number" step="0.01" name="amount" data-proposal-amount value="<?= ws_h($p['amount']??0) ?>"></label><label>Timeline Days<input class="form-control" type="number" name="timeline_days" value="<?= ws_h($p['timeline_days']??0) ?>"></label><label>Valid Until<input class="form-control" type="date" name="valid_until" value="<?= ws_h($p['valid_until']??'') ?>"></label><label class="full">Scope of Work<textarea class="form-control" name="scope" rows="4" placeholder="Detailed inclusions, deliverables, construction phases..."><?= ws_h($p['scope']??'') ?></textarea></label><label class="full">Payment Terms<textarea class="form-control" name="payment_terms" rows="3" placeholder="Reservation, progress billing, retention, final payment..."><?= ws_h($p['payment_terms']??'') ?></textarea></label><label class="full">Exclusions / Client Responsibilities<textarea class="form-control" name="exclusions" rows="3"><?= ws_h($p['exclusions']??'') ?></textarea></label><label class="full">General Terms<textarea class="form-control" name="terms" rows="3"><?= ws_h($p['terms']??'') ?></textarea></label></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Proposal</button></div></form></div></div>
<?php }
function ws_render_proposal_view(PDO $pdo, int $id): void { $s=$pdo->prepare("SELECT p.*,e.estimate_no,e.grand_total estimate_total,pr.project_code,pr.name project_name FROM mb_proposals p LEFT JOIN mb_estimates e ON e.id=p.estimate_id LEFT JOIN mb_projects pr ON pr.id=p.project_id WHERE p.id=?"); $s->execute([$id]); $p=$s->fetch(PDO::FETCH_ASSOC); if(!$p){ echo '<div class="alert alert-warning">Proposal not found.</div>'; return; } ?>
<div class="proposal-view"><div class="d-flex justify-content-between flex-wrap gap-3"><div><h4><?= ws_h($p['title']) ?></h4><div class="text-muted"><?= ws_h($p['proposal_no'] ?: 'PROP-'.$p['id']) ?> · <?= ws_h($p['client_name']) ?> · <?= ws_h($p['location']) ?></div></div><div class="text-end"><span class="mb-badge <?= $p['status']==='approved'?'success':($p['status']==='rejected'?'danger':'warn') ?>"><?= ws_h($p['status']) ?></span><div class="fs-4 fw-bold mt-2"><?= ws_money($p['amount']) ?></div></div></div><hr><div class="row g-3 mb-3"><div class="col-md-4"><div class="workspace-mini-card"><b><?= ws_h($p['estimate_no'] ?: 'Not linked') ?></b><span>Source Estimate</span></div></div><div class="col-md-4"><div class="workspace-mini-card"><b><?= ws_h($p['project_name'] ?: 'Will create on approval') ?></b><span>Project</span></div></div><div class="col-md-4"><div class="workspace-mini-card"><b><?= ws_h($p['valid_until'] ?: '—') ?></b><span>Valid Until</span></div></div></div><h6>Scope of Work</h6><p class="text-muted"><?= nl2br(ws_h($p['scope'])) ?></p><h6>Payment Terms</h6><p class="text-muted"><?= nl2br(ws_h($p['payment_terms'] ?: $p['terms'])) ?></p><h6>Exclusions</h6><p class="text-muted"><?= nl2br(ws_h($p['exclusions'])) ?></p></div>
<?php }
function ws_render_proposals(PDO $pdo, string $q=''): void { require_permission($pdo,'view_proposals'); $can=current_user_can($pdo,'manage_proposals'); $where='';$params=[]; if($q!==''){ $where="WHERE p.proposal_no LIKE ? OR p.title LIKE ? OR p.client_name LIKE ? OR p.status LIKE ?"; $params=array_fill(0,4,'%'.$q.'%'); } $st=$pdo->prepare("SELECT p.*,e.estimate_no,pr.project_code,pr.name project_name FROM mb_proposals p LEFT JOIN mb_estimates e ON e.id=p.estimate_id LEFT JOIN mb_projects pr ON pr.id=p.project_id $where ORDER BY p.updated_at DESC,p.id DESC LIMIT 200"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-0">Proposals</h5><div class="text-muted small">Create client-ready scope, terms, amount, and approval flow from estimates. Approval can generate the project file automatically.</div></div><?php if($can): ?><button class="btn btn-primary btn-sm" data-workspace-open="proposalModal">New Proposal</button><?php endif; ?></div>
<div class="table-responsive workspace-table-wrap"><table class="table table-hover align-middle workspace-table"><thead><tr><th>Proposal</th><th>Client / Source</th><th>Status</th><th>Amount</th><th>Valid Until</th><th>Project</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong><?= ws_h($r['proposal_no'] ?: 'PROP-'.$r['id']) ?></strong><div class="text-muted small"><?= ws_h($r['title']) ?></div></td><td><?= ws_h($r['client_name']) ?><div class="text-muted small"><?= ws_h($r['estimate_no'] ?: 'No estimate') ?></div></td><td><span class="mb-badge <?= $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':'warn') ?>"><?= ws_h($r['status']) ?></span></td><td><?= ws_money($r['amount']) ?></td><td><?= ws_h($r['valid_until'] ?: '—') ?></td><td><?= ws_h($r['project_name'] ?: '—') ?></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary" data-ws-view="proposals" data-id="<?= (int)$r['id'] ?>">View</button><?php if($can): ?> <button class="btn btn-sm btn-outline-primary" data-ws-edit="proposals" data-id="<?= (int)$r['id'] ?>">Edit</button><?php if($r['status']!=='approved'): ?> <button class="btn btn-sm btn-success" data-ws-approve-proposal="<?= (int)$r['id'] ?>">Approve</button><?php endif; ?> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete proposal?" data-id="<?= (int)$r['id'] ?>">Delete</button><?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No proposals yet.</td></tr><?php endif; ?></tbody></table></div><?php if($can) ws_render_proposal_modal($pdo); }

function ws_render_generic(PDO $pdo, string $module, string $q=''): void {
  $map=[
    'expenses'=>['view_finance','manage_expenses','mb_expenses','Expenses',['expense_date'=>'Date','category'=>'Category','vendor'=>'Vendor','description'=>'Description','amount'=>'Amount','tax_amount'=>'Tax','reference_no'=>'Reference','status'=>'Status'],['amount','tax_amount']],
    'invoices'=>['view_finance','manage_invoices','mb_invoices','Invoices',['invoice_no'=>'Invoice No.','client_name'=>'Client','issue_date'=>'Issue Date','due_date'=>'Due Date','amount'=>'Amount','paid_amount'=>'Paid','status'=>'Status','notes'=>'Notes'],['amount','paid_amount']],
    'employees'=>['view_hr','manage_employees','mb_employees','Employees',['employee_code'=>'Code','full_name'=>'Full Name','employee_type'=>'Type','job_title'=>'Job Title','department'=>'Department','phone'=>'Phone','email'=>'Email','daily_rate'=>'Daily Rate','status'=>'Status'],['daily_rate']],
    'attendance'=>['view_hr','manage_attendance','mb_attendance','Attendance',['employee_id'=>'Employee ID','attendance_date'=>'Date','time_in'=>'Time In','time_out'=>'Time Out','status'=>'Status','notes'=>'Notes'],[]],
    'inventory'=>['view_inventory','manage_inventory','mb_inventory_items','Inventory',['sku'=>'SKU','item_name'=>'Item Name','category'=>'Category','unit'=>'Unit','quantity'=>'Qty','min_quantity'=>'Min','unit_cost'=>'Unit Cost','location'=>'Location','status'=>'Status'],['unit_cost']],
    'documents'=>['view_documents','manage_documents','mb_documents','Documents',['title'=>'Title','category'=>'Category','status'=>'Status','file_path'=>'File Path','expiry_date'=>'Expiry','notes'=>'Notes'],[]],
    'plans'=>['view_plans','manage_plans','mb_plan_files','Plans',['title'=>'Title','plan_type'=>'Plan Type','revision'=>'Revision','status'=>'Status','file_path'=>'File Path','notes'=>'Notes'],[]],
  ];
  if(!isset($map[$module])){ echo '<div class="alert alert-warning">Module not found.</div>'; return; }
  [$view,$manage,$table,$title,$fields,$moneyCols]=$map[$module]; require_permission($pdo,$view); $can=current_user_can($pdo,$manage); $st=$pdo->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT 200"); $rows=$st->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0"><?= ws_h($title) ?></h5><div class="text-muted small">Operational records with modal CRUD.</div></div><?php if($can): ?><button class="btn btn-primary btn-sm" data-workspace-open="genericModal">New Record</button><?php endif; ?></div><div class="table-responsive workspace-table-wrap"><table class="table table-hover workspace-table"><thead><tr><?php foreach($fields as $f): ?><th><?= ws_h($f) ?></th><?php endforeach; ?><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><?php foreach($fields as $col=>$label): ?><td><?= in_array($col,$moneyCols,true)?ws_money($r[$col]??0):ws_h($r[$col]??'') ?></td><?php endforeach; ?><td class="text-end"><?php if($can): ?><button class="btn btn-sm btn-outline-primary" data-ws-edit="<?= ws_h($module) ?>" data-id="<?= (int)$r['id'] ?>">Edit</button> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete record?" data-id="<?= (int)$r['id'] ?>">Delete</button><?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="<?= count($fields)+1 ?>" class="text-center text-muted py-4">No records yet.</td></tr><?php endif; ?></tbody></table></div><?php if($can): ?><div class="modal fade" id="genericModal"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" data-spa-form><input type="hidden" name="module" value="<?= ws_h($module) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0"><div class="modal-header"><h5 class="modal-title">New <?= ws_h($title) ?> Record</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><div class="mb-form-grid"><?php foreach($fields as $col=>$label): ?><label class="<?= in_array($col,['description','notes','file_path'],true)?'full':'' ?>"><?= ws_h($label) ?><?php if(in_array($col,['description','notes'],true)): ?><textarea class="form-control" name="<?= ws_h($col) ?>"></textarea><?php else: ?><input class="form-control" name="<?= ws_h($col) ?>" <?= in_array($col,$moneyCols,true)?'type="number" step="0.01"':'' ?>></label><?php endif; ?></label><?php endforeach; ?></div></div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div><?php endif; ?>
<?php }

function ws_save_estimate(PDO $pdo): void { require_permission($pdo,'manage_estimates'); $id=(int)($_POST['id']??0); $tot=ws_estimate_totals_from_post($_POST); $eno=trim($_POST['estimate_no']??'') ?: ('EST-'.date('Ymd-His')); $data=[($_POST['project_id']?:null),$eno,trim($_POST['title']??''),trim($_POST['client_name']??''),trim($_POST['status']??'draft'),$tot['labor'],$tot['materials'],$tot['equipment'],ws_num($_POST['overhead_cost']??0),ws_num($_POST['markup_percent']??0),ws_num($_POST['tax_percent']??0),$tot['subtotal'],$tot['markup'],$tot['tax'],$tot['grand'],trim($_POST['notes']??''),trim($_POST['project_type']??''),trim($_POST['location']??''),ws_num($_POST['floor_area']??0),(int)($_POST['floors']??1),(int)($_POST['duration_days']??0),($_POST['target_start_date']?:null),($_POST['target_end_date']?:null),ws_num($_POST['professional_fee']??0),ws_num($_POST['permit_fee']??0),ws_num($_POST['mobilization_fee']??0),ws_num($_POST['supervision_fee']??0),ws_num($_POST['contingency_percent']??0),$tot['cont'],ws_num($_POST['discount_amount']??0),ws_num($_POST['target_margin_percent']??15),$tot['profit'],$tot['margin'],$tot['risk']];
  if($id>0){ $sql="UPDATE mb_estimates SET project_id=?,estimate_no=?,title=?,client_name=?,status=?,labor_cost=?,material_cost=?,equipment_cost=?,overhead_cost=?,markup_percent=?,tax_percent=?,subtotal=?,markup_amount=?,tax_amount=?,grand_total=?,notes=?,project_type=?,location=?,floor_area=?,floors=?,duration_days=?,target_start_date=?,target_end_date=?,professional_fee=?,permit_fee=?,mobilization_fee=?,supervision_fee=?,contingency_percent=?,contingency_amount=?,discount_amount=?,target_margin_percent=?,profit_amount=?,profit_margin_percent=?,risk_level=? WHERE id=?"; $pdo->prepare($sql)->execute([...$data,$id]); }
  else { $sql="INSERT INTO mb_estimates (project_id,estimate_no,title,client_name,status,labor_cost,material_cost,equipment_cost,overhead_cost,markup_percent,tax_percent,subtotal,markup_amount,tax_amount,grand_total,notes,project_type,location,floor_area,floors,duration_days,target_start_date,target_end_date,professional_fee,permit_fee,mobilization_fee,supervision_fee,contingency_percent,contingency_amount,discount_amount,target_margin_percent,profit_amount,profit_margin_percent,risk_level,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"; $pdo->prepare($sql)->execute([...$data,(int)$_SESSION['user_id']]); $id=(int)$pdo->lastInsertId(); }
  foreach(['mb_estimate_materials','mb_estimate_labor','mb_estimate_equipment'] as $t){ $pdo->prepare("DELETE FROM $t WHERE estimate_id=?")->execute([$id]); }
  $i=0; foreach(($_POST['materials']??[]) as $r){ if(trim((string)($r['material_name']??''))==='') continue; $line=ws_num($r['quantity']??0)*ws_num($r['unit_cost']??0)*(1+ws_num($r['waste_percent']??0)/100); $pdo->prepare("INSERT INTO mb_estimate_materials (estimate_id,material_name,unit,quantity,unit_cost,waste_percent,supplier,line_total,sort_order) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$id,trim($r['material_name']),trim($r['unit']??''),ws_num($r['quantity']??0),ws_num($r['unit_cost']??0),ws_num($r['waste_percent']??0),trim($r['supplier']??''),$line,$i++]); }
  $i=0; foreach(($_POST['labor']??[]) as $r){ if(trim((string)($r['role_name']??''))==='') continue; $line=ws_num($r['worker_count']??0)*ws_num($r['daily_rate']??0)*ws_num($r['days_count']??0); $pdo->prepare("INSERT INTO mb_estimate_labor (estimate_id,role_name,worker_count,daily_rate,days_count,line_total,sort_order) VALUES (?,?,?,?,?,?,?)")->execute([$id,trim($r['role_name']),ws_num($r['worker_count']??0),ws_num($r['daily_rate']??0),ws_num($r['days_count']??0),$line,$i++]); }
  $i=0; foreach(($_POST['equipment']??[]) as $r){ if(trim((string)($r['equipment_name']??''))==='') continue; $line=ws_num($r['rate']??0)*ws_num($r['duration']??0); $pdo->prepare("INSERT INTO mb_estimate_equipment (estimate_id,equipment_name,rate_type,rate,duration,line_total,sort_order) VALUES (?,?,?,?,?,?,?)")->execute([$id,trim($r['equipment_name']),trim($r['rate_type']??'daily'),ws_num($r['rate']??0),ws_num($r['duration']??0),$line,$i++]); }
  ws_json(['ok'=>true,'message'=>'Estimate saved.']); }
function ws_save_project(PDO $pdo): void { require_permission($pdo,'manage_projects'); $id=(int)($_POST['id']??0); $code=trim($_POST['project_code']??'') ?: ('PRJ-'.date('Ymd-His')); $data=[$code,trim($_POST['name']??''),trim($_POST['client_name']??''),trim($_POST['client_email']??''),trim($_POST['client_phone']??''),trim($_POST['location']??''),trim($_POST['project_type']??''),trim($_POST['status']??'proposed'),max(0,min(100,(int)($_POST['progress_percent']??0))),($_POST['contract_start_date']?:null),($_POST['target_end_date']?:null),ws_num($_POST['estimated_cost']??0),ws_num($_POST['actual_cost']??0),ws_num($_POST['contract_amount']??0),trim($_POST['notes']??''),trim($_POST['site_contact']??''),trim($_POST['priority']??'normal')]; if($id>0){ $pdo->prepare("UPDATE mb_projects SET project_code=?,name=?,client_name=?,client_email=?,client_phone=?,location=?,project_type=?,status=?,progress_percent=?,contract_start_date=?,target_end_date=?,estimated_cost=?,actual_cost=?,contract_amount=?,notes=?,site_contact=?,priority=? WHERE id=?")->execute([...$data,$id]); } else { $pdo->prepare("INSERT INTO mb_projects (project_code,name,client_name,client_email,client_phone,location,project_type,status,progress_percent,contract_start_date,target_end_date,estimated_cost,actual_cost,contract_amount,notes,site_contact,priority,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([...$data,(int)$_SESSION['user_id']]); } ws_json(['ok'=>true,'message'=>'Project saved.']); }
function ws_save_proposal(PDO $pdo): void { require_permission($pdo,'manage_proposals'); $id=(int)($_POST['id']??0); $no=trim($_POST['proposal_no']??'') ?: ('PROP-'.date('Ymd-His')); $data=[($_POST['project_id']?:null),($_POST['estimate_id']?:null),$no,trim($_POST['title']??''),trim($_POST['client_name']??''),trim($_POST['status']??'draft'),ws_num($_POST['amount']??0),($_POST['valid_until']?:null),trim($_POST['scope']??''),trim($_POST['terms']??''),trim($_POST['location']??''),trim($_POST['project_type']??''),trim($_POST['payment_terms']??''),trim($_POST['exclusions']??''),(int)($_POST['timeline_days']??0)]; if($id>0){ $pdo->prepare("UPDATE mb_proposals SET project_id=?,estimate_id=?,proposal_no=?,title=?,client_name=?,status=?,amount=?,valid_until=?,scope=?,terms=?,location=?,project_type=?,payment_terms=?,exclusions=?,timeline_days=? WHERE id=?")->execute([...$data,$id]); } else { $pdo->prepare("INSERT INTO mb_proposals (project_id,estimate_id,proposal_no,title,client_name,status,amount,valid_until,scope,terms,location,project_type,payment_terms,exclusions,timeline_days,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([...$data,(int)$_SESSION['user_id']]); } ws_json(['ok'=>true,'message'=>'Proposal saved.']); }
function ws_approve_proposal(PDO $pdo): void { require_permission($pdo,'manage_proposals'); require_permission($pdo,'manage_projects'); $id=(int)($_POST['id']??0); $s=$pdo->prepare("SELECT p.*, e.estimated_cost,e.subtotal FROM mb_proposals p LEFT JOIN mb_estimates e ON e.id=p.estimate_id WHERE p.id=?"); $s->execute([$id]); $p=$s->fetch(PDO::FETCH_ASSOC); if(!$p) ws_json(['ok'=>false,'message'=>'Proposal not found.'],404); $projectId=(int)($p['project_id']??0); if($projectId<=0){ $code='PRJ-'.date('Ymd-His'); $cost=(float)($p['subtotal']??0); $pdo->prepare("INSERT INTO mb_projects (project_code,name,client_name,location,project_type,status,progress_percent,estimated_cost,actual_cost,contract_amount,notes,created_by) VALUES (?,?,?,?,?,'approved',0,?,0,?,?,?)")->execute([$code,$p['title'],$p['client_name'],$p['location'],$p['project_type'],$cost,(float)$p['amount'],$p['scope'],(int)$_SESSION['user_id']]); $projectId=(int)$pdo->lastInsertId(); }
  $pdo->prepare("UPDATE mb_proposals SET status='approved', project_id=?, approved_at=NOW(), approved_by=? WHERE id=?")->execute([$projectId,(int)$_SESSION['user_id'],$id]); ws_json(['ok'=>true,'message'=>'Proposal approved and project file is ready.']); }


function ws_hr_bootstrap(PDO $pdo): void {
  foreach ([
    'photo_path'=>"VARCHAR(255) NULL", 'birth_date'=>"DATE NULL", 'gender'=>"VARCHAR(32) NULL", 'civil_status'=>"VARCHAR(32) NULL",
    'address'=>"TEXT NULL", 'emergency_contact'=>"VARCHAR(180) NULL", 'emergency_phone'=>"VARCHAR(64) NULL", 'category'=>"VARCHAR(32) NOT NULL DEFAULT 'office'",
    'job_title_id'=>"INT NULL", 'department_id'=>"INT NULL", 'hire_date'=>"DATE NULL", 'salary_rate'=>"DECIMAL(12,2) NOT NULL DEFAULT 0", 'rate_type'=>"VARCHAR(32) NOT NULL DEFAULT 'daily'"
  ] as $c=>$d) ws_add_col($pdo,'mb_employees',$c,$d);
  foreach ([
    'late_minutes'=>"INT NOT NULL DEFAULT 0",
    'overtime_hours'=>"DECIMAL(8,2) NOT NULL DEFAULT 0",
    'payable_day'=>"DECIMAL(5,2) NOT NULL DEFAULT 0",
    'regular_hours'=>"DECIMAL(8,2) NOT NULL DEFAULT 0",
    'worked_hours'=>"DECIMAL(8,2) NOT NULL DEFAULT 0"
  ] as $c=>$d) ws_add_col($pdo,'mb_attendance',$c,$d);
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_job_titles (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, department_id INT NULL, category VARCHAR(32) NOT NULL DEFAULT 'office', rate_type VARCHAR(32) NOT NULL DEFAULT 'daily', salary_rate DECIMAL(12,2) NOT NULL DEFAULT 0, description TEXT NULL, status VARCHAR(32) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_departments (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, category VARCHAR(32) NOT NULL DEFAULT 'office', manager_name VARCHAR(180) NULL, description TEXT NULL, status VARCHAR(32) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_employee_documents (id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, document_type VARCHAR(80) NOT NULL, document_title VARCHAR(180) NULL, file_path VARCHAR(255) NULL, issue_date DATE NULL, expiry_date DATE NULL, status VARCHAR(32) NOT NULL DEFAULT 'submitted', notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_emp_docs(employee_id), FOREIGN KEY(employee_id) REFERENCES mb_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_attendance_settings (id INT PRIMARY KEY DEFAULT 1, work_start TIME NOT NULL DEFAULT '08:00:00', work_end TIME NOT NULL DEFAULT '17:00:00', late_grace_minutes INT NOT NULL DEFAULT 15, overtime_after_hours DECIMAL(5,2) NOT NULL DEFAULT 8, overtime_rate_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.25, absent_no_timein TINYINT(1) NOT NULL DEFAULT 1, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("INSERT IGNORE INTO mb_attendance_settings (id) VALUES (1)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_payroll_periods (id INT AUTO_INCREMENT PRIMARY KEY, period_start DATE NOT NULL, period_end DATE NOT NULL, title VARCHAR(180) NULL, status VARCHAR(32) NOT NULL DEFAULT 'draft', gross_pay DECIMAL(14,2) NOT NULL DEFAULT 0, notes TEXT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS mb_payroll_items (id INT AUTO_INCREMENT PRIMARY KEY, payroll_id INT NOT NULL, employee_id INT NOT NULL, base_rate DECIMAL(12,2) NOT NULL DEFAULT 0, present_days DECIMAL(8,2) NOT NULL DEFAULT 0, late_days DECIMAL(8,2) NOT NULL DEFAULT 0, absent_days DECIMAL(8,2) NOT NULL DEFAULT 0, overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0, gross_pay DECIMAL(14,2) NOT NULL DEFAULT 0, notes TEXT NULL, FOREIGN KEY(payroll_id) REFERENCES mb_payroll_periods(id) ON DELETE CASCADE, FOREIGN KEY(employee_id) REFERENCES mb_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  foreach ([
    'standard_hours_per_day'=>"DECIMAL(5,2) NOT NULL DEFAULT 8.00",
    'monthly_working_days'=>"DECIMAL(5,2) NOT NULL DEFAULT 26.00",
    'weekly_working_days'=>"DECIMAL(5,2) NOT NULL DEFAULT 6.00",
    'half_day_hours'=>"DECIMAL(5,2) NOT NULL DEFAULT 4.00",
    'deduct_late_from_pay'=>"TINYINT(1) NOT NULL DEFAULT 1",
    'allow_overtime'=>"TINYINT(1) NOT NULL DEFAULT 1",
    'holiday_pay_multiplier'=>"DECIMAL(5,2) NOT NULL DEFAULT 1.00",
    'rest_day_pay_multiplier'=>"DECIMAL(5,2) NOT NULL DEFAULT 1.30"
  ] as $c=>$d) ws_add_col($pdo,'mb_attendance_settings',$c,$d);
  foreach ([
    'rate_type'=>"VARCHAR(32) NOT NULL DEFAULT 'daily'",
    'daily_rate'=>"DECIMAL(12,2) NOT NULL DEFAULT 0",
    'hourly_rate'=>"DECIMAL(12,2) NOT NULL DEFAULT 0",
    'payable_days'=>"DECIMAL(8,2) NOT NULL DEFAULT 0",
    'regular_hours'=>"DECIMAL(8,2) NOT NULL DEFAULT 0",
    'worked_hours'=>"DECIMAL(8,2) NOT NULL DEFAULT 0",
    'regular_pay'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",
    'overtime_pay'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",
    'late_deduction'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",
    'deductions'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",
    'net_pay'=>"DECIMAL(14,2) NOT NULL DEFAULT 0",
    'computation_notes'=>"TEXT NULL"
  ] as $c=>$d) ws_add_col($pdo,'mb_payroll_items',$c,$d);
  if(!ws_index_exists($pdo,'mb_payroll_items','uniq_mb_payroll_employee')){
    $pdo->exec("ALTER TABLE mb_payroll_items ADD UNIQUE KEY uniq_mb_payroll_employee (payroll_id, employee_id)");
  }
}
ws_hr_bootstrap($pdo);
function ws_upload_file(string $field, string $sub='employees'): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $base = dirname(__DIR__) . '/storage/uploads/' . $sub;
    if (!is_dir($base)) {
        @mkdir($base, 0775, true);
    }

    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($_FILES[$field]['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'], true)) {
        return null;
    }

    $fn = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $name;
    $dest = $base . '/' . $fn;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return 'storage/uploads/' . $sub . '/' . $fn;
    }

    return null;
}
function ws_media_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, 'uploads/')) {
        $path = 'storage/' . $path;
    }
    return ltrim($path, '/');
}
function ws_avatar_svg(string $name): string { $initials=''; foreach(preg_split('/\s+/',trim($name)) ?: [] as $part){ if($part!=='') $initials.=strtoupper(substr($part,0,1)); if(strlen($initials)>=2) break; } $initials=$initials!=='' ? substr($initials,0,2) : 'MB'; $safeName=htmlspecialchars($name,ENT_QUOTES,'UTF-8'); $safeInitials=htmlspecialchars($initials,ENT_QUOTES,'UTF-8'); $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" role="img" aria-label="'.$safeName.'"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#dbeafe"/><stop offset="100%" stop-color="#bfdbfe"/></linearGradient></defs><rect width="96" height="96" rx="48" fill="url(#g)"/><text x="50%" y="54%" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700" fill="#0f2748">'.$safeInitials.'</text></svg>'; return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg); }
function ws_employee_avatar_src(string $name, ?string $photoPath): string { $photo=ws_media_url($photoPath); return $photo!=='' ? $photo : ws_avatar_svg($name); }
function ws_doc_labels(): array { return ['birth_certificate'=>'Birth Certificate','nbi'=>'NBI Clearance','police_clearance'=>'Police Clearance','medical'=>'Medical Certificate','national_id'=>'National ID','license'=>'License']; }
function ws_status_options(): array { return ['active'=>'Active','probationary'=>'Probationary','on_leave'=>'On Leave','inactive'=>'Inactive','terminated'=>'Terminated']; }
function ws_emp_categories(): array { return ['office'=>'Office','field'=>'Field']; }
function ws_departments(PDO $pdo): array { return $pdo->query("SELECT * FROM mb_departments WHERE status='active' ORDER BY category,name")->fetchAll(PDO::FETCH_ASSOC); }
function ws_job_titles(PDO $pdo): array { return $pdo->query("SELECT jt.*,d.name department_name FROM mb_job_titles jt LEFT JOIN mb_departments d ON d.id=jt.department_id WHERE jt.status='active' ORDER BY jt.category,jt.title")->fetchAll(PDO::FETCH_ASSOC); }
function ws_fetch_employee(PDO $pdo,int $id): array { $s=$pdo->prepare("SELECT e.*,jt.title job_title_name,jt.salary_rate job_rate,jt.rate_type job_rate_type,d.name department_name FROM mb_employees e LEFT JOIN mb_job_titles jt ON jt.id=e.job_title_id LEFT JOIN mb_departments d ON d.id=e.department_id WHERE e.id=?"); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: []; }
function ws_employee_docs(PDO $pdo,int $id): array { $s=$pdo->prepare("SELECT * FROM mb_employee_documents WHERE employee_id=? ORDER BY document_type,id DESC"); $s->execute([$id]); return $s->fetchAll(PDO::FETCH_ASSOC); }
function ws_employee_attendance_records(PDO $pdo,int $id): array { $s=$pdo->prepare("SELECT attendance_date,time_in,time_out,status,late_minutes,worked_hours,regular_hours,overtime_hours,payable_day,notes FROM mb_attendance WHERE employee_id=? ORDER BY attendance_date DESC,id DESC LIMIT 24"); $s->execute([$id]); return $s->fetchAll(PDO::FETCH_ASSOC); }
function ws_employee_payroll_records(PDO $pdo,int $id): array { $s=$pdo->prepare("SELECT pi.*,pp.period_start,pp.period_end,pp.title,pp.status AS period_status,pp.created_at FROM mb_payroll_items pi INNER JOIN mb_payroll_periods pp ON pp.id=pi.payroll_id WHERE pi.employee_id=? ORDER BY pp.period_end DESC,pp.id DESC LIMIT 24"); $s->execute([$id]); return $s->fetchAll(PDO::FETCH_ASSOC); }
function ws_age_label(?string $birthDate): string { if(!$birthDate) return ''; try{ $dob=new DateTime($birthDate); $now=new DateTime('today'); return (string)$dob->diff($now)->y; }catch(Throwable $e){ return ''; } }
function ws_employee_rate_label(array $e): string { $rate=(float)($e['salary_rate'] ?: $e['daily_rate']); if($rate<=0) return 'Rate not set'; $type=trim((string)($e['rate_type'] ?? 'daily')); return ws_money($rate).' / '.ws_h($type); }
function ws_doc_status_class(?string $status, bool $hasFile): string { if(!$hasFile) return 'missing'; return match((string)$status){ 'verified','submitted' => 'submitted', 'expired' => 'expired', default => 'pending', }; }
function ws_doc_status_label(?string $status, bool $hasFile): string { if(!$hasFile) return 'Missing'; return ucfirst((string)($status ?: 'Pending')); }
function ws_attendance_status_label(?string $status): string { return ucfirst(str_replace('_',' ',(string)($status ?: 'present'))); }
function ws_attendance_settings(PDO $pdo): array {
  $settings=$pdo->query("SELECT * FROM mb_attendance_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
  return array_merge([
    'work_start'=>'08:00:00',
    'work_end'=>'17:00:00',
    'late_grace_minutes'=>15,
    'overtime_after_hours'=>8,
    'overtime_rate_multiplier'=>1.25,
    'absent_no_timein'=>1,
    'standard_hours_per_day'=>8,
    'monthly_working_days'=>26,
    'weekly_working_days'=>6,
    'half_day_hours'=>4,
    'deduct_late_from_pay'=>1,
    'allow_overtime'=>1,
    'holiday_pay_multiplier'=>1,
    'rest_day_pay_multiplier'=>1.3,
  ], $settings);
}
function ws_compute_attendance_day(array $attendance, array $settings): array {
  $status=strtolower(trim((string)($attendance['status'] ?? 'present'))) ?: 'present';
  $status=$status==='leave' ? 'paid_leave' : $status;
  if(!isset(ws_attendance_status_options()[$status])) $status='present';
  $timeIn=(string)($attendance['time_in'] ?? '');
  $timeOut=(string)($attendance['time_out'] ?? '');
  $lateMinutes=max(0, (int)($attendance['late_minutes'] ?? 0));
  $workedHours=ws_num($attendance['worked_hours'] ?? 0);
  $regularHours=ws_num($attendance['regular_hours'] ?? 0);
  $overtimeHours=ws_num($attendance['overtime_hours'] ?? 0);
  $payableDay=ws_num($attendance['payable_day'] ?? 0);
  $startMinutes=ws_time_to_minutes((string)$settings['work_start']);
  $inMinutes=ws_time_to_minutes($timeIn);
  $outMinutes=ws_time_to_minutes($timeOut);
  if($inMinutes !== null && $outMinutes !== null){
    if($outMinutes < $inMinutes) $outMinutes += 24 * 60;
    $workedHours=round(max(0, $outMinutes - $inMinutes) / 60, 2);
  }
  if(($status==='present' || $status==='late') && $inMinutes !== null && $startMinutes !== null){
    $lateMinutes=max(0, $inMinutes - ($startMinutes + (int)$settings['late_grace_minutes']));
  }
  $payableDay=match($status){
    'present','late','paid_leave' => 1.0,
    'half_day' => 0.5,
    'absent','unpaid_leave','rest_day','holiday','suspended' => 0.0,
    default => 1.0,
  };
  if(($status==='present' || $status==='late') && !empty($settings['absent_no_timein']) && $inMinutes===null){
    $status='absent';
    $payableDay=0.0;
  }
  $standardHours=max(0.0, ws_num($settings['standard_hours_per_day'] ?? 8));
  $halfDayHours=max(0.0, ws_num($settings['half_day_hours'] ?? 4));
  if($status==='half_day'){
    $regularHours=min($workedHours > 0 ? $workedHours : $halfDayHours, $halfDayHours);
    $overtimeHours=0;
  } elseif($status==='rest_day' || $status==='holiday'){
    $regularHours=0;
    if(!empty($settings['allow_overtime']) && $workedHours > 0){
      $overtimeHours=max($overtimeHours, $workedHours);
    } else {
      $overtimeHours=0;
    }
  } elseif(in_array($status,['absent','unpaid_leave','suspended'],true)){
    $regularHours=0;
    $workedHours=0;
    $overtimeHours=0;
    $lateMinutes=0;
  } else {
    $regularHours=min($workedHours, $standardHours);
    if(!empty($settings['allow_overtime'])){
      $overtimeHours=max($overtimeHours, round(max(0, $workedHours - ws_num($settings['overtime_after_hours'] ?? $standardHours)), 2));
    } else {
      $overtimeHours=0;
    }
  }
  return [
    'status'=>$status,
    'time_in'=>$timeIn !== '' ? $timeIn : null,
    'time_out'=>$timeOut !== '' ? $timeOut : null,
    'late_minutes'=>$lateMinutes,
    'worked_hours'=>round(max(0, $workedHours), 2),
    'regular_hours'=>round(max(0, $regularHours), 2),
    'overtime_hours'=>round(max(0, $overtimeHours), 2),
    'payable_day'=>round(max(0, $payableDay), 2),
  ];
}
function ws_employee_rate_breakdown(array $employee, array $settings): array {
  $rateType=strtolower(trim((string)($employee['rate_type'] ?? 'daily'))) ?: 'daily';
  if(!isset(ws_rate_type_options()[$rateType])) $rateType='daily';
  $baseRate=ws_num($employee['salary_rate'] ?? ($employee['daily_rate'] ?? 0));
  $standardHours=max(0.01, ws_num($settings['standard_hours_per_day'] ?? 8));
  $monthlyDays=max(0.01, ws_num($settings['monthly_working_days'] ?? 26));
  $weeklyDays=max(0.01, ws_num($settings['weekly_working_days'] ?? 6));
  $dailyRate=0.0;
  $hourlyRate=0.0;
  $notes=[];
  if($rateType==='daily'){
    $dailyRate=$baseRate;
    $hourlyRate=$dailyRate / $standardHours;
    $notes[]='Daily rate used directly from employee salary rate.';
  } elseif($rateType==='weekly'){
    $dailyRate=$baseRate / $weeklyDays;
    $hourlyRate=$dailyRate / $standardHours;
    $notes[]='Weekly salary converted using weekly working days.';
  } elseif($rateType==='monthly'){
    $dailyRate=$baseRate / $monthlyDays;
    $hourlyRate=$dailyRate / $standardHours;
    $notes[]='Monthly salary converted using monthly working days.';
  } elseif($rateType==='hourly'){
    $hourlyRate=$baseRate;
    $dailyRate=$hourlyRate * $standardHours;
    $notes[]='Hourly rate used directly from employee salary rate.';
  } else {
    $notes[]='Project rate stays manual and attendance is informational only.';
  }
  return ['rate_type'=>$rateType,'base_rate'=>round($baseRate,2),'daily_rate'=>round($dailyRate,2),'hourly_rate'=>round($hourlyRate,2),'notes'=>$notes];
}
function ws_compute_employee_payroll(PDO $pdo, array $employee, string $start, string $end): array {
  $settings=ws_attendance_settings($pdo);
  $rate=ws_employee_rate_breakdown($employee, $settings);
  $stmt=$pdo->prepare("SELECT * FROM mb_attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date ASC");
  $stmt->execute([(int)$employee['id'],$start,$end]);
  $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $totals=['present_days'=>0.0,'late_days'=>0.0,'absent_days'=>0.0,'payable_days'=>0.0,'regular_hours'=>0.0,'worked_hours'=>0.0,'overtime_hours'=>0.0,'late_minutes'=>0,'regular_pay'=>0.0,'overtime_pay'=>0.0,'late_deduction'=>0.0,'deductions'=>0.0,'gross_pay'=>0.0,'net_pay'=>0.0];
  $notes=$rate['notes'];
  foreach($rows as $row){
    $day=ws_compute_attendance_day($row, $settings);
    $status=$day['status'];
    if($status==='present') $totals['present_days'] += 1;
    if($status==='late'){ $totals['present_days'] += 1; $totals['late_days'] += 1; }
    if($status==='half_day') $totals['present_days'] += 0.5;
    if(in_array($status,['absent','unpaid_leave','suspended'],true)) $totals['absent_days'] += 1;
    $totals['payable_days'] += $day['payable_day'];
    $totals['regular_hours'] += $day['regular_hours'];
    $totals['worked_hours'] += $day['worked_hours'];
    $totals['overtime_hours'] += $day['overtime_hours'];
    $totals['late_minutes'] += (int)$day['late_minutes'];
  }
  $overtimeMultiplier=ws_num($settings['overtime_rate_multiplier'] ?? 1.25);
  $lateHours=round($totals['late_minutes'] / 60, 2);
  if($rate['rate_type']==='daily'){
    $totals['regular_pay']=$rate['daily_rate'] * $totals['payable_days'];
    $totals['overtime_pay']=$rate['hourly_rate'] * $totals['overtime_hours'] * $overtimeMultiplier;
    $totals['late_deduction']=!empty($settings['deduct_late_from_pay']) ? ($rate['hourly_rate'] * $lateHours) : 0.0;
  } elseif($rate['rate_type']==='weekly'){
    $totals['regular_pay']=$rate['daily_rate'] * $totals['payable_days'];
    $totals['overtime_pay']=$rate['hourly_rate'] * $totals['overtime_hours'] * $overtimeMultiplier;
    $totals['late_deduction']=!empty($settings['deduct_late_from_pay']) ? ($rate['hourly_rate'] * $lateHours) : 0.0;
  } elseif($rate['rate_type']==='monthly'){
    $totals['regular_pay']=$rate['daily_rate'] * $totals['payable_days'];
    $totals['overtime_pay']=$rate['hourly_rate'] * $totals['overtime_hours'] * $overtimeMultiplier;
    $totals['late_deduction']=!empty($settings['deduct_late_from_pay']) ? ($rate['hourly_rate'] * $lateHours) : 0.0;
  } elseif($rate['rate_type']==='hourly'){
    $totals['regular_pay']=$rate['hourly_rate'] * $totals['regular_hours'];
    $totals['overtime_pay']=$rate['hourly_rate'] * $totals['overtime_hours'] * $overtimeMultiplier;
    $totals['late_deduction']=(!empty($settings['deduct_late_from_pay']) && $totals['worked_hours'] >= $totals['regular_hours']) ? ($rate['hourly_rate'] * $lateHours) : 0.0;
  } else {
    $notes[]='Project-based employee left at manual payroll amount 0.00.';
  }
  $totals['deductions']=round($totals['late_deduction'],2);
  $totals['gross_pay']=round(max(0, $totals['regular_pay'] + $totals['overtime_pay'] - $totals['late_deduction']),2);
  $totals['net_pay']=round(max(0, $totals['gross_pay'] - $totals['deductions']),2);
  $notes[]='Payable days computed from attendance status rules between '.$start.' and '.$end.'.';
  $notes[]='Present '.$totals['present_days'].', late '.$totals['late_days'].', absent '.$totals['absent_days'].', payable '.$totals['payable_days'].'.';
  return array_merge([
    'employee_id'=>(int)$employee['id'],
    'employee_code'=>(string)($employee['employee_code'] ?? ''),
    'full_name'=>(string)($employee['full_name'] ?? ''),
    'rate_type'=>$rate['rate_type'],
    'base_rate'=>$rate['base_rate'],
    'daily_rate'=>$rate['daily_rate'],
    'hourly_rate'=>$rate['hourly_rate'],
    'computation_notes'=>implode("\n",$notes),
  ], array_map(fn($value)=>is_float($value) ? round($value,2) : $value, $totals));
}
function ws_rebuild_payroll_items(PDO $pdo, int $payrollId): void {
  $periodStmt=$pdo->prepare("SELECT * FROM mb_payroll_periods WHERE id=?");
  $periodStmt->execute([$payrollId]);
  $period=$periodStmt->fetch(PDO::FETCH_ASSOC);
  if(!$period) throw new RuntimeException('Payroll period not found.');
  $pdo->prepare("DELETE FROM mb_payroll_items WHERE payroll_id=?")->execute([$payrollId]);
  $employees=$pdo->query("SELECT * FROM mb_employees WHERE status IN ('active','probationary') ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
  $insert=$pdo->prepare("INSERT INTO mb_payroll_items (payroll_id,employee_id,base_rate,present_days,late_days,absent_days,overtime_hours,gross_pay,notes,rate_type,daily_rate,hourly_rate,payable_days,regular_hours,worked_hours,regular_pay,overtime_pay,late_deduction,deductions,net_pay,computation_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $gross=0.0;
  foreach($employees as $employee){
    $item=ws_compute_employee_payroll($pdo,$employee,(string)$period['period_start'],(string)$period['period_end']);
    $insert->execute([$payrollId,$item['employee_id'],$item['base_rate'],$item['present_days'],$item['late_days'],$item['absent_days'],$item['overtime_hours'],$item['gross_pay'],$item['computation_notes'],$item['rate_type'],$item['daily_rate'],$item['hourly_rate'],$item['payable_days'],$item['regular_hours'],$item['worked_hours'],$item['regular_pay'],$item['overtime_pay'],$item['late_deduction'],$item['deductions'],$item['net_pay'],$item['computation_notes']]);
    $gross += (float)$item['gross_pay'];
  }
  $pdo->prepare("UPDATE mb_payroll_periods SET gross_pay=? WHERE id=?")->execute([round($gross,2),$payrollId]);
}

function ws_render_jobtitles(PDO $pdo,string $q=''): void { require_permission($pdo,'manage_employees'); $rows=$pdo->query("SELECT jt.*,d.name department_name FROM mb_job_titles jt LEFT JOIN mb_departments d ON d.id=jt.department_id ORDER BY jt.updated_at DESC,jt.id DESC")->fetchAll(PDO::FETCH_ASSOC); $deps=ws_departments($pdo); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0">Job Titles & Salary Rates</h5><div class="text-muted small">Create reusable job titles so employee forms use dropdowns instead of manual salary typing.</div></div><button class="btn btn-primary btn-sm" data-workspace-open="jobTitleModal">New Job Title</button></div>
<div class="table-responsive workspace-table-wrap"><table class="table table-hover workspace-table"><thead><tr><th>Title</th><th>Category</th><th>Department</th><th>Rate Type</th><th>Salary Rate</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong><?=ws_h($r['title'])?></strong><div class="text-muted small"><?=ws_h($r['description'])?></div></td><td><?=ws_h(ucfirst($r['category']))?></td><td><?=ws_h($r['department_name']??'')?></td><td><?=ws_h(ucfirst($r['rate_type']))?></td><td><?=ws_money($r['salary_rate'])?></td><td><span class="badge text-bg-light border"><?=ws_h($r['status'])?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-ws-edit="job_titles" data-id="<?= (int)$r['id']?>">Edit</button> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete job title?" data-id="<?= (int)$r['id']?>">Delete</button></td></tr><?php endforeach; ?></tbody></table></div>
<div class="modal fade" id="jobTitleModal"><div class="modal-dialog"><form class="modal-content" data-spa-form><input type="hidden" name="module" value="job_titles"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0"><div class="modal-header"><h5 class="modal-title">New Job Title</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-form-grid single"><label>Title<input required class="form-control" name="title" placeholder="Project Engineer, Foreman, Mason"></label><label>Category<select class="form-select" name="category"><option value="office">Office</option><option value="field">Field</option></select></label><label>Department<select class="form-select" name="department_id"><option value="">No Department</option><?php foreach($deps as $d): ?><option value="<?= (int)$d['id']?>"><?=ws_h($d['name'])?></option><?php endforeach; ?></select></label><label>Rate Type<select class="form-select" name="rate_type"><?php foreach(ws_rate_type_options() as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></label><label>Salary Rate<input class="form-control" type="number" step="0.01" name="salary_rate" value="0"></label><label>Status<select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><label class="full">Description<textarea class="form-control" name="description"></textarea></label></div></div><div class="modal-footer"><button class="btn btn-primary">Save Job Title</button></div></form></div></div><?php }
function ws_render_department_modal(PDO $pdo, ?array $r=null): void { $is=!empty($r['id']); ?><div class="modal fade" id="departmentModal"><div class="modal-dialog"><form class="modal-content" data-spa-form><input type="hidden" name="module" value="departments"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($r['id']??0)?>"><div class="modal-header"><h5 class="modal-title"><?= $is?'Edit':'New'?> Department</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-form-grid single"><label>Department Name<input required class="form-control" name="name" value="<?=ws_h($r['name']??'')?>"></label><label>Category<select class="form-select" name="category"><option value="office" <?=($r['category']??'')==='office'?'selected':''?>>Office</option><option value="field" <?=($r['category']??'')==='field'?'selected':''?>>Field</option></select></label><label>Manager / Lead<input class="form-control" name="manager_name" value="<?=ws_h($r['manager_name']??'')?>"></label><label>Status<select class="form-select" name="status"><option value="active" <?=($r['status']??'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($r['status']??'')==='inactive'?'selected':''?>>Inactive</option></select></label><label class="full">Description<textarea class="form-control" name="description"><?=ws_h($r['description']??'')?></textarea></label></div></div><div class="modal-footer"><button class="btn btn-primary">Save Department</button></div></form></div></div><?php }
function ws_render_departments(PDO $pdo,string $q=''): void { require_permission($pdo,'manage_employees'); $rows=$pdo->query("SELECT * FROM mb_departments ORDER BY category,name")->fetchAll(PDO::FETCH_ASSOC); ?><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0">Departments</h5><div class="text-muted small">Admin-created dropdowns for office and field employee records.</div></div><button class="btn btn-primary btn-sm" data-workspace-open="departmentModal">New Department</button></div><div class="row g-3"><?php foreach($rows as $r): ?><div class="col-md-4"><div class="workspace-section-card h-100"><div class="d-flex justify-content-between"><h6><?=ws_h($r['name'])?></h6><span class="badge text-bg-light border"><?=ws_h(ucfirst($r['category']))?></span></div><div class="text-muted small mb-2">Manager: <?=ws_h($r['manager_name']?:'Not assigned')?></div><p class="small mb-3"><?=ws_h($r['description'])?></p><button class="btn btn-sm btn-outline-primary" data-ws-edit="departments" data-id="<?= (int)$r['id']?>">Edit</button> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete department?" data-id="<?= (int)$r['id']?>">Delete</button></div></div><?php endforeach; ?></div><?php ws_render_department_modal($pdo); }
function ws_render_jobtitle_modal(PDO $pdo, ?array $r=null): void { $deps=ws_departments($pdo); $is=!empty($r['id']); ?><div class="modal fade" id="jobTitleEditModal"><div class="modal-dialog"><form class="modal-content" data-spa-form><input type="hidden" name="module" value="job_titles"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($r['id']??0)?>"><div class="modal-header"><h5 class="modal-title"><?= $is?'Edit':'New'?> Job Title</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-form-grid single"><label>Title<input required class="form-control" name="title" value="<?=ws_h($r['title']??'')?>"></label><label>Category<select class="form-select" name="category"><option value="office" <?=($r['category']??'')==='office'?'selected':''?>>Office</option><option value="field" <?=($r['category']??'')==='field'?'selected':''?>>Field</option></select></label><label>Department<select class="form-select" name="department_id"><option value="">No Department</option><?php foreach($deps as $d): ?><option value="<?= (int)$d['id']?>" <?=((int)($r['department_id']??0)===(int)$d['id']?'selected':'')?>><?=ws_h($d['name'])?></option><?php endforeach; ?></select></label><label>Rate Type<select class="form-select" name="rate_type"><?php foreach(ws_rate_type_options() as $k=>$v): ?><option value="<?=$k?>" <?=($r['rate_type']??'daily')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label><label>Salary Rate<input class="form-control" type="number" step="0.01" name="salary_rate" value="<?=ws_h($r['salary_rate']??0)?>"></label><label>Status<select class="form-select" name="status"><option value="active" <?=($r['status']??'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($r['status']??'')==='inactive'?'selected':''?>>Inactive</option></select></label><label class="full">Description<textarea class="form-control" name="description"><?=ws_h($r['description']??'')?></textarea></label></div></div><div class="modal-footer"><button class="btn btn-primary">Save Job Title</button></div></form></div></div><?php }

function ws_render_employee_modal(PDO $pdo, ?array $e=null): void { $is=!empty($e['id']); $deps=ws_departments($pdo); $jobs=ws_job_titles($pdo); $docs=$is?ws_employee_docs($pdo,(int)$e['id']):[]; $docHave=[]; foreach($docs as $d){$docHave[$d['document_type']]=$d;} ?>
<div class="modal fade" id="employeeResumeModal"><div class="modal-dialog modal-xl"><form class="modal-content employee-resume-modal" data-spa-form enctype="multipart/form-data"><input type="hidden" name="module" value="employees"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($e['id']??0)?>"><div class="modal-header"><div><h5 class="modal-title"><?= $is?'Edit':'New'?> Employee Resume File</h5><div class="text-muted small">Complete HR profile, job title salary dropdown, documents, printable profile, and ID generation.</div></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="employee-resume-grid"><aside class="employee-photo-panel"><div class="employee-photo-preview"><?= !empty($e['photo_path']) ? '<span class="employee-photo-preview-label">'.ws_h(basename((string)$e['photo_path'])).'</span>' : '<span>Photo</span>' ?></div><input class="form-control form-control-sm" type="file" accept="image/*" name="photo" data-photo-input><small class="text-muted">Upload 1x1 or portrait photo for resume and ID.</small><hr><label>Employee Code<input class="form-control" name="employee_code" value="<?=ws_h($e['employee_code']??'')?>" placeholder="Auto/manual"></label><label>Status<select class="form-select" name="status"><?php foreach(ws_status_options() as $k=>$v):?><option value="<?=$k?>" <?=($e['status']??'active')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label><label>Category<select class="form-select" name="category"><option value="office" <?=($e['category']??'office')==='office'?'selected':''?>>Office</option><option value="field" <?=($e['category']??'')==='field'?'selected':''?>>Field</option></select></label></aside><main><section><h6>Personal Information</h6><div class="mb-form-grid"><label>Full Name<input required class="form-control" name="full_name" value="<?=ws_h($e['full_name']??'')?>"></label><label>Birth Date<input class="form-control" type="date" name="birth_date" value="<?=ws_h($e['birth_date']??'')?>"></label><label>Gender<select class="form-select" name="gender"><option value="">Select</option><?php foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $k=>$v):?><option value="<?=$k?>" <?=($e['gender']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label><label>Civil Status<select class="form-select" name="civil_status"><option value="">Select</option><?php foreach(['single'=>'Single','married'=>'Married','widowed'=>'Widowed','separated'=>'Separated'] as $k=>$v):?><option value="<?=$k?>" <?=($e['civil_status']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label><label>Phone<input class="form-control" name="phone" value="<?=ws_h($e['phone']??'')?>"></label><label>Email<input class="form-control" name="email" value="<?=ws_h($e['email']??'')?>"></label><label class="full">Address<textarea class="form-control" name="address" rows="2"><?=ws_h($e['address']??'')?></textarea></label><label>Emergency Contact<input class="form-control" name="emergency_contact" value="<?=ws_h($e['emergency_contact']??'')?>"></label><label>Emergency Phone<input class="form-control" name="emergency_phone" value="<?=ws_h($e['emergency_phone']??'')?>"></label></div></section><section><h6>Employment Details</h6><div class="mb-form-grid"><label>Department<select class="form-select" name="department_id"><option value="">Select Department</option><?php foreach($deps as $d):?><option value="<?= (int)$d['id']?>" data-category="<?=ws_h($d['category'])?>" <?=((int)($e['department_id']??0)===(int)$d['id']?'selected':'')?>><?=ws_h(ucfirst($d['category']).' · '.$d['name'])?></option><?php endforeach;?></select></label><label>Job Title<select class="form-select" name="job_title_id" data-job-title-select><option value="">Select Job Title</option><?php foreach($jobs as $j):?><option value="<?= (int)$j['id']?>" data-rate="<?=ws_h($j['salary_rate'])?>" data-rate-type="<?=ws_h($j['rate_type'])?>" data-category="<?=ws_h($j['category'])?>" data-department="<?= (int)$j['department_id']?>" <?=((int)($e['job_title_id']??0)===(int)$j['id']?'selected':'')?>><?=ws_h(ucfirst($j['category']).' · '.$j['title'].' · '.mb_money($j['salary_rate']).' / '.$j['rate_type'])?></option><?php endforeach;?></select></label><label>Manual Job Title<input class="form-control" name="job_title" value="<?=ws_h($e['job_title']??'')?>" placeholder="Optional override"></label><label>Employee Type<input class="form-control" name="employee_type" value="<?=ws_h($e['employee_type']??'')?>" placeholder="Regular, Contractual, Project-based"></label><label>Hire Date<input class="form-control" type="date" name="hire_date" value="<?=ws_h($e['hire_date']??'')?>"></label><label>Rate Type<select class="form-select" name="rate_type"><?php foreach(ws_rate_type_options() as $k=>$v): ?><option value="<?=$k?>" <?=($e['rate_type']??'daily')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label><label>Salary Rate<input class="form-control" type="number" step="0.01" name="salary_rate" value="<?=ws_h($e['salary_rate']??($e['daily_rate']??0))?>"></label><label class="full">Notes<textarea class="form-control" name="notes" rows="3"><?=ws_h($e['notes']??'')?></textarea></label></div></section><section><h6>Required Documentation</h6><div class="employee-doc-grid"><?php foreach(ws_doc_labels() as $key=>$label): $existing=$docHave[$key]??null; ?><div class="doc-tile <?= $existing?'has-doc':''?>"><label class="form-check"><input class="form-check-input" type="checkbox" name="doc_required[]" value="<?=$key?>" <?= $existing?'checked':''?>> <span class="form-check-label"><?=$label?></span></label><?php if($existing):?><a class="small" target="_blank" href="<?=ws_h($existing['file_path'])?>">View uploaded file</a><?php endif;?><input class="form-control form-control-sm" type="file" name="doc_<?=$key?>" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"><select class="form-select form-select-sm" name="doc_status[<?=$key?>]"><option value="pending">Pending</option><option value="submitted" <?=($existing['status']??'')==='submitted'?'selected':''?>>Submitted</option><option value="verified" <?=($existing['status']??'')==='verified'?'selected':''?>>Verified</option><option value="expired" <?=($existing['status']??'')==='expired'?'selected':''?>>Expired</option></select></div><?php endforeach;?></div></section></main></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Employee</button></div></form></div></div><?php }
function ws_render_employee_view(PDO $pdo,int $id): void {
  $e=ws_fetch_employee($pdo,$id);
  if(!$e){ echo '<div class="alert alert-warning">Employee not found.</div>'; return; }
  $docs=ws_employee_docs($pdo,$id);
  $docMap=[];
  foreach($docs as $d){ if(!isset($docMap[$d['document_type']])) $docMap[$d['document_type']]=$d; }
  $attendanceRows=ws_employee_attendance_records($pdo,$id);
  $payrollRows=ws_employee_payroll_records($pdo,$id);
  $canEdit=current_user_can($pdo,'manage_employees');
  $age=ws_age_label($e['birth_date']??null);
  $statusKey=(string)($e['status'] ?? 'active');
  $statusLabel=ws_status_options()[$statusKey] ?? ucfirst(str_replace('_',' ',$statusKey));
  $jobTitle=trim((string)($e['job_title_name'] ?: $e['job_title']));
  $department=trim((string)($e['department_name'] ?: $e['department']));
  $category=ucfirst((string)($e['category'] ?: 'office'));
  ?>
<div class="employee-profile-shell employee-view-print">
  <div class="employee-profile-main">
    <div class="employee-profile-head">
      <div>
        <h3 class="employee-profile-title">Employee Profile</h3>
        <div class="employee-profile-breadcrumb">Employees <span>/</span> Profile</div>
      </div>
      <div class="employee-profile-actions">
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
        <?php if($canEdit): ?><button class="btn btn-warning text-white" type="button" data-employee-edit-toggle>Edit Profile</button><?php endif; ?>
      </div>
    </div>

    <section class="employee-hero-card">
      <div class="employee-hero-media">
        <div class="employee-photo-preview employee-photo-preview-square employee-hero-avatar">
          <?php if(!empty($e['photo_path'])): ?><img src="<?=ws_h(ws_media_url((string)$e['photo_path']))?>" alt="photo"><?php else: ?><span>Photo</span><?php endif; ?>
        </div>
      </div>
      <div class="employee-hero-copy">
        <span class="employee-status-chip <?=$statusKey==='active'?'active':'inactive'?>"><?=$statusLabel?></span>
        <h4><?=ws_h($e['full_name'])?></h4>
        <div class="employee-hero-meta"><?=ws_h($e['employee_code'])?> <span>&bull;</span> <?=ws_h($department ?: 'No department')?> <span>&bull;</span> <?=ws_h($jobTitle ?: 'No job title')?></div>
        <div class="employee-hero-submeta"><?=ws_h($category)?> <span>&bull;</span> <?=ws_employee_rate_label($e)?></div>
      </div>
    </section>

    <div class="employee-profile-tabs">
      <button class="active" type="button" data-employee-tab="overview">Overview</button>
      <button type="button" data-employee-tab="documents">Documents</button>
      <button type="button" data-employee-tab="attendance">Attendance</button>
      <button type="button" data-employee-tab="payroll">Payroll</button>
      <button type="button" data-employee-tab="performance">Performance</button>
      <button type="button" data-employee-tab="history">History</button>
    </div>

    <div class="employee-profile-grid" data-employee-section="overview">
      <section class="workspace-section-card employee-info-card">
        <div class="employee-section-title">Personal Information</div>
        <div class="employee-detail-list">
          <div><span>Phone</span><strong><?=ws_h($e['phone'] ?: 'Not provided')?></strong></div>
          <div><span>Email</span><strong><?=ws_h($e['email'] ?: 'Not provided')?></strong></div>
          <div><span>Birth Date</span><strong><?=ws_h($e['birth_date'] ?: 'Not set')?><?= $age!=='' ? ' ('.$age.' yrs old)' : '' ?></strong></div>
          <div><span>Address</span><strong><?=nl2br(ws_h($e['address'] ?: 'Not provided'))?></strong></div>
        </div>
      </section>

      <section class="workspace-section-card employee-info-card">
        <div class="employee-section-title">About / Notes</div>
        <div class="employee-note-box"><?=nl2br(ws_h(trim((string)($e['notes'] ?? '')) ?: 'No notes added yet.'))?></div>
      </section>
    </div>

    <section class="workspace-section-card employee-documents-card" data-employee-section="documents">
      <div class="employee-section-title">Documents</div>
      <div class="employee-documents-grid">
        <?php foreach(ws_doc_labels() as $key=>$label): $doc=$docMap[$key]??null; $hasFile=!empty($doc['file_path']); $docClass=ws_doc_status_class($doc['status']??null,$hasFile); ?>
          <div class="employee-document-item <?=$docClass?>">
            <div class="employee-document-copy">
              <strong><?=ws_h($label)?></strong>
              <span><?=ws_doc_status_label($doc['status']??null,$hasFile)?></span>
            </div>
            <?php if($hasFile): ?>
              <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?=ws_h($doc['file_path'])?>">View File</a>
            <?php else: ?>
              <span class="employee-document-empty">No File</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="workspace-section-card employee-record-card" data-employee-section="attendance">
      <div class="employee-section-title-row">
        <div class="employee-section-title">Attendance</div>
        <div class="text-muted small">Employee-specific attendance records</div>
      </div>
      <div class="table-responsive workspace-table-wrap">
        <table class="table table-sm workspace-table mb-0">
          <thead><tr><th>Date</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Late</th><th>Worked</th><th>Regular</th><th>OT Hours</th><th>Payable</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach($attendanceRows as $row): ?>
              <tr>
                <td><?=ws_h($row['attendance_date'])?></td>
                <td><span class="mb-badge"><?=ws_h(ws_attendance_status_label($row['status']))?></span></td>
                <td><?=ws_h($row['time_in'] ? ws_time_12((string)$row['time_in']) : '-')?></td>
                <td><?=ws_h($row['time_out'] ? ws_time_12((string)$row['time_out']) : '-')?></td>
                <td><?=ws_h((string)((int)$row['late_minutes']))?></td>
                <td><?=ws_h(number_format((float)($row['worked_hours'] ?? 0),2))?></td>
                <td><?=ws_h(number_format((float)($row['regular_hours'] ?? 0),2))?></td>
                <td><?=ws_h(number_format((float)$row['overtime_hours'],2))?></td>
                <td><?=ws_h(number_format((float)$row['payable_day'],2))?></td>
                <td><?=ws_h($row['notes'] ?: '-')?></td>
              </tr>
            <?php endforeach; if(!$attendanceRows): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">No attendance records for this employee.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="workspace-section-card employee-record-card" data-employee-section="payroll">
      <div class="employee-section-title-row">
        <div class="employee-section-title">Payroll</div>
        <div class="text-muted small">Employee-specific payroll history</div>
      </div>
      <div class="table-responsive workspace-table-wrap">
        <table class="table table-sm workspace-table mb-0">
          <thead><tr><th>Period</th><th>Payroll Title</th><th>Status</th><th>Rate Type</th><th>Base Rate</th><th>Present</th><th>Late</th><th>Absent</th><th>Payable</th><th>Regular Pay</th><th>OT Pay</th><th>Gross Pay</th><th>Net Pay</th></tr></thead>
          <tbody>
            <?php foreach($payrollRows as $row): ?>
              <tr>
                <td><?=ws_h($row['period_start'])?> to <?=ws_h($row['period_end'])?></td>
                <td><?=ws_h($row['title'] ?: 'Payroll Period')?></td>
                <td><span class="mb-badge"><?=ws_h($row['period_status'])?></span></td>
                <td><?=ws_h($row['rate_type'] ?: 'daily')?></td>
                <td><?=ws_money($row['base_rate'])?></td>
                <td><?=ws_h((string)((float)$row['present_days']))?></td>
                <td><?=ws_h((string)((float)$row['late_days']))?></td>
                <td><?=ws_h((string)((float)$row['absent_days']))?></td>
                <td><?=ws_h(number_format((float)($row['payable_days'] ?? 0),2))?></td>
                <td><?=ws_money($row['regular_pay'] ?? 0)?></td>
                <td><?=ws_money($row['overtime_pay'] ?? 0)?></td>
                <td><strong><?=ws_money($row['gross_pay'])?></strong></td>
                <td><strong><?=ws_money($row['net_pay'] ?? 0)?></strong></td>
              </tr>
            <?php endforeach; if(!$payrollRows): ?>
              <tr><td colspan="13" class="text-center text-muted py-4">No payroll records for this employee.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="employee-profile-grid" data-employee-section="performance">
      <section class="workspace-section-card employee-info-card">
        <div class="employee-section-title">Performance</div>
        <div class="employee-note-box">
          Performance tracking is not yet connected here. This section is reserved for future appraisal and KPI data.
        </div>
      </section>
      <section class="workspace-section-card employee-info-card" data-employee-section="history">
        <div class="employee-section-title">History</div>
        <div class="employee-note-box">
          History will show role changes, salary updates, and document events once the audit feed is wired in.
        </div>
      </section>
    </section>
  </div>

  <?php if($canEdit): $deps=ws_departments($pdo); $jobs=ws_job_titles($pdo); ?>
    <style>
      .employee-profile-shell .employee-edit-panel{width:min(520px,94vw);height:100%;max-height:100vh;background:linear-gradient(180deg,#f7fbff 0%,#ffffff 26%,#f8fbff 100%);border-left:1px solid #dbe7f3;box-shadow:-30px 0 70px rgba(15,23,42,.16);display:flex;flex-direction:column;overflow:hidden}
      .employee-resume-modal{height:min(92vh,920px);display:flex;flex-direction:column;overflow:hidden}
      .employee-resume-modal .modal-body{flex:1;min-height:0;overflow:hidden;padding:0}
      .employee-resume-modal .modal-footer{flex:none}
      .employee-profile-shell .employee-edit-head{padding:1.35rem 1.4rem 1.1rem;border-bottom:1px solid #dbe7f3;background:linear-gradient(135deg,#f8fbff,#ffffff)}
      .employee-profile-shell .employee-edit-head h4{font-size:2rem;line-height:1.05}
      .employee-profile-shell .employee-edit-head p{font-size:.95rem}
      .employee-profile-shell .employee-edit-body{padding:1.05rem 1.4rem 1.4rem;gap:1.05rem;overflow:auto;min-height:0;flex:1}
      .employee-profile-shell .employee-edit-section{border-radius:22px;border-color:#dbe7f3;box-shadow:0 12px 30px rgba(15,23,42,.05)}
      .employee-profile-shell .employee-edit-section-title{font-size:1.02rem}
      .employee-profile-shell .employee-photo-panel{padding:1.05rem 1.05rem 1.1rem;align-items:stretch;background:rgba(255,255,255,.92);border:1px solid #dbe7f3;border-radius:22px;overflow:visible}
      .employee-profile-shell .employee-edit-photo-head{display:flex;flex-direction:column;align-items:flex-start;gap:.18rem;margin-bottom:.75rem;width:100%}
      .employee-profile-shell .employee-edit-photo-head .employee-edit-photo-kicker{font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:#64748b;line-height:1.2}
      .employee-profile-shell .employee-edit-photo-head .employee-edit-section-title{margin:0;font-size:1.02rem;line-height:1.2}
      .employee-profile-shell .employee-photo-upload-row{align-items:stretch}
      .employee-profile-shell .employee-photo-preview.employee-edit-avatar{flex:0 0 96px;width:96px;height:96px;min-width:96px;min-height:96px;max-width:96px;max-height:96px;display:flex;align-items:center;justify-content:center}
      .employee-profile-shell .employee-photo-upload-box{min-height:96px;display:flex;flex-direction:column;justify-content:center}
      .employee-profile-shell .employee-edit-doc-item{border-radius:20px;border-color:#dbe7f3;background:linear-gradient(180deg,#ffffff,#f5f9ff)}
      .employee-profile-shell .employee-edit-doc-top strong{font-size:1rem}
      .employee-profile-shell .employee-edit-footer{background:linear-gradient(180deg,#ffffff,#f6f9fc);flex:none}
      .employee-profile-shell .mb-form-grid label{font-weight:800;color:#0f172a}
      .employee-profile-shell .mb-form-grid .form-control,.employee-profile-shell .mb-form-grid .form-select{background:#fff;border-color:#d3e0ef;box-shadow:0 1px 0 rgba(15,23,42,.02) inset}
      .employee-profile-shell .mb-form-grid .form-control:focus,.employee-profile-shell .mb-form-grid .form-select:focus{border-color:#7bb1f1;box-shadow:0 0 0 .2rem rgba(59,130,246,.12)}
      .employee-profile-shell .employee-edit-panel::-webkit-scrollbar{width:0;height:0}
      .employee-profile-shell .employee-edit-body::-webkit-scrollbar{width:10px}
      .employee-profile-shell .employee-edit-body::-webkit-scrollbar-thumb{background:#c7d7ea;border-radius:999px}
      @media(max-width:900px){.employee-profile-shell .employee-edit-panel{width:100%;max-width:none;border-left:0;border-top:1px solid #dbe7f3;border-radius:24px 24px 0 0;height:100%;max-height:100vh}.employee-profile-shell .employee-edit-head{padding:1.1rem 1.1rem .9rem}.employee-profile-shell .employee-edit-body{padding:1rem 1.1rem 1.1rem}.employee-profile-shell .employee-edit-footer{padding:1rem 1.1rem 1.15rem}}
    </style>
    <div class="employee-edit-overlay" aria-hidden="true"></div>
    <aside class="employee-edit-panel">
      <form class="employee-profile-editor" data-spa-form enctype="multipart/form-data">
        <input type="hidden" name="module" value="employees">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
        <div class="employee-edit-head">
          <div>
            <h4>Edit Employee</h4>
            <p>Update employee information</p>
          </div>
          <button class="btn-close" type="button" data-employee-edit-close aria-label="Close editor"></button>
        </div>

        <div class="employee-edit-body">
          <section class="employee-edit-section employee-photo-panel">
            <div class="employee-edit-photo-head">
              <div class="employee-edit-photo-kicker">Profile Photo</div>
              <div class="employee-edit-section-title">Upload or replace the employee portrait</div>
            </div>
            <div class="employee-photo-upload-row">
              <div class="employee-photo-preview employee-photo-preview-square employee-edit-avatar">
                <?php if(!empty($e['photo_path'])): ?><img src="<?=ws_h(ws_media_url((string)$e['photo_path']))?>" alt="photo"><?php else: ?><span>Photo</span><?php endif; ?>
              </div>
              <label class="employee-photo-upload-box">
                <input class="d-none" type="file" accept="image/*" name="photo" data-photo-input>
                <strong>Change Photo</strong>
                <span>JPG, PNG (Max 2MB)</span>
              </label>
            </div>
          </section>

          <section class="employee-edit-section">
            <div class="employee-edit-section-title">Personal Information</div>
            <div class="mb-form-grid">
              <label>Full Name<input required class="form-control" name="full_name" value="<?=ws_h($e['full_name']??'')?>"></label>
              <label>Employee ID<input class="form-control" name="employee_code" value="<?=ws_h($e['employee_code']??'')?>"></label>
              <label>Department / Category<select class="form-select" name="department_id"><option value="">Select Department</option><?php foreach($deps as $d): ?><option value="<?= (int)$d['id']?>" data-category="<?=ws_h($d['category'])?>" <?=((int)($e['department_id']??0)===(int)$d['id']?'selected':'')?>><?=ws_h(ucfirst($d['category']).' - '.$d['name'])?></option><?php endforeach; ?></select></label>
              <label>Position / Job Title<select class="form-select" name="job_title_id" data-job-title-select><option value="">Select Job Title</option><?php foreach($jobs as $j): ?><option value="<?= (int)$j['id']?>" data-rate="<?=ws_h($j['salary_rate'])?>" data-rate-type="<?=ws_h($j['rate_type'])?>" data-category="<?=ws_h($j['category'])?>" data-department="<?= (int)$j['department_id']?>" <?=((int)($e['job_title_id']??0)===(int)$j['id']?'selected':'')?>><?=ws_h($j['title'])?></option><?php endforeach; ?></select></label>
              <label>Role / Access Level<input class="form-control" name="employee_type" value="<?=ws_h($e['employee_type']??'')?>" placeholder="Admin, Staff, Project-based"></label>
              <label>Salary<input class="form-control" type="number" step="0.01" name="salary_rate" value="<?=ws_h($e['salary_rate']??($e['daily_rate']??0))?>"></label>
              <label>Rate Type<select class="form-select" name="rate_type"><option value="daily" <?=($e['rate_type']??'daily')==='daily'?'selected':''?>>Daily</option><option value="monthly" <?=($e['rate_type']??'')==='monthly'?'selected':''?>>Monthly</option><option value="hourly" <?=($e['rate_type']??'')==='hourly'?'selected':''?>>Hourly</option><option value="project" <?=($e['rate_type']??'')==='project'?'selected':''?>>Project</option></select></label>
              <label>Status<select class="form-select" name="status"><?php foreach(ws_status_options() as $k=>$v): ?><option value="<?=$k?>" <?=($e['status']??'active')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label>
              <input type="hidden" name="category" value="<?=ws_h($e['category']??'office')?>">
              <input type="hidden" name="job_title" value="<?=ws_h($e['job_title']??'')?>">
              <input type="hidden" name="hire_date" value="<?=ws_h($e['hire_date']??'')?>">
              <input type="hidden" name="civil_status" value="<?=ws_h($e['civil_status']??'')?>">
              <input type="hidden" name="emergency_contact" value="<?=ws_h($e['emergency_contact']??'')?>">
              <input type="hidden" name="emergency_phone" value="<?=ws_h($e['emergency_phone']??'')?>">
            </div>
          </section>

          <section class="employee-edit-section">
            <div class="employee-edit-section-title">Contact Information</div>
            <div class="mb-form-grid">
              <label>Phone<input class="form-control" name="phone" value="<?=ws_h($e['phone']??'')?>"></label>
              <label>Email<input class="form-control" name="email" value="<?=ws_h($e['email']??'')?>"></label>
              <label>Birth Date<input class="form-control" type="date" name="birth_date" value="<?=ws_h($e['birth_date']??'')?>"></label>
              <label>Gender<select class="form-select" name="gender"><option value="">Select</option><?php foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $k=>$v): ?><option value="<?=$k?>" <?=($e['gender']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label>
              <label class="full">Address<textarea class="form-control" name="address" rows="3"><?=ws_h($e['address']??'')?></textarea></label>
            </div>
          </section>

          <section class="employee-edit-section">
            <div class="employee-edit-section-title">Additional Information</div>
            <div class="mb-form-grid single">
              <label>Employment Status<select class="form-select" name="status"><?php foreach(ws_status_options() as $k=>$v): ?><option value="<?=$k?>" <?=($e['status']??'active')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label>
              <label>Notes<textarea class="form-control" name="notes" rows="4"><?=ws_h($e['notes']??'')?></textarea></label>
            </div>
          </section>

          <section class="employee-edit-section">
            <div class="employee-edit-section-title">Documents</div>
            <div class="employee-edit-doc-grid">
              <?php foreach(ws_doc_labels() as $key=>$label): $doc=$docMap[$key]??null; ?>
                <div class="employee-edit-doc-item">
                  <div class="employee-edit-doc-top">
                    <div>
                      <strong><?=ws_h($label)?></strong>
                      <span class="employee-edit-doc-sub">Upload the latest copy here.</span>
                    </div>
                    <?php if(!empty($doc['file_path'])): ?><a class="employee-edit-doc-link" target="_blank" href="<?=ws_h($doc['file_path'])?>">View</a><?php endif; ?>
                  </div>
                  <input class="form-control form-control-sm employee-edit-file" type="file" name="doc_<?=$key?>" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        </div>

        <div class="employee-edit-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-warning text-white" type="submit">Save Changes</button>
        </div>
      </form>
    </aside>
  <?php endif; ?>
</div>
<?php }
function ws_render_employees(PDO $pdo,string $q=''): void { require_permission($pdo,'view_hr'); $can=current_user_can($pdo,'manage_employees'); $params=[];$where=''; if($q!==''){ $where="WHERE e.full_name LIKE ? OR e.employee_code LIKE ? OR e.phone LIKE ? OR jt.title LIKE ? OR d.name LIKE ?"; $params=array_fill(0,5,'%'.$q.'%'); } $st=$pdo->prepare("SELECT e.*,jt.title job_title_name,d.name department_name FROM mb_employees e LEFT JOIN mb_job_titles jt ON jt.id=e.job_title_id LEFT JOIN mb_departments d ON d.id=e.department_id $where ORDER BY e.category,e.full_name LIMIT 300"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC); $office=array_filter($rows,fn($r)=>($r['category']??'office')==='office'); $field=array_filter($rows,fn($r)=>($r['category']??'')==='field'); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0">Employees</h5><div class="text-muted small">Office and field records with resume profile, documents, salary title, attendance, payroll, and ID printing.</div></div><div class="d-flex gap-2"><?php if($can): ?><button class="btn btn-outline-primary btn-sm" data-module="departments">Departments</button><button class="btn btn-outline-primary btn-sm" data-module="job_titles">Job Titles</button><button class="btn btn-primary btn-sm" data-workspace-open="employeeResumeModal">New Employee</button><?php endif; ?></div></div>
<div class="row g-3 mb-3"><div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Office</div><div class="stat-main"><?=count($office)?></div></div></div><div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Field</div><div class="stat-main"><?=count($field)?></div></div></div><div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Active</div><div class="stat-main"><?=count(array_filter($rows,fn($r)=>($r['status']??'')==='active'))?></div></div></div><div class="col-md-3"><div class="workspace-stat"><div class="stat-kicker">Missing Docs</div><div class="stat-main"><?php $missing=0; foreach($rows as $r){$c=$pdo->prepare('SELECT COUNT(*) FROM mb_employee_documents WHERE employee_id=?');$c->execute([$r['id']]); if((int)$c->fetchColumn()<3)$missing++;} echo $missing;?></div></div></div></div>
<ul class="nav nav-pills mb-3 employee-category-tabs"><li class="nav-item"><button class="nav-link active" data-emp-cat="office" type="button">Office Employees</button></li><li class="nav-item"><button class="nav-link" data-emp-cat="field" type="button">Field Workers</button></li></ul>
<?php foreach(['office'=>$office,'field'=>$field] as $cat=>$list): ?><div class="employee-cat-pane <?= $cat==='office'?'active':''?>" data-emp-pane="<?=$cat?>"><div class="table-responsive workspace-table-wrap"><table class="table table-hover workspace-table"><thead><tr><th>Employee</th><th>Job / Department</th><th>Rate</th><th>Status</th><th>Contact</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($list as $r): ?><tr><td><div class="d-flex align-items-center gap-2"><div class="employee-thumb"><?php if(!empty($r['photo_path'])):?><img src="<?=ws_h(ws_media_url((string)$r['photo_path']))?>" alt="photo" style="width:42px;height:42px;max-width:42px;max-height:42px;min-width:42px;min-height:42px;display:block;object-fit:cover;object-position:center;"><?php endif;?></div><div><strong><?=ws_h($r['full_name'])?></strong><div class="text-muted small"><?=ws_h($r['employee_code'])?></div></div></div></td><td><?=ws_h($r['job_title_name'] ?: $r['job_title'])?><div class="text-muted small"><?=ws_h($r['department_name'] ?: $r['department'])?></div></td><td><?=ws_money($r['salary_rate'] ?: $r['daily_rate'])?><div class="text-muted small"><?=ws_h($r['rate_type']??'daily')?></div></td><td><span class="badge text-bg-light border"><?=ws_h($r['status'])?></span></td><td><?=ws_h($r['phone'])?><div class="text-muted small"><?=ws_h($r['email'])?></div></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary" data-ws-view="employees" data-id="<?= (int)$r['id']?>">View</button> <?php if($can): ?><button class="btn btn-sm btn-outline-primary" data-ws-edit="employees" data-id="<?= (int)$r['id']?>">Edit</button> <button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete employee?" data-id="<?= (int)$r['id']?>">Delete</button><?php endif;?></td></tr><?php endforeach; if(!$list): ?><tr><td colspan="6" class="text-center text-muted py-4">No <?=ws_h($cat)?> employees yet.</td></tr><?php endif;?></tbody></table></div></div><?php endforeach; ?><?php if($can) ws_render_employee_modal($pdo); }

function ws_render_attendance(PDO $pdo,string $q=''): void {
  require_permission($pdo,'view_hr');
  $can=current_user_can($pdo,'manage_attendance');
  $date=$_GET['date'] ?? date('Y-m-d');
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date=date('Y-m-d');
  $settings=$pdo->query("SELECT * FROM mb_attendance_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  $emps=$pdo->query("
    SELECT e.id,e.employee_code,e.full_name,e.category,e.salary_rate,e.daily_rate,e.photo_path,
           COALESCE(d.name,'Unassigned') department_name,
           COALESCE(NULLIF(TRIM(e.department),''), d.name, CASE WHEN e.category='field' THEN 'Field Team' ELSE 'Head Office' END) site_name
    FROM mb_employees e
    LEFT JOIN mb_departments d ON d.id=e.department_id
    WHERE e.status IN ('active','probationary')
    ORDER BY e.category,e.full_name
  ")->fetchAll(PDO::FETCH_ASSOC);
  $att=[];
  $s=$pdo->prepare("SELECT * FROM mb_attendance WHERE attendance_date=?");
  $s->execute([$date]);
  foreach($s->fetchAll(PDO::FETCH_ASSOC) as $a){ $att[$a['employee_id']]=$a; }
  $month=substr($date,0,7);
  $first=new DateTime($month.'-01');
  $days=(int)$first->format('t');
  $startDow=(int)$first->format('w');
  $counts=[];
  $s=$pdo->prepare("SELECT attendance_date,status,COUNT(*) c FROM mb_attendance WHERE attendance_date BETWEEN ? AND ? GROUP BY attendance_date,status");
  $s->execute([$month.'-01',$month.'-'.$days]);
  foreach($s as $r){ $counts[$r['attendance_date']][$r['status']]=$r['c']; }
  $dayCounts=$counts[$date]??[];
  $presentCount=(int)($dayCounts['present']??0);
  $lateCount=(int)($dayCounts['late']??0);
  $absentCount=(int)($dayCounts['absent']??0);
  $halfDayCount=(int)($dayCounts['half_day']??0);
  $overtimeEmployees=(int)($dayCounts['overtime']??0);
  $payrollImpact=0.0;
  $monthlyPresent=$monthlyLate=$monthlyAbsent=$monthlyOvertimeDays=0;
  foreach($counts as $bucket){
    $monthlyPresent+=(int)(($bucket['present']??0)+($bucket['half_day']??0));
    $monthlyLate+=(int)($bucket['late']??0);
    $monthlyAbsent+=(int)($bucket['absent']??0);
    if(!empty($bucket['overtime'])) $monthlyOvertimeDays+=(int)$bucket['overtime'];
  }
  foreach($emps as $e){
    $row=$att[$e['id']]??[];
    $rate=(float)($e['salary_rate'] ?: $e['daily_rate']);
    $payrollImpact += $rate * (['present'=>1,'late'=>1,'half_day'=>0.5,'leave'=>1,'rest_day'=>0,'absent'=>0][(string)($row['status']??'present')] ?? 1);
  }
  $departments=[];
  $sites=[];
  foreach($emps as $e){ $departments[(string)$e['department_name']]=(string)$e['department_name']; $sites[(string)$e['site_name']]=(string)$e['site_name']; }
  ?>
<div class="attendance-shell attendance-pro-shell">
  <div class="attendance-topbar">
    <div class="attendance-date-wrap">
      <input class="form-control attendance-date-input pro" type="date" value="<?=ws_h($date)?>" data-attendance-date>
    </div>
    <div class="attendance-segments" data-att-category-tabs>
      <button type="button" class="attendance-segment active" data-att-category="office">Office Staff</button>
      <button type="button" class="attendance-segment" data-att-category="field">Field Workers</button>
    </div>
    <select class="form-select attendance-filter-select" data-att-filter="department">
      <option value="">All Departments</option>
      <?php foreach($departments as $department): ?><option value="<?=ws_h($department)?>"><?=ws_h($department)?></option><?php endforeach; ?>
    </select>
    <select class="form-select attendance-filter-select" data-att-filter="site">
      <option value="">All Project Sites</option>
      <?php foreach($sites as $site): ?><option value="<?=ws_h($site)?>"><?=ws_h($site)?></option><?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-attendance btn-attendance-outline attendance-settings-btn" data-workspace-open="attendanceSettingsModal">Settings</button>
  </div>

  <div class="attendance-kpi-grid">
    <div class="attendance-kpi-card present">
      <div class="attendance-kpi-label">Present Today</div>
      <div class="attendance-kpi-value"><?=$presentCount?></div>
      <div class="attendance-kpi-sub"><?=count($emps) ? round((($presentCount + $lateCount)/max(1,count($emps)))*100) : 0?>% of total</div>
    </div>
    <div class="attendance-kpi-card late">
      <div class="attendance-kpi-label">Late Today</div>
      <div class="attendance-kpi-value"><?=$lateCount?></div>
      <div class="attendance-kpi-sub">Needs quick review</div>
    </div>
    <div class="attendance-kpi-card absent">
      <div class="attendance-kpi-label">Absent Today</div>
      <div class="attendance-kpi-value"><?=$absentCount?></div>
      <div class="attendance-kpi-sub">Follow-up required</div>
    </div>
    <div class="attendance-kpi-card half">
      <div class="attendance-kpi-label">Half Day</div>
      <div class="attendance-kpi-value"><?=$halfDayCount?></div>
      <div class="attendance-kpi-sub">Partial attendance</div>
    </div>
    <div class="attendance-kpi-card overtime">
      <div class="attendance-kpi-label">Overtime Hours</div>
      <div class="attendance-kpi-value"><?=number_format((float)$overtimeEmployees,1)?></div>
      <div class="attendance-kpi-sub">Current day markers</div>
    </div>
    <div class="attendance-kpi-card payroll">
      <div class="attendance-kpi-label">Payroll Impact</div>
      <div class="attendance-kpi-value">₱ <?=number_format($payrollImpact,0)?></div>
      <div class="attendance-kpi-sub">Estimated for this period</div>
    </div>
  </div>

  <div class="attendance-main-grid">
    <section class="workspace-section-card attendance-month-card">
      <div class="attendance-card-title">Monthly Attendance Calendar</div>
      <div class="attendance-card-head">
        <div class="attendance-month-nav"><?=date('F Y',strtotime($date))?></div>
        <button type="button" class="btn btn-attendance btn-attendance-outline btn-sm" data-att-today>Today</button>
      </div>
      <div class="attendance-legend">
        <span class="present">Present</span>
        <span class="late">Late</span>
        <span class="absent">Absent</span>
        <span class="overtime">Overtime</span>
        <span class="rest">Rest Day</span>
      </div>
      <div class="attendance-calendar pro">
        <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d):?><div class="cal-head"><?=$d?></div><?php endforeach; ?>
        <?php for($i=0;$i<$startDow;$i++) echo '<div class="cal-cell muted pro"></div>'; ?>
        <?php for($d=1;$d<=$days;$d++): $dt=$month.'-'.str_pad((string)$d,2,'0',STR_PAD_LEFT); $c=$counts[$dt]??[]; ?>
          <button type="button" class="cal-cell pro <?= $dt===$date?'active':''?>" data-att-date="<?=$dt?>">
            <span class="cal-day"><?=$d?></span>
            <span class="cal-dots">
              <?php if(($c['present']??0)>0): ?><i class="present"></i><?php endif; ?>
              <?php if(($c['late']??0)>0): ?><i class="late"></i><?php endif; ?>
              <?php if(($c['absent']??0)>0): ?><i class="absent"></i><?php endif; ?>
              <?php if(($c['overtime']??0)>0): ?><i class="overtime"></i><?php endif; ?>
              <?php if(($c['rest_day']??0)>0): ?><i class="rest"></i><?php endif; ?>
            </span>
          </button>
        <?php endfor; ?>
      </div>
      <div class="attendance-mini-stats">
        <div><strong><?=$days?></strong><span>Total Work Days</span></div>
        <div><strong><?=$monthlyPresent?></strong><span>Present Days</span></div>
        <div><strong><?=$monthlyLate?></strong><span>Late Days</span></div>
        <div><strong><?=$monthlyAbsent?></strong><span>Absent Days</span></div>
        <div><strong><?=$monthlyOvertimeDays?></strong><span>Overtime Days</span></div>
      </div>
    </section>

    <section class="workspace-section-card attendance-sheet-pro">
      <form data-spa-form data-attendance-board data-start="<?=ws_h(substr((string)$settings['work_start'],0,5))?>" data-end="<?=ws_h(substr((string)$settings['work_end'],0,5))?>" data-grace="<?= (int)($settings['late_grace_minutes'] ?? 0) ?>">
        <input type="hidden" name="module" value="attendance">
        <input type="hidden" name="action" value="bulk_save">
        <input type="hidden" name="attendance_date" value="<?=ws_h($date)?>">
        <div class="attendance-sheet-title-row">
          <div class="attendance-card-title">Daily Attendance Sheet - <?=date('M d, Y (l)',strtotime($date))?></div>
          <div class="attendance-sheet-actions">
            <input class="form-control attendance-search-input" placeholder="Search employee..." data-att-search>
            <button type="button" class="btn btn-attendance btn-attendance-solid btn-sm" data-att-bulk="present">Mark All Present</button>
            <button type="button" class="btn btn-attendance btn-attendance-outline btn-sm" data-att-bulk="clear">Clear Day</button>
            <button type="button" class="btn btn-attendance btn-attendance-outline btn-sm" data-module="payroll">Export Payroll</button>
            <?php if($can): ?><button class="btn btn-attendance btn-attendance-dark btn-sm">Save Attendance</button><?php endif; ?>
          </div>
        </div>

        <div class="attendance-sheet-table">
          <div class="attendance-sheet-headline">
            <span>Employee</span>
            <span>Department / Site</span>
            <span>Time In</span>
            <span>Time Out</span>
            <span>Status</span>
            <span>Late</span>
            <span>OT Hours</span>
            <span>Actions</span>
          </div>

          <?php foreach($emps as $e): $a=$att[$e['id']]??[]; $status=(string)($a['status'] ?? 'present'); $avatar=ws_employee_avatar_src((string)$e['full_name'], (string)($e['photo_path'] ?? '')); ?>
            <div class="attendance-row-pro" data-att-row data-att-card data-category="<?=ws_h((string)$e['category'])?>" data-department="<?=ws_h((string)$e['department_name'])?>" data-site="<?=ws_h((string)$e['site_name'])?>" data-search="<?=ws_h(strtolower($e['full_name'].' '.$e['employee_code'].' '.$e['department_name'].' '.$e['site_name']))?>" data-employee-name="<?=ws_h($e['full_name'])?>" data-employee-meta="<?=ws_h($e['department_name'].' - '.$e['site_name'])?>">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][employee_id]" value="<?= (int)$e['id']?>">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][status]" value="<?=ws_h($status)?>" data-att-input="status">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][time_in]" value="<?=ws_h((string)($a['time_in'] ?? ''))?>" data-att-input="time_in">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][time_out]" value="<?=ws_h((string)($a['time_out'] ?? ''))?>" data-att-input="time_out">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][late_minutes]" value="<?=ws_h((string)($a['late_minutes'] ?? 0))?>" data-att-input="late_minutes">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][overtime_hours]" value="<?=ws_h((string)($a['overtime_hours'] ?? 0))?>" data-att-input="overtime_hours">
              <input type="hidden" name="rows[<?= (int)$e['id']?>][notes]" value="<?=ws_h((string)($a['notes'] ?? ''))?>" data-att-input="notes">

              <div class="employee-col" role="button" tabindex="0" data-ws-view="employees" data-id="<?= (int)$e['id']?>">
                <div class="attendance-avatar pro"><img src="<?=ws_h($avatar)?>" alt="<?=ws_h($e['full_name'])?>" loading="lazy"></div>
                <div>
                  <div class="employee-code"><?=ws_h($e['employee_code'])?></div>
                  <strong><?=ws_h($e['full_name'])?></strong>
                  <div class="text-muted small"><?=ws_h(ucfirst($e['category']))?></div>
                </div>
              </div>
              <div class="dept-col">
                <div><?=ws_h($e['department_name'])?></div>
                <div class="text-muted small"><?=ws_h($e['site_name'])?></div>
              </div>
              <div class="time-col" data-att-display="time_in"><?=ws_h($a['time_in'] ? ws_time_12((string)$a['time_in']) : '-')?></div>
              <div class="time-col" data-att-display="time_out"><?=ws_h($a['time_out'] ? ws_time_12((string)$a['time_out']) : '-')?></div>
              <div><span class="attendance-status-pill" data-att-status-pill><?=ws_h(ucfirst(str_replace('_',' ',$status)))?></span></div>
              <div class="late-col" data-att-display="late_minutes"><?=ws_h((string)($a['late_minutes'] ?? 0))?> min</div>
              <div class="ot-col" data-att-display="overtime_hours"><?=number_format((float)($a['overtime_hours'] ?? 0),2)?></div>
              <div class="action-col">
                <button type="button" class="attendance-icon-btn quick present" data-att-quick="present">P</button>
                <button type="button" class="attendance-icon-btn quick late" data-att-quick="late">L</button>
                <button type="button" class="attendance-icon-btn quick absent" data-att-quick="absent">A</button>
                <button type="button" class="attendance-icon-btn" data-att-open-details>Edit</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </form>
    </section>
  </div>

  <div class="attendance-footer-grid">
    <section class="workspace-section-card attendance-footer-card">
      <div class="attendance-card-title">Attendance Settings (Work Schedule)</div>
      <div class="attendance-settings-grid">
        <div><span>Work Start:</span><strong><?=ws_h($settings['work_start'])?></strong></div>
        <div><span>Work End:</span><strong><?=ws_h($settings['work_end'])?></strong></div>
        <div><span>Grace Period:</span><strong><?=ws_h($settings['late_grace_minutes'])?> min</strong></div>
        <div><span>Overtime After:</span><strong><?=ws_h($settings['overtime_after_hours'])?> hrs</strong></div>
      </div>
      <button type="button" class="btn btn-attendance btn-attendance-outline btn-sm mt-2" data-workspace-open="attendanceSettingsModal">View Settings</button>
    </section>
    <section class="workspace-section-card attendance-footer-card">
      <div class="attendance-card-title">Quick Summary (<?=date('M 1',strtotime($date))?> - <?=date('M d, Y',strtotime($date))?>)</div>
      <div class="attendance-quick-summary">
        <div><span>Present</span><strong><?=$monthlyPresent?></strong></div>
        <div><span>Late</span><strong><?=$monthlyLate?></strong></div>
        <div><span>Absent</span><strong><?=$monthlyAbsent?></strong></div>
        <div><span>Payroll Impact</span><strong>₱ <?=number_format($payrollImpact,0)?></strong></div>
      </div>
    </section>
    <section class="workspace-section-card attendance-footer-card">
      <div class="attendance-card-title">Payroll Preview</div>
      <div class="text-muted small mb-3">Based on current attendance</div>
      <button type="button" class="btn btn-attendance btn-attendance-outline btn-sm" data-payroll-preview>View Payroll Preview</button>
    </section>
  </div>
</div>
<div class="modal fade" id="attendanceEntryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="attendanceEntryTitle">Attendance Details</h5>
          <div class="text-muted small" id="attendanceEntryMeta"></div>
        </div>
        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-form-grid single">
          <label>Status<select class="form-select" data-att-modal-field="status"><?php foreach(['present'=>'Present','late'=>'Late','absent'=>'Absent','half_day'=>'Half Day','leave'=>'Leave','rest_day'=>'Rest Day'] as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></label>
          <label>Time In<input class="form-control" type="text" inputmode="numeric" placeholder="8:00 AM" data-att-modal-field="time_in"></label>
          <label>Time Out<input class="form-control" type="text" inputmode="numeric" placeholder="5:00 PM" data-att-modal-field="time_out"></label>
          <label>Late Minutes<input class="form-control" type="number" data-att-modal-field="late_minutes"></label>
          <label>OT Hours<input class="form-control" type="number" step="0.01" data-att-modal-field="overtime_hours"></label>
          <label><span data-att-reason-label>Reason / Notes</span><textarea class="form-control" rows="3" data-att-modal-field="notes"></textarea></label>
        </div>
        <div class="small text-danger mt-2 d-none" data-att-modal-error></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-attendance btn-attendance-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-attendance btn-attendance-dark" data-att-save-details>Apply</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="attendanceSettingsModal"><div class="modal-dialog"><form class="modal-content" data-spa-form><input type="hidden" name="module" value="attendance"><input type="hidden" name="action" value="settings"><div class="modal-header"><h5 class="modal-title">Attendance Settings</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-form-grid single"><label>Work Start<input class="form-control" type="text" inputmode="numeric" name="work_start" value="<?=ws_h(ws_time_12($settings['work_start']))?>" placeholder="8:00 AM"></label><label>Work End<input class="form-control" type="text" inputmode="numeric" name="work_end" value="<?=ws_h(ws_time_12($settings['work_end']))?>" placeholder="5:00 PM"></label><label>Late Grace Minutes<input class="form-control" type="number" name="late_grace_minutes" value="<?=ws_h($settings['late_grace_minutes'])?>"></label><label>Standard Hours / Day<input class="form-control" type="number" step="0.01" name="standard_hours_per_day" value="<?=ws_h($settings['standard_hours_per_day'])?>"></label><label>Half Day Hours<input class="form-control" type="number" step="0.01" name="half_day_hours" value="<?=ws_h($settings['half_day_hours'])?>"></label><label>Monthly Working Days<input class="form-control" type="number" step="0.01" name="monthly_working_days" value="<?=ws_h($settings['monthly_working_days'])?>"></label><label>Weekly Working Days<input class="form-control" type="number" step="0.01" name="weekly_working_days" value="<?=ws_h($settings['weekly_working_days'])?>"></label><label>Overtime After Hours<input class="form-control" type="number" step="0.01" name="overtime_after_hours" value="<?=ws_h($settings['overtime_after_hours'])?>"></label><label>Overtime Multiplier<input class="form-control" type="number" step="0.01" name="overtime_rate_multiplier" value="<?=ws_h($settings['overtime_rate_multiplier'])?>"></label><label>Holiday Pay Multiplier<input class="form-control" type="number" step="0.01" name="holiday_pay_multiplier" value="<?=ws_h($settings['holiday_pay_multiplier'])?>"></label><label>Rest Day Pay Multiplier<input class="form-control" type="number" step="0.01" name="rest_day_pay_multiplier" value="<?=ws_h($settings['rest_day_pay_multiplier'])?>"></label><label class="d-flex align-items-center gap-2"><input type="checkbox" name="deduct_late_from_pay" value="1" <?=!empty($settings['deduct_late_from_pay'])?'checked':''?>> Deduct late from pay</label><label class="d-flex align-items-center gap-2"><input type="checkbox" name="allow_overtime" value="1" <?=!empty($settings['allow_overtime'])?'checked':''?>> Allow overtime</label><label class="d-flex align-items-center gap-2"><input type="checkbox" name="absent_no_timein" value="1" <?=!empty($settings['absent_no_timein'])?'checked':''?>> Missing time-in counts as absent</label></div></div><div class="modal-footer"><button class="btn btn-attendance btn-attendance-dark">Save Settings</button></div></form></div></div><?php }
function ws_render_payroll(PDO $pdo,string $q=''): void { require_permission($pdo,'view_hr'); $periodType=strtolower(trim((string)($_GET['period_type'] ?? 'monthly'))); if(!isset(ws_payroll_period_type_options()[$periodType])) $periodType='monthly'; $anchor=$_GET['period_anchor'] ?? date('Y-m-d'); ['start'=>$start,'end'=>$end]=ws_period_range($periodType,(string)$anchor,$_GET['start']??null,$_GET['end']??null); $items=ws_payroll_preview($pdo,$start,$end); $total=array_sum(array_column($items,'gross_pay')); $periods=$pdo->query("SELECT * FROM mb_payroll_periods ORDER BY period_end DESC,id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC); ?><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0">Payroll</h5><div class="text-muted small">Attendance-driven payroll with daily, weekly, monthly, hourly, and project-based rate support.</div></div></div><div class="workspace-section-card mb-3"><form class="d-flex flex-wrap gap-2 align-items-end" data-payroll-filter><label class="small">Period Type<select class="form-select form-select-sm" name="period_type"><?php foreach(ws_payroll_period_type_options() as $k=>$v): ?><option value="<?=$k?>" <?=$periodType===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label><label class="small">Anchor<input class="form-control form-control-sm" type="date" name="period_anchor" value="<?=ws_h((string)$anchor)?>"></label><label class="small">Start<input class="form-control form-control-sm" type="date" name="start" value="<?=ws_h($start)?>"></label><label class="small">End<input class="form-control form-control-sm" type="date" name="end" value="<?=ws_h($end)?>"></label><button class="btn btn-outline-primary btn-sm" type="submit">Preview</button></form></div><div class="workspace-section-card"><div class="d-flex justify-content-between mb-2"><strong>Payroll Preview</strong><strong><?=ws_money($total)?></strong></div><div class="table-responsive"><table class="table table-sm workspace-table"><thead><tr><th>Employee</th><th>Rate Type</th><th>Base Rate</th><th>Daily</th><th>Hourly</th><th>Present</th><th>Late</th><th>Absent</th><th>Payable</th><th>Regular Hrs</th><th>OT Hrs</th><th>Late Min</th><th>Regular Pay</th><th>OT Pay</th><th>Late Deduction</th><th>Gross</th><th>Net</th></tr></thead><tbody><?php foreach($items as $i):?><tr><td><strong><?=ws_h($i['full_name'])?></strong><div class="text-muted small"><?=ws_h($i['employee_code'])?></div></td><td><?=ws_h($i['rate_type'])?></td><td><?=ws_money($i['base_rate'])?></td><td><?=ws_money($i['daily_rate'])?></td><td><?=ws_money($i['hourly_rate'])?></td><td><?=number_format((float)$i['present_days'],2)?></td><td><?=number_format((float)$i['late_days'],2)?></td><td><?=number_format((float)$i['absent_days'],2)?></td><td><?=number_format((float)$i['payable_days'],2)?></td><td><?=number_format((float)$i['regular_hours'],2)?></td><td><?=number_format((float)$i['overtime_hours'],2)?></td><td><?= (int)$i['late_minutes'] ?></td><td><?=ws_money($i['regular_pay'])?></td><td><?=ws_money($i['overtime_pay'])?></td><td><?=ws_money($i['late_deduction'])?></td><td><strong><?=ws_money($i['gross_pay'])?></strong></td><td><strong><?=ws_money($i['net_pay'])?></strong></td></tr><?php endforeach; if(!$items): ?><tr><td colspan="17" class="text-center text-muted py-4">No payroll preview items for this range.</td></tr><?php endif; ?></tbody></table></div><form data-spa-form><input type="hidden" name="module" value="payroll"><input type="hidden" name="action" value="save_period"><input type="hidden" name="period_type" value="<?=ws_h($periodType)?>"><input type="hidden" name="period_anchor" value="<?=ws_h((string)$anchor)?>"><input type="hidden" name="period_start" value="<?=ws_h($start)?>"><input type="hidden" name="period_end" value="<?=ws_h($end)?>"><button class="btn btn-primary btn-sm">Save Payroll Period</button></form></div><h6 class="mt-4">Saved Payroll Periods</h6><div class="table-responsive workspace-table-wrap"><table class="table table-sm workspace-table"><thead><tr><th>Period</th><th>Status</th><th>Gross Pay</th><th>Created</th><th></th></tr></thead><tbody><?php foreach($periods as $p):?><tr><td><?=ws_h($p['period_start'])?> to <?=ws_h($p['period_end'])?><div class="text-muted small"><?=ws_h($p['notes'] ?: '')?></div></td><td><?=ws_h($p['status'])?></td><td><?=ws_money($p['gross_pay'])?></td><td><?=ws_h($p['created_at'])?></td><td class="text-end"><button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete payroll period?" data-id="<?= (int)$p['id']?>">Delete</button></td></tr><?php endforeach;?></tbody></table></div><?php }
function ws_payroll_preview(PDO $pdo,string $start,string $end): array { $emps=$pdo->query("SELECT * FROM mb_employees WHERE status IN ('active','probationary') ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC); $out=[]; foreach($emps as $e){ $out[]=ws_compute_employee_payroll($pdo,$e,$start,$end); } return $out; }

function ws_save_job_title(PDO $pdo): void { require_permission($pdo,'manage_employees'); $id=(int)($_POST['id']??0); $rateType=strtolower(trim((string)($_POST['rate_type']??'daily'))); if(!isset(ws_rate_type_options()[$rateType])) $rateType='daily'; $data=[trim($_POST['title']??''),($_POST['department_id']?:null),trim($_POST['category']??'office'),$rateType,ws_num($_POST['salary_rate']??0),trim($_POST['description']??''),trim($_POST['status']??'active')]; if($id>0) $pdo->prepare("UPDATE mb_job_titles SET title=?,department_id=?,category=?,rate_type=?,salary_rate=?,description=?,status=? WHERE id=?")->execute([...$data,$id]); else $pdo->prepare("INSERT INTO mb_job_titles (title,department_id,category,rate_type,salary_rate,description,status) VALUES (?,?,?,?,?,?,?)")->execute($data); ws_json(['ok'=>true,'message'=>'Job title saved.']); }
function ws_save_department(PDO $pdo): void { require_permission($pdo,'manage_employees'); $id=(int)($_POST['id']??0); $data=[trim($_POST['name']??''),trim($_POST['category']??'office'),trim($_POST['manager_name']??''),trim($_POST['description']??''),trim($_POST['status']??'active')]; if($id>0) $pdo->prepare("UPDATE mb_departments SET name=?,category=?,manager_name=?,description=?,status=? WHERE id=?")->execute([...$data,$id]); else $pdo->prepare("INSERT INTO mb_departments (name,category,manager_name,description,status) VALUES (?,?,?,?,?)")->execute($data); ws_json(['ok'=>true,'message'=>'Department saved.']); }
function ws_save_employee(PDO $pdo): void { require_permission($pdo,'manage_employees'); $id=(int)($_POST['id']??0); $code=trim($_POST['employee_code']??'') ?: ('EMP-'.date('Ymd-His')); $jobId=(int)($_POST['job_title_id']??0); $job=null; if($jobId>0){ $jobStmt=$pdo->prepare("SELECT * FROM mb_job_titles WHERE id=?"); $jobStmt->execute([$jobId]); $job=$jobStmt->fetch(PDO::FETCH_ASSOC) ?: null; } $rate=ws_num($_POST['salary_rate']??0); if($rate<=0 && $job) $rate=(float)$job['salary_rate']; $rateType=strtolower(trim((string)($_POST['rate_type']??($job['rate_type']??'daily')))); if(!isset(ws_rate_type_options()[$rateType])) $rateType='daily'; $photo=ws_upload_file('photo','employees/photos'); $departmentId=$_POST['department_id']?:null; $departmentName=''; if($departmentId){ $deptStmt=$pdo->prepare("SELECT name FROM mb_departments WHERE id=?"); $deptStmt->execute([$departmentId]); $departmentName=(string)($deptStmt->fetchColumn() ?: ''); } $fields=['employee_code','full_name','employee_type','job_title','department','phone','email','daily_rate','status','notes','photo_path','birth_date','gender','civil_status','address','emergency_contact','emergency_phone','category','job_title_id','department_id','hire_date','salary_rate','rate_type']; $data=[$code,trim($_POST['full_name']??''),trim($_POST['employee_type']??''),trim($_POST['job_title']??($job['title']??'')),$departmentName,trim($_POST['phone']??''),trim($_POST['email']??''),$rate,trim($_POST['status']??'active'),trim($_POST['notes']??''),$photo,($_POST['birth_date']?:null),trim($_POST['gender']??''),trim($_POST['civil_status']??''),trim($_POST['address']??''),trim($_POST['emergency_contact']??''),trim($_POST['emergency_phone']??''),trim($_POST['category']??($job['category']??'office')),($jobId?:null),$departmentId,($_POST['hire_date']?:null),$rate,$rateType]; if($id>0){ if(!$photo){$fields=array_values(array_filter($fields,fn($f)=>$f!=='photo_path')); array_splice($data,10,1);} $sets=implode(',',array_map(fn($f)=>"`$f`=?",$fields)); $pdo->prepare("UPDATE mb_employees SET $sets WHERE id=?")->execute([...$data,$id]); } else { $cols=implode(',',array_map(fn($f)=>"`$f`",$fields)); $qs=implode(',',array_fill(0,count($fields),'?')); $pdo->prepare("INSERT INTO mb_employees ($cols) VALUES ($qs)")->execute($data); $id=(int)$pdo->lastInsertId(); }
  foreach(ws_doc_labels() as $key=>$label){ $path=ws_upload_file('doc_'.$key,'employees/docs'); if($path || in_array($key,$_POST['doc_required']??[],true)){ $exists=$pdo->prepare("SELECT id,status FROM mb_employee_documents WHERE employee_id=? AND document_type=? ORDER BY id DESC LIMIT 1"); $exists->execute([$id,$key]); $existingDoc=$exists->fetch(PDO::FETCH_ASSOC) ?: []; $status=(string)($existingDoc['status'] ?? 'submitted'); if($path && $status==='') $status='submitted'; if($status==='') $status='submitted'; $docId=(int)($existingDoc['id'] ?? 0); if($docId){ if($path) $pdo->prepare("UPDATE mb_employee_documents SET file_path=?,status=?,document_title=? WHERE id=?")->execute([$path,$status,$label,$docId]); else $pdo->prepare("UPDATE mb_employee_documents SET status=?,document_title=? WHERE id=?")->execute([$status,$label,$docId]); } else $pdo->prepare("INSERT INTO mb_employee_documents (employee_id,document_type,document_title,file_path,status) VALUES (?,?,?,?,?)")->execute([$id,$key,$label,$path,$status]); } }
  ws_json(['ok'=>true,'message'=>'Employee resume saved.']); }
function ws_save_attendance_bulk(PDO $pdo): void { require_permission($pdo,'manage_attendance'); $date=$_POST['attendance_date']??date('Y-m-d'); $settings=ws_attendance_settings($pdo); foreach(($_POST['rows']??[]) as $row){ $emp=(int)($row['employee_id']??0); if(!$emp) continue; $computed=ws_compute_attendance_day($row, $settings); $notes=trim((string)($row['notes']??'')); if(in_array($computed['status'],['late','absent'],true) && $notes==='') ws_json(['ok'=>false,'message'=>$computed['status']==='late'?'Please enter why the employee is late before saving.':'Please enter the reason for absence before saving.'],422); $pdo->prepare("INSERT INTO mb_attendance (employee_id,attendance_date,time_in,time_out,status,late_minutes,overtime_hours,payable_day,regular_hours,worked_hours,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE time_in=VALUES(time_in),time_out=VALUES(time_out),status=VALUES(status),late_minutes=VALUES(late_minutes),overtime_hours=VALUES(overtime_hours),payable_day=VALUES(payable_day),regular_hours=VALUES(regular_hours),worked_hours=VALUES(worked_hours),notes=VALUES(notes)")->execute([$emp,$date,$computed['time_in'],$computed['time_out'],$computed['status'],$computed['late_minutes'],$computed['overtime_hours'],$computed['payable_day'],$computed['regular_hours'],$computed['worked_hours'],$notes,(int)$_SESSION['user_id']]); } ws_json(['ok'=>true,'message'=>'Attendance saved and computed.']); }
function ws_save_attendance_settings(PDO $pdo): void { require_permission($pdo,'manage_attendance'); $workStart=ws_time_input_to_24($_POST['work_start']??'') ?? '08:00'; $workEnd=ws_time_input_to_24($_POST['work_end']??'') ?? '17:00'; $pdo->prepare("UPDATE mb_attendance_settings SET work_start=?,work_end=?,late_grace_minutes=?,overtime_after_hours=?,overtime_rate_multiplier=?,standard_hours_per_day=?,monthly_working_days=?,weekly_working_days=?,half_day_hours=?,deduct_late_from_pay=?,allow_overtime=?,holiday_pay_multiplier=?,rest_day_pay_multiplier=?,absent_no_timein=? WHERE id=1")->execute([$workStart,$workEnd,(int)($_POST['late_grace_minutes']??15),ws_num($_POST['overtime_after_hours']??8),ws_num($_POST['overtime_rate_multiplier']??1.25),ws_num($_POST['standard_hours_per_day']??8),ws_num($_POST['monthly_working_days']??26),ws_num($_POST['weekly_working_days']??6),ws_num($_POST['half_day_hours']??4),!empty($_POST['deduct_late_from_pay'])?1:0,!empty($_POST['allow_overtime'])?1:0,ws_num($_POST['holiday_pay_multiplier']??1),ws_num($_POST['rest_day_pay_multiplier']??1.3),!empty($_POST['absent_no_timein'])?1:0]); ws_json(['ok'=>true,'message'=>'Attendance settings saved.']); }
function ws_save_payroll_period(PDO $pdo): void { require_permission($pdo,'manage_employees'); $type=strtolower(trim((string)($_POST['period_type']??'custom'))); if(!isset(ws_payroll_period_type_options()[$type])) $type='custom'; $anchor=(string)($_POST['period_anchor'] ?? ($_POST['period_start'] ?? date('Y-m-d'))); ['start'=>$start,'end'=>$end]=ws_period_range($type,$anchor,$_POST['period_start']??null,$_POST['period_end']??null); $pdo->prepare("INSERT INTO mb_payroll_periods (period_start,period_end,title,status,gross_pay,notes,created_by) VALUES (?,?,?,'draft',0,?,?)")->execute([$start,$end,'Payroll '.ucfirst(str_replace('_',' ',$type)).' '.$start.' to '.$end,'Type: '.$type,(int)$_SESSION['user_id']]); $pid=(int)$pdo->lastInsertId(); ws_rebuild_payroll_items($pdo,$pid); ws_json(['ok'=>true,'message'=>'Payroll period saved and rebuilt from attendance.']); }

function ws_save_generic(PDO $pdo, string $module): void { $map=['expenses'=>['manage_expenses','mb_expenses',['expense_date','category','vendor','description','amount','tax_amount','reference_no','status']], 'invoices'=>['manage_invoices','mb_invoices',['invoice_no','client_name','issue_date','due_date','amount','paid_amount','status','notes']], 'employees'=>['manage_employees','mb_employees',['employee_code','full_name','employee_type','job_title','department','phone','email','daily_rate','status','notes']], 'attendance'=>['manage_attendance','mb_attendance',['employee_id','attendance_date','time_in','time_out','status','notes']], 'inventory'=>['manage_inventory','mb_inventory_items',['sku','item_name','category','unit','quantity','min_quantity','unit_cost','location','status','notes']], 'documents'=>['manage_documents','mb_documents',['title','category','status','file_path','expiry_date','notes']], 'plans'=>['manage_plans','mb_plan_files',['title','plan_type','revision','status','file_path','notes']]]; if(!isset($map[$module])) ws_json(['ok'=>false,'message'=>'Unsupported module'],400); [$perm,$table,$fields]=$map[$module]; require_permission($pdo,$perm); $id=(int)($_POST['id']??0); $vals=[]; foreach($fields as $f){ $vals[]=$_POST[$f] ?? null; } if($id>0){ $sets=implode(',',array_map(fn($f)=>"`$f`=?",$fields)); $pdo->prepare("UPDATE `$table` SET $sets WHERE id=?")->execute([...$vals,$id]); } else { $cols=implode(',',array_map(fn($f)=>"`$f`",$fields)); $qs=implode(',',array_fill(0,count($fields),'?')); $pdo->prepare("INSERT INTO `$table` ($cols,created_by) VALUES ($qs,?)")->execute([...$vals,(int)$_SESSION['user_id']]); } ws_json(['ok'=>true,'message'=>'Record saved.']); }
function ws_delete(PDO $pdo, string $module): void { $map=['projects'=>['manage_projects','mb_projects'],'estimates'=>['manage_estimates','mb_estimates'],'proposals'=>['manage_proposals','mb_proposals'],'expenses'=>['manage_expenses','mb_expenses'],'invoices'=>['manage_invoices','mb_invoices'],'employees'=>['manage_employees','mb_employees'],'attendance'=>['manage_attendance','mb_attendance'],'inventory'=>['manage_inventory','mb_inventory_items'],'documents'=>['manage_documents','mb_documents'],'plans'=>['manage_plans','mb_plan_files'],'job_titles'=>['manage_employees','mb_job_titles'],'departments'=>['manage_employees','mb_departments'],'payroll'=>['manage_employees','mb_payroll_periods']]; if(!isset($map[$module])) ws_json(['ok'=>false,'message'=>'Unsupported delete.'],400); [$perm,$table]=$map[$module]; require_permission($pdo,$perm); $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([(int)($_POST['id']??0)]); ws_json(['ok'=>true,'message'=>'Deleted.']); }

try {
  if($_SERVER['REQUEST_METHOD']==='POST') { csrf_verify(); $module=$_POST['module']??''; $action=$_POST['action']??''; if($action==='delete') ws_delete($pdo,$module); if($module==='estimates' && $action==='save') ws_save_estimate($pdo); if($module==='projects' && $action==='save') ws_save_project($pdo); if($module==='proposals' && $action==='save') ws_save_proposal($pdo); if($module==='proposals' && $action==='approve') ws_approve_proposal($pdo); if($module==='job_titles' && $action==='save') ws_save_job_title($pdo); if($module==='departments' && $action==='save') ws_save_department($pdo); if($module==='employees' && $action==='save') ws_save_employee($pdo); if($module==='attendance' && $action==='bulk_save') ws_save_attendance_bulk($pdo); if($module==='attendance' && $action==='settings') ws_save_attendance_settings($pdo); if($module==='payroll' && $action==='save_period') ws_save_payroll_period($pdo); ws_save_generic($pdo,$module); }
  $module=$_GET['module'] ?? 'overview'; $mode=$_GET['mode'] ?? 'list'; $id=(int)($_GET['id'] ?? 0); $q=trim($_GET['q'] ?? '');
  if($mode==='view'){ if($module==='estimates') ws_render_estimate_view($pdo,$id); elseif($module==='projects') ws_render_project_view($pdo,$id); elseif($module==='proposals') ws_render_proposal_view($pdo,$id); elseif($module==='employees') ws_render_employee_view($pdo,$id); else echo '<div class="alert alert-info">View modal is available for main contractor modules.</div>'; exit; }
  if($mode==='edit'){ if($module==='estimates') ws_render_estimate_modal($pdo,ws_fetch_estimate($pdo,$id)); elseif($module==='projects'){ $s=$pdo->prepare("SELECT * FROM mb_projects WHERE id=?"); $s->execute([$id]); ws_render_project_modal($pdo,$s->fetch(PDO::FETCH_ASSOC) ?: []); } elseif($module==='proposals'){ $s=$pdo->prepare("SELECT * FROM mb_proposals WHERE id=?"); $s->execute([$id]); ws_render_proposal_modal($pdo,$s->fetch(PDO::FETCH_ASSOC) ?: []); } elseif($module==='employees') ws_render_employee_modal($pdo,ws_fetch_employee($pdo,$id)); elseif($module==='job_titles'){ $s=$pdo->prepare("SELECT * FROM mb_job_titles WHERE id=?"); $s->execute([$id]); ws_render_jobtitle_modal($pdo,$s->fetch(PDO::FETCH_ASSOC) ?: []); } elseif($module==='departments'){ $s=$pdo->prepare("SELECT * FROM mb_departments WHERE id=?"); $s->execute([$id]); ws_render_department_modal($pdo,$s->fetch(PDO::FETCH_ASSOC) ?: []); } else echo '<div class="alert alert-warning">Edit modal not available.</div>'; exit; }
  if($module==='overview') ws_render_overview($pdo); elseif($module==='estimates') ws_render_estimates($pdo,$q); elseif($module==='projects') ws_render_projects($pdo,$q); elseif($module==='proposals') ws_render_proposals($pdo,$q); elseif($module==='employees') ws_render_employees($pdo,$q); elseif($module==='attendance') ws_render_attendance($pdo,$q); elseif($module==='payroll') ws_render_payroll($pdo,$q); elseif($module==='job_titles') ws_render_jobtitles($pdo,$q); elseif($module==='departments') ws_render_departments($pdo,$q); elseif($module==='reports') { require_permission($pdo,'view_reports'); ws_render_overview($pdo); } else ws_render_generic($pdo,$module,$q);
} catch(Throwable $e) { if($_SERVER['REQUEST_METHOD']==='POST') ws_json(['ok'=>false,'message'=>$e->getMessage()],500); http_response_code(500); echo '<div class="alert alert-danger">'.ws_h($e->getMessage()).'</div>'; }
