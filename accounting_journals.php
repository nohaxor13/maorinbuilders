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
html, body { height: 100%; overflow: hidden; background: #fff; }
.container, .container-sm, .container-md, .container-lg, .container-xl, .container-xxl,
main.container, .content.container {
  max-width: 100% !important;
  padding-left: 0 !important; padding-right: 0 !important;
  margin-left: 0 !important;  margin-right: 0 !important;
}
.navbar .container, .navbar .container-fluid {
  padding-left: 0 !important; padding-right: 0 !important;
}
.page-root { height: 100vh; display: flex; flex-direction: column; }
.journal-hero{
  background: linear-gradient(135deg,#e8f5ff 0%,#fff 45%);
  border-bottom: 1px solid var(--bs-border-color);
  padding:.5rem .75rem;
}
.head-row{
  padding:.5rem .75rem; border-bottom:1px solid var(--bs-border-color);
  display:flex; flex-wrap:wrap; align-items:center; gap:.5rem;
}
.head-row h3{ margin:0; font-weight:700; letter-spacing:.2px; }
.head-row .tools{ margin-left:auto; display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
.table-wrap{
  flex:1 1 auto; overflow-y:auto; overflow-x:auto;
  padding-bottom: 4.5rem;
}
#accountingJournalTable{ width:100%; table-layout:auto; min-width:1380px; }
#accountingJournalTable thead th{ position: sticky; top:0; z-index:10; background:#fff; white-space:nowrap; font-weight:600; font-size:.85rem; }
#accountingJournalTable th,#accountingJournalTable td{ font-size:.82rem; line-height:1.25; white-space:normal; word-break:break-word; }
.w-actions{ width:90px; }
.w-no{ width:55px; }
.table-sm td,.table-sm th{ padding-top:.4rem; padding-bottom:.4rem; }
.table-hover tbody tr:hover{ background:#fafcff; }
.aj-sort{
  appearance:none; border:0; background:transparent; padding:0; margin:0;
  font:inherit; font-weight:600; color:inherit; cursor:pointer;
}
.aj-sort:hover{ text-decoration:underline; }
.aj-sort.active{ color:#0d6efd; }
.aj-money{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.num{ font-variant-numeric: tabular-nums; }
.tfoot-label{ background:#f8fafc; font-weight:600; }
#accountingJournalTable tfoot td{
  position: sticky; bottom:0; z-index:9; background:#fff;
  border-top:2px solid #e9ecef !important;
  box-shadow:0 -2px 6px rgba(0,0,0,.04);
}
.aj-empty{padding:28px;text-align:center;color:#64748b}
.page-controls{
  border-top:1px solid var(--bs-border-color);
  padding:.4rem .75rem;
  display:flex; align-items:center; justify-content:space-between; gap:.5rem;
  position:sticky; bottom:0; z-index:20; background:#fff;
  box-shadow:0 -2px 8px rgba(0,0,0,.06);
}
</style>

<div class="page-root">
<div class="journal-hero d-flex flex-wrap gap-2 align-items-center">
  <div class="d-flex gap-2 flex-wrap">
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Switch Journal</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="purchase_list.php">Purchase Journal</a></li>
        <li><hr class="dropdown-divider"></li>
        <?php foreach (mb_accounting_journal_types() as $key => $item): ?>
          <li><a class="dropdown-item<?= $key===$type?' active':'' ?>" href="<?= htmlspecialchars(mb_journal_list_url($key)) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if ($canCreate): ?>
      <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(mb_journal_entry_url($type)) ?>">New Entry</a>
    <?php endif; ?>
    <?php if ($canExport): ?>
      <a class="btn btn-success btn-sm" id="ajExport" href="accounting_journal_export.php?type=<?= htmlspecialchars($type) ?>">Export XLSX</a>
    <?php endif; ?>
    <?php if ($canImport): ?>
      <a class="btn btn-outline-primary btn-sm" href="accounting_journal_import.php?type=<?= htmlspecialchars($type) ?>">Import XLSX</a>
    <?php endif; ?>
  </div>

  <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
    <div class="d-flex gap-2 align-items-center">
      <span class="text-muted small me-1">Month</span>
      <select id="monthPick" class="form-select form-select-sm" style="min-width:140px">
        <option value="">-- Month --</option>
        <option value="1">January</option><option value="2">February</option><option value="3">March</option>
        <option value="4">April</option><option value="5">May</option><option value="6">June</option>
        <option value="7">July</option><option value="8">August</option><option value="9">September</option>
        <option value="10">October</option><option value="11">November</option><option value="12">December</option>
      </select>
      <span class="text-muted small ms-2 me-1">Year</span>
      <select id="yearPick" class="form-select form-select-sm" style="min-width:110px"></select>
      <input type="hidden" id="ajDateFrom">
      <input type="hidden" id="ajDateTo">
    </div>
  </div>
</div>

<div class="head-row">
  <h3><?= htmlspecialchars($config['label']) ?></h3>
  <div class="tools">
    <input id="ajSearch" type="search" class="form-control form-control-sm" style="min-width:260px" placeholder="Search reference, party, account, description">
    <small class="text-muted">Click the column labels to sort.</small>
    <small id="ajCount" class="text-muted ms-2">0 results</small>
    <button id="ajRefresh" class="btn btn-outline-secondary btn-sm" type="button">Refresh</button>
    <button id="ajClear" class="btn btn-outline-secondary btn-sm" type="button">Clear</button>
  </div>
</div>

<div class="table-wrap">
  <table class="table table-bordered table-hover table-striped table-sm align-middle mb-0" id="accountingJournalTable">
    <thead class="table-light">
      <tr>
        <th class="w-actions">Actions</th>
        <th class="w-no text-end">#</th>
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
      </tr>
    </thead>
    <tbody id="ajBody"><tr><td colspan="<?= count($columns) + 2 ?>" class="aj-empty">Loading entries...</td></tr></tbody>
    <tfoot>
      <tr>
        <td colspan="2"></td>
        <?php $labelPrinted = false; ?>
        <?php foreach ($columns as $column): ?>
          <?php if ($column === 'debit'): ?>
            <td class="text-end fw-bold num" id="ajTotalDebit">0.00</td>
          <?php elseif ($column === 'credit'): ?>
            <td class="text-end fw-bold num" id="ajTotalCredit">0.00</td>
          <?php elseif ($column === 'sundry_debit'): ?>
            <td class="text-end fw-bold num" id="ajTotalSundryDebit">0.00</td>
          <?php elseif ($column === 'sundry_credit'): ?>
            <td class="text-end fw-bold num" id="ajTotalSundryCredit">0.00</td>
          <?php elseif (!$labelPrinted): $labelPrinted = true; ?>
            <td class="tfoot-label text-end">total</td>
          <?php else: ?>
            <td></td>
          <?php endif; ?>
        <?php endforeach; ?>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="page-controls">
  <div class="small text-muted">Tip: use Month and Year for quick date filters.</div>
  <div class="d-flex align-items-center gap-2">
    <div class="input-group input-group-sm" style="width:190px;">
      <span class="input-group-text">Rows</span>
      <select id="ajLimit" class="form-select">
        <option value="0" selected>All</option>
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="500">500</option>
      </select>
    </div>
    <button id="ajPrev" class="btn btn-sm btn-outline-secondary" type="button">Prev</button>
    <span id="ajPage" class="small"></span>
    <div class="input-group input-group-sm" style="width:140px;">
      <span class="input-group-text">Go</span>
      <input id="ajPageJump" type="number" min="1" class="form-control" placeholder="#">
    </div>
    <button id="ajNext" class="btn btn-sm btn-outline-secondary" type="button">Next</button>
  </div>
</div>
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
const setText=(sel,value)=>{const el=q(sel); if(el) el.textContent=value;};
const monthPick=q('#monthPick'), yearPick=q('#yearPick');
function initYears(){
  const now=new Date(), year=now.getFullYear();
  let html='<option value="">-- Year --</option>';
  for(let y=year+1;y>=year-8;y--) html+=`<option value="${y}" ${y===year?'selected':''}>${y}</option>`;
  yearPick.innerHTML=html;
}
function applyMonthYear(){
  const month=Number(monthPick.value||0), year=Number(yearPick.value||0);
  if(month && year){
    const start=new Date(year,month-1,1), end=new Date(year,month,0);
    q('#ajDateFrom').value=start.toISOString().slice(0,10);
    q('#ajDateTo').value=end.toISOString().slice(0,10);
  }else{
    q('#ajDateFrom').value='';
    q('#ajDateTo').value='';
  }
}
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
  setText('#ajTotalDebit',money(json.totals.debit));
  setText('#ajTotalCredit',money(json.totals.credit));
  setText('#ajTotalSundryDebit',money(json.totals.sundry_debit));
  setText('#ajTotalSundryCredit',money(json.totals.sundry_credit));
  if(canExport){
    const exportParams=params();
    exportParams.delete('action');
    exportParams.delete('page');
    exportParams.delete('limit');
    q('#ajExport').href='accounting_journal_export.php?'+exportParams.toString();
  }
  q('#ajPrev').disabled=page<=1;
  q('#ajNext').disabled=page>=pages;
  q('#ajBody').innerHTML=(json.data||[]).length ? json.data.map((row,index)=>{
    const actions=[
      canEdit?`<a class="btn btn-outline-primary btn-sm py-0 px-1" href="accounting_journal_entry.php?type=${encodeURIComponent(type)}&id=${row.id}">Edit</a>`:'',
      canDelete?`<button class="btn btn-outline-danger btn-sm py-0 px-1" type="button" data-delete="${row.id}">Del</button>`:''
    ].join(' ');
    const cells=columns.map(col=>{
      const moneyCol=['debit','credit','sundry_debit','sundry_credit'].includes(col);
      return `<td class="${moneyCol?'aj-money':''}">${moneyCol?money(row[col]):esc(row[col])}</td>`;
    }).join('');
    const rowNo=(Number(q('#ajLimit').value||0)>0 ? ((page-1)*Number(q('#ajLimit').value)+index+1) : index+1);
    return `<tr><td class="text-nowrap">${actions}</td><td class="text-end num">${rowNo}</td>${cells}<td>${esc(row.entered_by)}</td></tr>`;
  }).join('') : '<tr><td colspan="'+(columns.length+2)+'" class="aj-empty">No entries found.</td></tr>';
  document.querySelectorAll('.aj-sort').forEach(btn=>btn.classList.toggle('active',btn.dataset.sortDesc===sort||btn.dataset.sortAsc===sort));
}
let timer=null;
q('#ajSearch').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load();},250);});
['#ajDateFrom','#ajDateTo','#ajLimit'].forEach(sel=>q(sel).addEventListener('change',()=>{page=1;load();}));
monthPick.addEventListener('change',()=>{applyMonthYear();page=1;load();});
yearPick.addEventListener('change',()=>{applyMonthYear();page=1;load();});
q('#ajRefresh').addEventListener('click',load);
q('#ajClear').addEventListener('click',()=>{q('#ajSearch').value='';q('#ajDateFrom').value='';q('#ajDateTo').value='';monthPick.value='';page=1;load();});
q('#ajPrev').addEventListener('click',()=>{if(page>1){page--;load();}});
q('#ajNext').addEventListener('click',()=>{if(page<pages){page++;load();}});
q('#ajPageJump').addEventListener('change',()=>{const next=Number(q('#ajPageJump').value||0);if(next>=1&&next<=pages){page=next;load();}q('#ajPageJump').value='';});
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
initYears();
load();
})();
</script>

<?php include "templates/footer.php"; ?>
