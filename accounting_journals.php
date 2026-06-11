<?php
declare(strict_types=1);

require "config.php";
require "helpers.php";
require "accounting_journal_common.php";

redirect_if_not_logged_in();
require_permission($pdo, 'view_journal');
mb_ensure_accounting_journal_tables($pdo);

$type = mb_accounting_journal_type($_GET['type'] ?? 'general');
$config = mb_accounting_journal_config($type);
$columns = mb_accounting_journal_columns($type);
$labels = mb_accounting_journal_field_labels();
$canCreate = current_user_can($pdo, 'create_journal');
$canEdit = current_user_can($pdo, 'edit_journal');
$canDelete = current_user_can($pdo, 'delete_journal');
$canExport = current_user_can($pdo, 'export_journal');
$canImport = current_user_can($pdo, 'import_journal');

$pageContainerClass = 'container-fluid';
include "templates/header.php";
?>
<style>
.aj-hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border-radius:18px;padding:18px 20px;box-shadow:0 16px 38px rgba(15,23,42,.18)}
.aj-hero h1{font-size:1.35rem;margin:0}
.aj-hero p{margin:.25rem 0 0;color:rgba(255,255,255,.78)}
.aj-toolbar{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:12px;box-shadow:0 8px 26px rgba(15,23,42,.06)}
.aj-table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:auto;max-height:65vh}
#accountingJournalTable{min-width:1380px}
#accountingJournalTable thead th{position:sticky;top:0;background:#f8fafc;z-index:2;white-space:nowrap;font-size:.82rem}
#accountingJournalTable td{font-size:.82rem;vertical-align:middle}
.aj-sort{border:0;background:transparent;font:inherit;padding:0;color:inherit}
.aj-sort.active{color:#0d6efd;font-weight:700}
.aj-money{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.aj-summary{display:flex;gap:10px;flex-wrap:wrap}
.aj-summary span{background:#f8fafc;border:1px solid #e5e7eb;border-radius:999px;padding:6px 10px;font-size:.82rem}
.aj-empty{padding:28px;text-align:center;color:#64748b}
</style>

<div class="aj-hero d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
  <div>
    <h1><?= htmlspecialchars($config['label']) ?></h1>
    <p>Track entries with search, filters, totals, and quick actions.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <div class="dropdown">
      <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Switch Journal</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="purchase_list.php">Purchase Journal</a></li>
        <li><hr class="dropdown-divider"></li>
        <?php foreach (mb_accounting_journal_types() as $key => $item): ?>
          <li><a class="dropdown-item<?= $key===$type?' active':'' ?>" href="<?= htmlspecialchars(mb_journal_list_url($key)) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if ($canCreate): ?>
      <a class="btn btn-success btn-sm" href="<?= htmlspecialchars(mb_journal_entry_url($type)) ?>">New Entry</a>
    <?php endif; ?>
    <?php if ($canExport): ?>
      <a class="btn btn-light btn-sm" id="ajExport" href="accounting_journal_export.php?type=<?= htmlspecialchars($type) ?>">Export XLSX</a>
    <?php endif; ?>
    <?php if ($canImport): ?>
      <a class="btn btn-outline-light btn-sm" href="accounting_journal_import.php?type=<?= htmlspecialchars($type) ?>">Import XLSX</a>
    <?php endif; ?>
  </div>
</div>

<div class="aj-toolbar mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small text-muted">Search</label>
      <input id="ajSearch" type="search" class="form-control form-control-sm" placeholder="Search reference, party, account, description">
    </div>
    <div class="col-md-2">
      <label class="form-label small text-muted">From</label>
      <input id="ajDateFrom" type="date" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label small text-muted">To</label>
      <input id="ajDateTo" type="date" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label small text-muted">Rows</label>
      <select id="ajLimit" class="form-select form-select-sm">
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="0">All</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button id="ajRefresh" class="btn btn-outline-secondary btn-sm flex-fill" type="button">Refresh</button>
      <button id="ajClear" class="btn btn-outline-secondary btn-sm flex-fill" type="button">Clear</button>
    </div>
  </div>
  <div class="d-flex justify-content-between gap-2 flex-wrap mt-3">
    <small id="ajCount" class="text-muted">Loading...</small>
    <div class="aj-summary">
      <span>Debit: <strong id="ajTotalDebit">0.00</strong></span>
      <span>Credit: <strong id="ajTotalCredit">0.00</strong></span>
      <span>Sundry Debit: <strong id="ajTotalSundryDebit">0.00</strong></span>
      <span>Sundry Credit: <strong id="ajTotalSundryCredit">0.00</strong></span>
    </div>
  </div>
</div>

<div class="aj-table-wrap">
  <table class="table table-bordered table-hover table-striped table-sm mb-0" id="accountingJournalTable">
    <thead>
      <tr>
        <?php foreach ($columns as $column): ?>
          <?php $isMoney = in_array($column, ['debit','credit','sundry_debit','sundry_credit'], true); ?>
          <th class="<?= $isMoney ? 'text-end' : '' ?>">
            <?php if (in_array($column, ['entry_date','debit','credit','sundry_debit','sundry_credit'], true)): ?>
              <button class="aj-sort" data-sort-desc="<?= htmlspecialchars($column === 'entry_date' ? 'date_desc' : $column . '_desc') ?>" data-sort-asc="<?= htmlspecialchars($column === 'entry_date' ? 'date_asc' : $column . '_asc') ?>"><?= htmlspecialchars($labels[$column] ?? $column) ?></button>
            <?php else: ?>
              <?= htmlspecialchars($labels[$column] ?? $column) ?>
            <?php endif; ?>
          </th>
        <?php endforeach; ?>
        <th>Entered By</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="ajBody"><tr><td colspan="<?= count($columns) + 2 ?>" class="aj-empty">Loading entries...</td></tr></tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
  <div class="btn-group btn-group-sm">
    <button id="ajPrev" class="btn btn-outline-secondary" type="button">Previous</button>
    <button id="ajNext" class="btn btn-outline-secondary" type="button">Next</button>
  </div>
  <small id="ajPage" class="text-muted">Page 1 of 1</small>
</div>

<script>
(function(){
const type=<?= json_encode($type) ?>;
const columns=<?= json_encode($columns) ?>;
const labels=<?= json_encode($labels) ?>;
const canEdit=<?= $canEdit ? 'true' : 'false' ?>;
const canDelete=<?= $canDelete ? 'true' : 'false' ?>;
const canExport=<?= $canExport ? 'true' : 'false' ?>;
let page=1, sort='date_desc', pages=1;
const q=s=>document.querySelector(s);
const money=v=>Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
function params(){
  const p=new URLSearchParams({action:'list',type,sort,page:String(page),limit:q('#ajLimit').value});
  if(q('#ajSearch').value.trim()) p.set('search',q('#ajSearch').value.trim());
  if(q('#ajDateFrom').value) p.set('date_from',q('#ajDateFrom').value);
  if(q('#ajDateTo').value) p.set('date_to',q('#ajDateTo').value);
  return p;
}
async function load(){
  const res=await fetch('accounting_journal_api.php?'+params().toString(),{credentials:'same-origin'});
  const json=await res.json();
  if(!json.ok){q('#ajBody').innerHTML='<tr><td colspan="16" class="aj-empty">'+esc(json.message||'Unable to load entries')+'</td></tr>';return;}
  page=json.page; pages=json.pages;
  q('#ajCount').textContent=json.total+' entries';
  q('#ajPage').textContent='Page '+json.page+' of '+json.pages;
  q('#ajTotalDebit').textContent=money(json.totals.debit);
  q('#ajTotalCredit').textContent=money(json.totals.credit);
  q('#ajTotalSundryDebit').textContent=money(json.totals.sundry_debit);
  q('#ajTotalSundryCredit').textContent=money(json.totals.sundry_credit);
  if(canExport){
    const exportParams=params();
    exportParams.delete('action');
    exportParams.delete('page');
    exportParams.delete('limit');
    q('#ajExport').href='accounting_journal_export.php?'+exportParams.toString();
  }
  q('#ajPrev').disabled=page<=1;
  q('#ajNext').disabled=page>=pages;
  q('#ajBody').innerHTML=(json.data||[]).length ? json.data.map(row=>{
    const actions=[
      canEdit?`<a class="btn btn-outline-primary btn-sm" href="accounting_journal_entry.php?type=${encodeURIComponent(type)}&id=${row.id}">Edit</a>`:'',
      canDelete?`<button class="btn btn-outline-danger btn-sm" type="button" data-delete="${row.id}">Delete</button>`:''
    ].join(' ');
    const cells=columns.map(col=>{
      const moneyCol=['debit','credit','sundry_debit','sundry_credit'].includes(col);
      return `<td class="${moneyCol?'aj-money':''}">${moneyCol?money(row[col]):esc(row[col])}</td>`;
    }).join('');
    return `<tr>${cells}<td>${esc(row.entered_by)}</td><td class="text-nowrap">${actions}</td></tr>`;
  }).join('') : '<tr><td colspan="'+(columns.length+2)+'" class="aj-empty">No entries found.</td></tr>';
  document.querySelectorAll('.aj-sort').forEach(btn=>btn.classList.toggle('active',btn.dataset.sortDesc===sort||btn.dataset.sortAsc===sort));
}
let timer=null;
q('#ajSearch').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load();},250);});
['#ajDateFrom','#ajDateTo','#ajLimit'].forEach(sel=>q(sel).addEventListener('change',()=>{page=1;load();}));
q('#ajRefresh').addEventListener('click',load);
q('#ajClear').addEventListener('click',()=>{q('#ajSearch').value='';q('#ajDateFrom').value='';q('#ajDateTo').value='';page=1;load();});
q('#ajPrev').addEventListener('click',()=>{if(page>1){page--;load();}});
q('#ajNext').addEventListener('click',()=>{if(page<pages){page++;load();}});
document.addEventListener('click',async e=>{
  const sortBtn=e.target.closest('.aj-sort');
  if(sortBtn){sort=sort===sortBtn.dataset.sortDesc?sortBtn.dataset.sortAsc:sortBtn.dataset.sortDesc;page=1;load();}
  const del=e.target.closest('[data-delete]');
  if(del && confirm('Delete this journal entry?')){
    const res=await fetch('accounting_journal_api.php?action=delete&type='+encodeURIComponent(type),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:del.dataset.delete})});
    const json=await res.json();
    if(!json.ok) alert(json.message||'Unable to delete entry.');
    load();
  }
});
load();
})();
</script>

<?php include "templates/footer.php"; ?>
