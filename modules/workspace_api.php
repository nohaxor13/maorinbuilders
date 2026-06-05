<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
redirect_if_not_logged_in();
ensure_maorin_workspace_tables($pdo);

function ws_configs(): array {
  return [
    'projects'=>['title'=>'Projects','table'=>'mb_projects','view'=>'view_projects','manage'=>'manage_projects','search'=>['project_code','name','client_name','location','status'],'fields'=>['project_code'=>'Code','name'=>'Project Name','client_name'=>'Client','location'=>'Location','project_type'=>'Type','status'=>'Status','progress_percent'=>'Progress %','estimated_cost'=>'Estimated Cost','actual_cost'=>'Actual Cost','contract_amount'=>'Contract Amount','notes'=>'Notes'],'money'=>['estimated_cost','actual_cost','contract_amount'],'required'=>['name']],
    'estimates'=>['title'=>'Estimates','table'=>'mb_estimates','view'=>'view_estimates','manage'=>'manage_estimates','search'=>['estimate_no','title','client_name','status','project_type','location'],'fields'=>['estimate_no'=>'Estimate No.','title'=>'Title','client_name'=>'Client','status'=>'Status','material_cost'=>'Materials','labor_cost'=>'Labor','equipment_cost'=>'Equipment','grand_total'=>'Client Price','profit_amount'=>'Profit','profit_margin_percent'=>'Margin %','risk_level'=>'Risk'],'money'=>['material_cost','labor_cost','equipment_cost','grand_total','profit_amount'],'required'=>['title']],
    'proposals'=>['title'=>'Proposals','table'=>'mb_proposals','view'=>'view_proposals','manage'=>'manage_proposals','search'=>['proposal_no','title','client_name','status'],'fields'=>['proposal_no'=>'Proposal No.','title'=>'Title','client_name'=>'Client','status'=>'Status','amount'=>'Amount','valid_until'=>'Valid Until','scope'=>'Scope','terms'=>'Terms'],'money'=>['amount'],'required'=>['title']],
    'plans'=>['title'=>'Plans','table'=>'mb_plan_files','view'=>'view_plans','manage'=>'manage_plans','search'=>['title','plan_type','revision','status'],'fields'=>['title'=>'Title','plan_type'=>'Plan Type','revision'=>'Revision','status'=>'Status','file_path'=>'File Path','notes'=>'Notes'],'money'=>[],'required'=>['title']],
    'expenses'=>['title'=>'Expenses','table'=>'mb_expenses','view'=>'view_finance','manage'=>'manage_expenses','search'=>['category','vendor','description','reference_no','status'],'fields'=>['expense_date'=>'Date','category'=>'Category','vendor'=>'Vendor','description'=>'Description','amount'=>'Amount','tax_amount'=>'Tax','reference_no'=>'Reference','status'=>'Status'],'money'=>['amount','tax_amount'],'required'=>['expense_date']],
    'invoices'=>['title'=>'Invoices','table'=>'mb_invoices','view'=>'view_finance','manage'=>'manage_invoices','search'=>['invoice_no','client_name','status','notes'],'fields'=>['invoice_no'=>'Invoice No.','client_name'=>'Client','issue_date'=>'Issue Date','due_date'=>'Due Date','amount'=>'Amount','paid_amount'=>'Paid','status'=>'Status','notes'=>'Notes'],'money'=>['amount','paid_amount'],'required'=>['issue_date']],
    'employees'=>['title'=>'Employees','table'=>'mb_employees','view'=>'view_hr','manage'=>'manage_employees','search'=>['employee_code','full_name','job_title','department','status'],'fields'=>['employee_code'=>'Code','full_name'=>'Full Name','employee_type'=>'Type','job_title'=>'Job Title','department'=>'Department','phone'=>'Phone','email'=>'Email','daily_rate'=>'Daily Rate','status'=>'Status','notes'=>'Notes'],'money'=>['daily_rate'],'required'=>['full_name']],
    'attendance'=>['title'=>'Attendance','table'=>'mb_attendance','view'=>'view_hr','manage'=>'manage_attendance','search'=>['status','notes'],'fields'=>['employee_id'=>'Employee ID','attendance_date'=>'Date','time_in'=>'Time In','time_out'=>'Time Out','status'=>'Status','notes'=>'Notes'],'money'=>[],'required'=>['employee_id','attendance_date']],
    'inventory'=>['title'=>'Inventory','table'=>'mb_inventory_items','view'=>'view_inventory','manage'=>'manage_inventory','search'=>['sku','item_name','category','location','status'],'fields'=>['sku'=>'SKU','item_name'=>'Item Name','category'=>'Category','unit'=>'Unit','quantity'=>'Qty','min_quantity'=>'Min Qty','unit_cost'=>'Unit Cost','location'=>'Location','status'=>'Status','notes'=>'Notes'],'money'=>['unit_cost'],'required'=>['item_name']],
    'documents'=>['title'=>'Documents','table'=>'mb_documents','view'=>'view_documents','manage'=>'manage_documents','search'=>['title','category','status','file_path','notes'],'fields'=>['title'=>'Title','category'=>'Category','status'=>'Status','file_path'=>'File Path','expiry_date'=>'Expiry Date','notes'=>'Notes'],'money'=>[],'required'=>['title']],
  ];
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function module_config($m){ $c=ws_configs(); if(!isset($c[$m])){ http_response_code(404); exit('Unknown module.'); } return $c[$m]; }
function nval($v): float { return is_numeric($v) ? (float)$v : 0.0; }
function money($v): string { return '₱'.mb_money($v); }
function estimate_status_options(): array { return ['draft'=>'Draft','for_review'=>'For Review','sent'=>'Sent to Client','approved'=>'Approved','rejected'=>'Rejected','revised'=>'Revised']; }
function project_type_options(): array { return ['residential'=>'Residential','commercial'=>'Commercial','renovation'=>'Renovation','warehouse'=>'Warehouse','fit_out'=>'Fit-out','other'=>'Other']; }
function risk_class($risk): string { return ['safe'=>'success','review'=>'warn','danger'=>'danger'][$risk] ?? 'warn'; }

function totals(PDO $pdo): array { return [
  'projects'=>(int)$pdo->query("SELECT COUNT(*) FROM mb_projects")->fetchColumn(),
  'contract'=>(float)$pdo->query("SELECT COALESCE(SUM(contract_amount),0) FROM mb_projects")->fetchColumn(),
  'expenses'=>(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM mb_expenses")->fetchColumn(),
  'inventory'=>(float)$pdo->query("SELECT COALESCE(SUM(quantity*unit_cost),0) FROM mb_inventory_items")->fetchColumn(),
  'employees'=>(int)$pdo->query("SELECT COUNT(*) FROM mb_employees WHERE status='active'")->fetchColumn(),
  'docs'=>(int)$pdo->query("SELECT COUNT(*) FROM mb_documents")->fetchColumn(),
];}

function render_overview(PDO $pdo): void { mb_require_any_permission($pdo,['view_projects','view_finance','view_hr','view_inventory','view_documents','view_reports']); $t=totals($pdo); ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="workspace-stat"><div class="text-muted small">Projects</div><div class="fs-3 fw-bold"><?= $t['projects'] ?></div></div></div>
  <div class="col-md-4"><div class="workspace-stat"><div class="text-muted small">Contract Amount</div><div class="fs-3 fw-bold">₱<?= mb_money($t['contract']) ?></div></div></div>
  <div class="col-md-4"><div class="workspace-stat"><div class="text-muted small">Expenses</div><div class="fs-3 fw-bold">₱<?= mb_money($t['expenses']) ?></div></div></div>
  <div class="col-md-4"><div class="workspace-stat"><div class="text-muted small">Inventory Value</div><div class="fs-3 fw-bold">₱<?= mb_money($t['inventory']) ?></div></div></div>
  <div class="col-md-4"><div class="workspace-stat"><div class="text-muted small">Active Employees</div><div class="fs-3 fw-bold"><?= $t['employees'] ?></div></div></div>
  <div class="col-md-4"><div class="workspace-stat"><div class="text-muted small">Documents</div><div class="fs-3 fw-bold"><?= $t['docs'] ?></div></div></div>
</div>
<div class="alert alert-info mb-0">Use the Workspace dropdown in the main navigation or the tabs above. This screen stays in one page and loads modules without leaving the current website layout.</div>
<?php }
function render_reports(PDO $pdo): void { mb_require_any_permission($pdo,['view_reports']); $t=totals($pdo); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0">Reports Summary</h5><div class="text-muted small">Live totals from workspace records.</div></div></div>
<div class="table-responsive"><table class="table table-sm align-middle"><tbody>
<tr><th>Total Projects</th><td><?= $t['projects'] ?></td></tr><tr><th>Total Contract Amount</th><td>₱<?= mb_money($t['contract']) ?></td></tr><tr><th>Total Expenses</th><td>₱<?= mb_money($t['expenses']) ?></td></tr><tr><th>Estimated Gross Balance</th><td>₱<?= mb_money($t['contract']-$t['expenses']) ?></td></tr><tr><th>Inventory Value</th><td>₱<?= mb_money($t['inventory']) ?></td></tr><tr><th>Active Employees</th><td><?= $t['employees'] ?></td></tr></tbody></table></div>
<?php }

function render_estimates(PDO $pdo, string $q=''): void {
  require_permission($pdo,'view_estimates');
  $can=current_user_can($pdo,'manage_estimates');
  $where=''; $params=[];
  if($q!==''){ $where="WHERE estimate_no LIKE ? OR title LIKE ? OR client_name LIKE ? OR status LIKE ? OR location LIKE ?"; $params=array_fill(0,5,'%'.$q.'%'); }
  $st=$pdo->prepare("SELECT * FROM mb_estimates $where ORDER BY updated_at DESC,id DESC LIMIT 200"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h5 class="mb-0">Professional Estimates</h5><div class="text-muted small">Live material, labor, equipment, professional fees, markup, tax, contingency, and loss-risk prediction.</div></div>
  <?php if($can): ?><button class="btn btn-primary btn-sm" data-workspace-open="estimateBuilderModal">New Estimate</button><?php endif; ?>
</div>
<div class="table-responsive workspace-table-wrap"><table class="table table-hover align-middle workspace-table"><thead><tr><th>No.</th><th>Title / Client</th><th>Status</th><th>Direct Cost</th><th>Client Price</th><th>Profit</th><th>Margin</th><th>Risk</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach($rows as $r): $direct=(float)$r['material_cost']+(float)$r['labor_cost']+(float)$r['equipment_cost']; ?>
<tr>
  <td class="fw-semibold"><?= h($r['estimate_no'] ?: ('EST-'.$r['id'])) ?></td>
  <td><div class="fw-semibold"><?= h($r['title']) ?></div><div class="text-muted small"><?= h($r['client_name']) ?><?= $r['location']?' · '.h($r['location']):'' ?></div></td>
  <td><span class="badge text-bg-light border"><?= h(estimate_status_options()[$r['status']] ?? $r['status']) ?></span></td>
  <td><?= money($direct) ?></td>
  <td class="fw-semibold"><?= money($r['grand_total']) ?></td>
  <td><?= money($r['profit_amount'] ?? 0) ?></td>
  <td><?= h(number_format((float)($r['profit_margin_percent'] ?? 0),2)) ?>%</td>
  <td><span class="mb-risk <?= risk_class($r['risk_level'] ?? 'review') ?>"><?= h(ucfirst($r['risk_level'] ?? 'review')) ?></span></td>
  <td class="text-end"><?php if($can): ?><button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete this estimate?" data-id="<?= (int)$r['id'] ?>">Delete</button><?php endif; ?></td>
</tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No estimates found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if($can): render_estimate_modal($pdo); endif; ?>
<?php }

function render_estimate_modal(PDO $pdo): void { $projects=$pdo->query("SELECT id, project_code, name, client_name, location, project_type FROM mb_projects ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="modal fade" id="estimateBuilderModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
    <form class="modal-content estimate-builder-modal" data-spa-form data-estimate-builder>
      <input type="hidden" name="module" value="estimates"><input type="hidden" name="action" value="save_estimate">
      <div class="modal-header estimate-head">
        <div><h5 class="modal-title mb-0">Professional Estimate Builder</h5><div class="small text-muted">Build the real contractor cost, client price, profit margin, and risk before sending a proposal.</div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="estimate-layout">
          <div class="estimate-inputs p-3 p-lg-4">
            <ul class="nav nav-tabs estimate-tabs mb-3" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#estProject" type="button">Project</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#estMaterials" type="button">Materials</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#estLabor" type="button">Labor</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#estEquipment" type="button">Equipment</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#estFees" type="button">Fees & Risk</button></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="estProject">
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label small fw-semibold">Estimate No.</label><input class="form-control" name="estimate_no" placeholder="Auto or EST-2026-001"></div>
                  <div class="col-md-8"><label class="form-label small fw-semibold">Title</label><input class="form-control" name="title" required placeholder="Two-storey residential construction"></div>
                  <div class="col-md-6"><label class="form-label small fw-semibold">Project</label><select class="form-select" name="project_id" data-project-picker><option value="">No linked project</option><?php foreach($projects as $p): ?><option value="<?= (int)$p['id'] ?>" data-client="<?= h($p['client_name']) ?>" data-location="<?= h($p['location']) ?>" data-type="<?= h($p['project_type']) ?>"><?= h(($p['project_code']?$p['project_code'].' · ':'').$p['name']) ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-6"><label class="form-label small fw-semibold">Client</label><input class="form-control" name="client_name" data-project-client placeholder="Client name"></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Status</label><select class="form-select" name="status"><?php foreach(estimate_status_options() as $k=>$v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Project Type</label><select class="form-select" name="project_type" data-project-type><?php foreach(project_type_options() as $k=>$v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Location</label><input class="form-control" name="location" data-project-location placeholder="Project location"></div>
                  <div class="col-md-3"><label class="form-label small fw-semibold">Floor Area m²</label><input class="form-control" name="floor_area" type="number" step="0.01" value="0" data-est-number></div>
                  <div class="col-md-3"><label class="form-label small fw-semibold">Floors</label><input class="form-control" name="floors" type="number" step="1" value="1" data-est-number></div>
                  <div class="col-md-3"><label class="form-label small fw-semibold">Duration Days</label><input class="form-control" name="duration_days" type="number" step="1" value="0" data-est-number></div>
                  <div class="col-md-3"><label class="form-label small fw-semibold">Target Margin %</label><input class="form-control" name="target_margin_percent" type="number" step="0.01" value="15" data-est-number></div>
                  <div class="col-md-6"><label class="form-label small fw-semibold">Start Date</label><input class="form-control" name="target_start_date" type="date"></div>
                  <div class="col-md-6"><label class="form-label small fw-semibold">Target End Date</label><input class="form-control" name="target_end_date" type="date"></div>
                  <div class="col-12"><label class="form-label small fw-semibold">Notes / Scope Assumptions</label><textarea class="form-control" name="notes" rows="4" placeholder="Important inclusions, exclusions, project assumptions, site risks, client instructions..."></textarea></div>
                </div>
              </div>
              <div class="tab-pane fade" id="estMaterials"><div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-semibold">Materials with waste allowance</div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="materials">Add Material</button></div><div class="estimate-lines" data-lines="materials"></div></div>
              <div class="tab-pane fade" id="estLabor"><div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-semibold">Labor manpower costing</div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="labor">Add Labor</button></div><div class="estimate-lines" data-lines="labor"></div></div>
              <div class="tab-pane fade" id="estEquipment"><div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-semibold">Equipment rental / usage</div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="equipment">Add Equipment</button></div><div class="estimate-lines" data-lines="equipment"></div></div>
              <div class="tab-pane fade" id="estFees">
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label small fw-semibold">Professional Fee</label><input class="form-control" name="professional_fee" type="number" step="0.01" value="0" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Permit Processing Fee</label><input class="form-control" name="permit_fee" type="number" step="0.01" value="0" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Mobilization Fee</label><input class="form-control" name="mobilization_fee" type="number" step="0.01" value="0" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Site Supervision Fee</label><input class="form-control" name="supervision_fee" type="number" step="0.01" value="0" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Overhead</label><input class="form-control" name="overhead_cost" type="number" step="0.01" value="0" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Contingency %</label><input class="form-control" name="contingency_percent" type="number" step="0.01" value="10" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Markup %</label><input class="form-control" name="markup_percent" type="number" step="0.01" value="20" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">VAT / Tax %</label><input class="form-control" name="tax_percent" type="number" step="0.01" value="12" data-est-number></div>
                  <div class="col-md-4"><label class="form-label small fw-semibold">Discount</label><input class="form-control" name="discount_amount" type="number" step="0.01" value="0" data-est-number></div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 small">Tip: Do not remove contingency unless the project scope is locked. Low margin + no contingency is the fastest way for a construction estimate to become a loss.</div>
              </div>
            </div>
          </div>
          <aside class="estimate-summary p-3 p-lg-4">
            <div class="sticky-lg-top estimate-summary-sticky">
              <div class="d-flex align-items-center justify-content-between mb-3"><div><div class="text-muted small text-uppercase fw-semibold">Live Outcome</div><h5 class="mb-0">Cost Prediction</h5></div><span class="mb-risk review" data-risk-label>Review</span></div>
              <div class="summary-row"><span>Materials</span><strong data-sum="materials">₱0.00</strong></div>
              <div class="summary-row"><span>Labor</span><strong data-sum="labor">₱0.00</strong></div>
              <div class="summary-row"><span>Equipment</span><strong data-sum="equipment">₱0.00</strong></div>
              <div class="summary-row"><span>Professional / Permit / Mobilization</span><strong data-sum="fees">₱0.00</strong></div>
              <div class="summary-row"><span>Overhead</span><strong data-sum="overhead">₱0.00</strong></div>
              <div class="summary-row"><span>Contingency</span><strong data-sum="contingency">₱0.00</strong></div>
              <hr>
              <div class="summary-row"><span>Total Contractor Cost</span><strong data-sum="subtotal">₱0.00</strong></div>
              <div class="summary-row"><span>Markup</span><strong data-sum="markup">₱0.00</strong></div>
              <div class="summary-row"><span>Tax</span><strong data-sum="tax">₱0.00</strong></div>
              <div class="summary-row text-muted"><span>Discount</span><strong data-sum="discount">₱0.00</strong></div>
              <div class="summary-grand"><span>Client Price</span><strong data-sum="grand">₱0.00</strong></div>
              <div class="summary-profit mt-3"><div><span>Estimated Profit</span><strong data-sum="profit">₱0.00</strong></div><div><span>Margin</span><strong data-sum="margin">0.00%</strong></div></div>
              <div class="estimate-warnings mt-3" data-warnings></div>
            </div>
          </aside>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Estimate</button></div>
    </form>
  </div>
</div>
<?php }

function render_module(PDO $pdo,string $m,string $q=''): void {
  if($m==='estimates'){ render_estimates($pdo,$q); return; }
  $cfg=module_config($m); require_permission($pdo,$cfg['view']); $can=current_user_can($pdo,$cfg['manage']); $where=''; $params=[]; if($q!==''){ $likes=[]; foreach($cfg['search'] as $col){$likes[]="$col LIKE ?"; $params[]='%'.$q.'%';} $where='WHERE '.implode(' OR ',$likes);} $st=$pdo->prepare("SELECT * FROM {$cfg['table']} $where ORDER BY id DESC LIMIT 300"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC); $fields=$cfg['fields']; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-0"><?= h($cfg['title']) ?></h5><div class="text-muted small"><?= count($rows) ?> visible records</div></div><?php if($can): ?><button class="btn btn-primary btn-sm" data-workspace-open="wsModal">New <?= h(rtrim($cfg['title'],'s')) ?></button><?php endif; ?></div>
<div class="table-responsive workspace-table-wrap"><table class="table table-hover align-middle workspace-table"><thead><tr><?php $shown=array_slice(array_keys($fields),0,6); foreach($shown as $f): ?><th><?= h($fields[$f]) ?></th><?php endforeach; ?><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><?php foreach($shown as $f): ?><td><?= in_array($f,$cfg['money'],true)?'₱'.mb_money($r[$f]??0):h($r[$f]??'') ?></td><?php endforeach; ?><td class="text-end"><?php if($can): ?><button class="btn btn-sm btn-outline-danger" data-confirm-action="Delete this record?" data-id="<?= (int)$r['id'] ?>">Delete</button><?php endif; ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="<?= count($shown)+1 ?>" class="text-center text-muted py-4">No records found.</td></tr><?php endif; ?></tbody></table></div>
<?php if($can): ?><div class="modal fade" id="wsModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" data-spa-form><input type="hidden" name="module" value="<?= h($m) ?>"><input type="hidden" name="action" value="save"><div class="modal-header"><h5 class="modal-title">New <?= h(rtrim($cfg['title'],'s')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><?php foreach($fields as $name=>$label): $isText=in_array($name,['notes','description','scope','terms'],true); $type=(str_contains($name,'date')||$name==='valid_until'||$name==='expiry_date')?'date':((str_contains($name,'time_'))?'time':(in_array($name,$cfg['money'],true)||str_contains($name,'percent')||str_contains($name,'quantity')||$name==='employee_id'?'number':'text')); ?><div class="col-md-6 <?= $isText?'col-12':'' ?>"><label class="form-label small fw-semibold"><?= h($label) ?></label><?php if($isText): ?><textarea class="form-control" name="<?= h($name) ?>" rows="3"></textarea><?php else: ?><input class="form-control" name="<?= h($name) ?>" type="<?= $type ?>" <?= $type==='number'?'step="0.01"':'' ?> <?= in_array($name,$cfg['required'],true)?'required':'' ?>><?php endif; ?></div><?php endforeach; ?></div></div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div><?php endif; ?>
<?php }

function calculate_estimate_from_post(array $post): array {
  $sumMaterials=0; foreach(($post['materials']??[]) as $r){ $qty=nval($r['quantity']??0); $cost=nval($r['unit_cost']??0); $waste=nval($r['waste_percent']??0); $sumMaterials += $qty*$cost*(1+$waste/100); }
  $sumLabor=0; foreach(($post['labor']??[]) as $r){ $sumLabor += nval($r['worker_count']??0)*nval($r['daily_rate']??0)*nval($r['days_count']??0); }
  $sumEquipment=0; foreach(($post['equipment']??[]) as $r){ $sumEquipment += nval($r['rate']??0)*nval($r['duration']??0); }
  $fees=nval($post['professional_fee']??0)+nval($post['permit_fee']??0)+nval($post['mobilization_fee']??0)+nval($post['supervision_fee']??0);
  $overhead=nval($post['overhead_cost']??0);
  $base=$sumMaterials+$sumLabor+$sumEquipment+$fees+$overhead;
  $cont=$base*(nval($post['contingency_percent']??0)/100);
  $subtotal=$base+$cont;
  $markup=$subtotal*(nval($post['markup_percent']??0)/100);
  $tax=($subtotal+$markup)*(nval($post['tax_percent']??0)/100);
  $discount=nval($post['discount_amount']??0);
  $grand=max(0,$subtotal+$markup+$tax-$discount);
  $profit=$grand-$subtotal-$tax; // tax is collected/remitted, not operating profit.
  $margin=$grand>0?($profit/$grand*100):0;
  $target=nval($post['target_margin_percent']??15);
  $risk='safe'; if($margin<0 || $grand<$subtotal){$risk='danger';} elseif($margin<$target || nval($post['contingency_percent']??0)<5){$risk='review';}
  return compact('sumMaterials','sumLabor','sumEquipment','fees','overhead','cont','subtotal','markup','tax','discount','grand','profit','margin','risk');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  header('Content-Type: application/json');
  try{
    csrf_verify(); $m=$_POST['module']??''; $cfg=module_config($m); require_permission($pdo,$cfg['manage']); $action=$_POST['action']??'';
    if($action==='delete'){ $id=(int)($_POST['id']??0); $pdo->prepare("DELETE FROM {$cfg['table']} WHERE id=?")->execute([$id]); echo json_encode(['ok'=>true,'message'=>'Record deleted.']); exit; }
    if($m==='estimates' && $action==='save_estimate'){
      if(trim((string)($_POST['title']??''))==='') throw new RuntimeException('Title is required.');
      $calc=calculate_estimate_from_post($_POST);
      $pdo->beginTransaction();
      $sql="INSERT INTO mb_estimates (project_id,estimate_no,title,client_name,status,project_type,location,floor_area,floors,duration_days,target_start_date,target_end_date,material_cost,labor_cost,equipment_cost,professional_fee,permit_fee,mobilization_fee,supervision_fee,overhead_cost,contingency_percent,contingency_amount,markup_percent,markup_amount,tax_percent,tax_amount,discount_amount,subtotal,grand_total,target_margin_percent,profit_amount,profit_margin_percent,risk_level,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
      $vals=[($_POST['project_id']??'')?:null, trim($_POST['estimate_no']??''), trim($_POST['title']??''), trim($_POST['client_name']??''), trim($_POST['status']??'draft'), trim($_POST['project_type']??''), trim($_POST['location']??''), nval($_POST['floor_area']??0), (int)nval($_POST['floors']??1), (int)nval($_POST['duration_days']??0), ($_POST['target_start_date']??'')?:null, ($_POST['target_end_date']??'')?:null, $calc['sumMaterials'], $calc['sumLabor'], $calc['sumEquipment'], nval($_POST['professional_fee']??0), nval($_POST['permit_fee']??0), nval($_POST['mobilization_fee']??0), nval($_POST['supervision_fee']??0), $calc['overhead'], nval($_POST['contingency_percent']??0), $calc['cont'], nval($_POST['markup_percent']??0), $calc['markup'], nval($_POST['tax_percent']??0), $calc['tax'], $calc['discount'], $calc['subtotal'], $calc['grand'], nval($_POST['target_margin_percent']??15), $calc['profit'], $calc['margin'], $calc['risk'], trim($_POST['notes']??''), (int)$_SESSION['user_id']];
      $pdo->prepare($sql)->execute($vals); $id=(int)$pdo->lastInsertId();
      $i=0; foreach(($_POST['materials']??[]) as $r){ if(trim((string)($r['material_name']??''))==='') continue; $qty=nval($r['quantity']??0); $cost=nval($r['unit_cost']??0); $waste=nval($r['waste_percent']??0); $line=$qty*$cost*(1+$waste/100); $pdo->prepare("INSERT INTO mb_estimate_materials (estimate_id,material_name,unit,quantity,unit_cost,waste_percent,supplier,line_total,sort_order) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$id,trim($r['material_name']),trim($r['unit']??''),$qty,$cost,$waste,trim($r['supplier']??''),$line,$i++]); }
      $i=0; foreach(($_POST['labor']??[]) as $r){ if(trim((string)($r['role_name']??''))==='') continue; $line=nval($r['worker_count']??0)*nval($r['daily_rate']??0)*nval($r['days_count']??0); $pdo->prepare("INSERT INTO mb_estimate_labor (estimate_id,role_name,worker_count,daily_rate,days_count,line_total,sort_order) VALUES (?,?,?,?,?,?,?)")->execute([$id,trim($r['role_name']),nval($r['worker_count']??0),nval($r['daily_rate']??0),nval($r['days_count']??0),$line,$i++]); }
      $i=0; foreach(($_POST['equipment']??[]) as $r){ if(trim((string)($r['equipment_name']??''))==='') continue; $line=nval($r['rate']??0)*nval($r['duration']??0); $pdo->prepare("INSERT INTO mb_estimate_equipment (estimate_id,equipment_name,rate_type,rate,duration,line_total,sort_order) VALUES (?,?,?,?,?,?,?)")->execute([$id,trim($r['equipment_name']),trim($r['rate_type']??'daily'),nval($r['rate']??0),nval($r['duration']??0),$line,$i++]); }
      $pdo->commit();
      echo json_encode(['ok'=>true,'message'=>'Professional estimate saved. Client price: '.money($calc['grand']).' · Margin: '.number_format($calc['margin'],2).'% · Risk: '.ucfirst($calc['risk'])]); exit;
    }
    if($action==='save'){
      foreach($cfg['required'] as $req){ if(trim((string)($_POST[$req]??''))==='') throw new RuntimeException($cfg['fields'][$req].' is required.'); }
      $cols=array_keys($cfg['fields']); $vals=[]; foreach($cols as $col){$v=$_POST[$col]??null; $vals[]=$v===''?null:$v;} if(in_array($m,['projects','proposals','expenses','invoices','documents','plans'],true)){ $cols[]='created_by'; $vals[]=(int)$_SESSION['user_id']; } $sql="INSERT INTO {$cfg['table']} (".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")"; $pdo->prepare($sql)->execute($vals); echo json_encode(['ok'=>true,'message'=>'Record saved.']); exit;
    }
    throw new RuntimeException('Unknown action.');
  } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); http_response_code(400); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); exit; }
}
$module=$_GET['module']??'overview'; $q=trim((string)($_GET['q']??''));
if($module==='overview') render_overview($pdo); elseif($module==='reports') render_reports($pdo); else render_module($pdo,$module,$q);
