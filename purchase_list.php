<?php
require "config.php";
require "helpers.php";
redirect_if_not_logged_in();
$isAdmin = current_user_is_admin($pdo);

include "templates/header.php";
?>

<style>
/* ===== FULL-BLEED LAYOUT: no outer margins, table-only scroll ===== */
html, body { height: 100%; overflow: hidden; background: #fff; }

/* Nuke Bootstrap container max-width/padding so it truly spans edge-to-edge */
.container, .container-sm, .container-md, .container-lg, .container-xl, .container-xxl,
main.container, .content.container {
  max-width: 100% !important;
  padding-left: 0 !important; padding-right: 0 !important;
  margin-left: 0 !important;  margin-right: 0 !important;
}
/* Some themes wrap navbar contents in a container—flatten that too */
.navbar .container, .navbar .container-fluid {
  padding-left: 0 !important; padding-right: 0 !important;
}

.page-root { height: 100vh; display: flex; flex-direction: column; }

/* Top bars (compact) */
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
.head-row .tools{ margin-left:auto; display:flex; gap:.5rem; align-items:center; }

/* TABLE is the ONLY scroll area */
.table-wrap{
  flex:1 1 auto; overflow-y:auto; overflow-x:hidden;
  padding-bottom: 4.5rem;             /* keeps the sticky totals row above the bottom bar */
}

/* Table visuals + sticky head/foot inside the scroller */
#journalTable{ width:100%; table-layout:auto; } /* natural sizing (readable) */
#journalTable thead th{ position: sticky; top:0; z-index:10; background:#fff; white-space:nowrap; }
#journalTable thead th button.sort-th{
  appearance:none;
  border:0;
  background:transparent;
  padding:0;
  margin:0;
  font:inherit;
  font-weight:600;
  color:inherit;
  cursor:pointer;
}
#journalTable thead th button.sort-th:hover{
  text-decoration: underline;
}
#journalTable thead th button.sort-th.active{
  color:#0d6efd;
}
#journalTable thead th button.sort-th.active::after{
  content:"";
}
#journalTable thead th button.sort-th.sort-th-right{
  width:100%;
  text-align:right;
}
#journalTable tfoot td{
  position: sticky; bottom:0; z-index:9; background:#fff;
  border-top:2px solid #e9ecef !important;
  box-shadow: 0 -2px 6px rgba(0,0,0,.04); /* subtle lift so totals are visible */
}

/* Compact font sizing for better density */
#journalTable th, #journalTable td { font-size: .82rem; line-height: 1.25; }
#journalTable thead th { font-weight: 600; font-size: .85rem; }

.table-sm td, .table-sm th{ padding-top:.4rem; padding-bottom:.4rem; }
.table-hover tbody tr:hover{ background:#fafcff; }

/* Readability: wrap long text, avoid horizontal scroll */
#journalTable th, #journalTable td{ white-space:normal; word-break:break-word; }

/* Column specifics */
.w-actions{ width:60px; }
.w-no{ width:55px; }
.num{ font-variant-numeric: tabular-nums; }
.money-strong{ font-weight:700; }

/* Description column: internal scroller so rows don't grow too tall */
td.col-desc > .desc-box{
  display:block;
  max-height: 5.5rem;         /* ~4–5 lines; tweak as you like */
  overflow-y: auto;
  white-space: pre-wrap;       /* keep user’s line breaks */
}

/* Badges & footer label */
.chip{ padding:.1rem .45rem; border-radius:999px; font-size:.72rem; font-weight:600; letter-spacing:.02em; }
.chip-vat{ background:#e7f5ff; border:1px solid #cfe8ff; color:#0a58ca; }
.chip-nvat{ background:#f8f9fa; border:1px solid #e9ecef; color:#5c636a; }
.tfoot-label{ background:#f8fafc; font-weight:600; }

/* Bottom controls (fixed, not scrolling) */
.page-controls{
  border-top:1px solid var(--bs-border-color);
  padding:.4rem .75rem;
  display:flex; align-items:center; justify-content:space-between; gap:.5rem;
  position: sticky;
  bottom: 0;
  z-index: 20;
  background: #fff;
  box-shadow: 0 -2px 8px rgba(0,0,0,.06);
}
</style>

<div class="page-root">

  <!-- Top toolbar -->
<div class="journal-hero d-flex flex-wrap gap-2 align-items-center">
    <div class="d-flex gap-2">
      <?php if ($isAdmin): ?>
        <a class="btn btn-success btn-sm" id="exportXlsx" href="journal_export.php?search=&sort=date_desc">Export XLSX</a>
        <a class="btn btn-outline-primary btn-sm" href="journal_import.php">Import XLSX</a>
      <?php endif; ?>
    </div>

    <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
<div class="d-flex gap-2 align-items-center">
      <span class="text-muted small me-1">Month</span>
      <select id="monthPick" class="form-select form-select-sm" style="min-width:140px">
        <option value="">— Month —</option>
        <option value="1">January</option><option value="2">February</option><option value="3">March</option>
        <option value="4">April</option><option value="5">May</option><option value="6">June</option>
        <option value="7">July</option><option value="8">August</option><option value="9">September</option>
        <option value="10">October</option><option value="11">November</option><option value="12">December</option>
      </select>
      <span class="text-muted small ms-2 me-1">Year</span>
      <select id="yearPick" class="form-select form-select-sm" style="min-width:110px"></select>
      <input type="hidden" id="dateFrom">
      <input type="hidden" id="dateTo">
</div>
</div>
  </div>

  <!-- Heading + search/sort -->
  <div class="head-row">
    <h3>Purchase Journal</h3>
    <div class="tools">
      <input type="text" id="journalSearch" class="form-control form-control-sm" style="min-width:260px"
             placeholder="Search supplier, description, project, ref, remarks..." />
      <small class="text-muted">Click the column labels to sort.</small>
      <small id="journalCount" class="text-muted ms-2">0 results</small>
    </div>
  </div>

  <!-- TABLE: only scroller -->
  <div class="table-wrap">
    <table class="table table-bordered table-hover table-striped table-sm align-middle" id="journalTable">

      <colgroup>
        <col style="width:90px">
        <col style="width:55px">
        <col style="width:110px">
        <col style="width:220px">
        <col style="width:90px">
        <col style="width:130px">
        <col style="width:90px">
        <col style="width:220px">
        <col style="width:280px">
        <col style="width:180px">
        <col style="width:120px">
        <col style="width:120px">
        <col style="width:120px">
        <col style="width:130px">
        <col style="width:120px">
        <col style="width:120px">
        <col style="width:120px">
        <col style="width:170px">
        <col style="width:180px">
      </colgroup>

      <thead class="table-light">
        <tr>
          <?php $currentSort = $_GET['sort'] ?? 'date_desc'; include "purchase_list_sort_headers.php"; ?>
        </tr>
      </thead>

      <tbody id="journalBody">
        <tr><td colspan="19" class="text-center py-4">Loading…</td></tr>
      </tbody>

      <tfoot id="journalFoot">
        <tr>
          <td class="tfoot-label text-end" colspan="10">Page totals</td>
          <td class="text-end fw-bold num" id="foot_input_vat">0.00</td>
          <td class="text-end fw-bold num" id="foot_vatable">0.00</td>
          <td class="text-end fw-bold num" id="foot_non_vat">0.00</td>
          <td class="text-end fw-bold num" id="foot_total">0.00</td>
          <td class="text-end fw-bold num" id="foot_cash">0.00</td>
          <td colspan="4"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Fixed bottom controls -->
  <div class="page-controls">
    <div class="small text-muted">Tip: use Presets for quick date filters.</div>
    <div class="d-flex align-items-center gap-2">
      <div class="input-group input-group-sm" style="width: 190px;">
        <span class="input-group-text">Rows</span>
        <!-- ✅ ALL is default (value=0) -->
        <select id="pageSize" class="form-select">
          <option value="0" selected>All</option>
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
          <option value="500">500</option>
          <option value="1000">1000</option>
        </select>
      </div>
      <button class="btn btn-sm btn-outline-secondary" id="prevPage">Prev</button>
      <span id="pageInfo" class="small"></span>
      <div class="input-group input-group-sm" style="width: 140px;">
        <span class="input-group-text">Go</span>
        <input id="pageJump" type="number" min="1" class="form-control" placeholder="#">
      </div>
      <button class="btn btn-sm btn-outline-secondary" id="nextPage">Next</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast" class="position-fixed bottom-0 end-0 p-3" style="z-index:1080;"></div>

<script>
(function(){
  const initialSort = <?= json_encode($_GET['sort'] ?? 'date_desc') ?>;
  const q = (sel) => document.querySelector(sel);
  const bodyEl = q('#journalBody');
  const footInputVat = q('#foot_input_vat');
  const footVatable  = q('#foot_vatable');
  const footNonVat   = q('#foot_non_vat');
  const footTotal    = q('#foot_total');
  const footCash     = q('#foot_cash');

  const searchEl = q('#journalSearch');
  const countEl = q('#journalCount');
  const pageInfoEl = q('#pageInfo');
  const prevBtn = q('#prevPage');
  const nextBtn = q('#nextPage');

  const fromEl = q('#dateFrom');
  const toEl = q('#dateTo');
  const presetEl = q('#datePreset');
  const clearBtn = q('#clearDates');


  const exportEl = q('#exportXlsx');

  const monthEl = q('#monthPick');
  const yearEl  = q('#yearPick');
  const applyMonthBtn = q('#applyMonth');

  // populate year dropdown (current year ±5)
  (function initYears(){
    const now = new Date();
    const y0 = now.getFullYear();
    const start = y0 - 5;
    const end   = y0 + 5;
    yearEl.innerHTML = '';
    for(let y=start;y<=end;y++){
      const opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      if(y===y0) opt.selected = true;
      yearEl.appendChild(opt);
    }
    // default month = current month
    monthEl.value = String(now.getMonth()+1);
  })();

  function lastDayOfMonth(year, month1to12){
    return new Date(year, month1to12, 0).getDate(); // month param is 1..12, day 0 => last day previous month
  }

  function applyMonthFilter(){
    const m = parseInt(monthEl.value,10);
    const y = parseInt(yearEl.value,10);
    if(!m || !y) return;
    const last = lastDayOfMonth(y, m);
    fromEl.value = `${y}-${pad2(m)}-01`;
    toEl.value   = `${y}-${pad2(m)}-${pad2(last)}`;
    if(presetEl) presetEl.value = '';
    page = 1;
    loadJournal();
  }

  if(applyMonthBtn){
    applyMonthBtn.addEventListener('click', (e)=>{ e.preventDefault(); applyMonthFilter(); });
  }
  if(monthEl){
    monthEl.addEventListener('change', ()=>{ applyMonthFilter(); });
  }
  if(yearEl){
    yearEl.addEventListener('change', ()=>{ applyMonthFilter(); });
  }

  // If user manually changes date range, clear month selection (keeps UI honest)
  [fromEl,toEl].forEach(el=>{
    el?.addEventListener('change', ()=>{ if(monthEl) monthEl.value=''; if(presetEl) presetEl.value=''; });
  });

  function updateExportHref(){
    if(!exportEl) return;
    const params = new URLSearchParams({
      search: searchEl.value || '',
      sort: getSortValue()
    });
    const df = fromEl.value?.trim();
    const dt = toEl.value?.trim();
    if(df) params.set('date_from', df);
    if(dt) params.set('date_to', dt);
    // also include month/year when set
    if(monthEl && monthEl.value) params.set('month', monthEl.value);
    if(yearEl && yearEl.value) params.set('year', yearEl.value);
    exportEl.href = 'journal_export.php?' + params.toString();
  }


  const sizeEl = q('#pageSize');
  const jumpEl = q('#pageJump');

  let page = 1;

  // ✅ limit=0 means "ALL" and we DO NOT send limit to API
  let limit = 0;
  let lastKnownPages = 1;

  function toast(msg, kind='info'){
    const el = document.createElement('div');
    el.className = 'toast align-items-center show border-0';
    el.role = 'alert';
    el.style.minWidth = '260px';
    el.innerHTML = `
      <div class="d-flex text-bg-${kind}">
        <div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    q('#toast').appendChild(el);
    setTimeout(()=>el.remove(), 3500);
  }

  function fmt(n){
    if(n==null||n==='') return '0.00';
    const v = Number(n);
    return isFinite(v) ? v.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '0.00';
  }
  function esc(s){ const d=document.createElement('div'); d.textContent = s==null?'' : String(s); return d.innerHTML; }
  function pad2(n){ return String(n).padStart(2,'0'); }
  function toISO(d){ return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; }
  function qAll(sel){ return Array.from(document.querySelectorAll(sel)); }
  function getSortValue(){
    const active = q('#journalTable thead button[data-sort-base].active');
    return active?.dataset.sortCurrent || 'date_desc';
  }
  function setSortValue(sort){
    qAll('#journalTable thead button[data-sort-base]').forEach(btn => {
      const isActive = btn.dataset.sortAsc === sort || btn.dataset.sortDesc === sort;
      btn.classList.toggle('active', isActive);
      const label = btn.textContent.replace(/[ ↑↓]$/, '');
      if (isActive) {
        btn.dataset.sortCurrent = sort;
        btn.innerHTML = label + (sort === btn.dataset.sortAsc ? ' ↑' : ' ↓');
      } else {
        delete btn.dataset.sortCurrent;
        btn.innerHTML = label;
      }
    });
  }

  function applyPreset(v){
    const now = new Date();
    let start=null,end=null;
    if(v==='today'){ start=end=new Date(); }
    else if(v==='yesterday'){ const y=new Date(now); y.setDate(y.getDate()-1); start=end=y; }
    else if(v==='last7'){ end=new Date(); start=new Date(); start.setDate(end.getDate()-6); }
    else if(v==='thisMonth'){ start=new Date(now.getFullYear(),now.getMonth(),1); end=new Date(now.getFullYear(),now.getMonth()+1,0); }
    else if(v==='lastMonth'){ start=new Date(now.getFullYear(),now.getMonth()-1,1); end=new Date(now.getFullYear(),now.getMonth(),0); }
    else if(v==='thisYear'){ start=new Date(now.getFullYear(),0,1); end=new Date(now.getFullYear(),11,31); }
    if(start&&end){ fromEl.value=toISO(start); toEl.value=toISO(end); if(monthEl) monthEl.value=''; page=1; loadJournal(); }
  }

  async function hardDelete(id){
    try{
      const res = await fetch('journal_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify({ id })
      });
      const j = await res.json().catch(()=>({ok:false,message:'Invalid response'}));
      if(!j.ok) throw new Error(j.message || 'Delete failed');
      toast('Entry permanently deleted.','success');
      loadJournal();
    }catch(e){ toast(esc(e.message||e),'danger'); }
  }

  async function loadJournal(){
    const params = new URLSearchParams({
      search: searchEl.value || '',
      sort:   getSortValue()
    });

    // ✅ ONLY send paging params when limit > 0
    if (limit > 0) {
      params.set('page', String(page));
      params.set('limit', String(limit));
    }

    const df = fromEl.value?.trim();
    const dt = toEl.value?.trim();
    if(df) params.set('date_from', df);
    if(dt) params.set('date_to', dt);

    updateExportHref();

    bodyEl.innerHTML = `<tr><td colspan="19" class="text-center py-4">Loading…</td></tr>`;

    try{
      const res = await fetch(`journal_api.php?` + params.toString(), { credentials: 'same-origin' });
      const json = await res.json();
      if(!json.ok) throw new Error(json.message || 'Failed to load');

      const rows = json.data || [];
      const total = json.total || 0;

      const apiUsePaging = !!json.usePaging;
      lastKnownPages = apiUsePaging ? (json.pages || 1) : 1;

      countEl.textContent = `${total} result${total===1?'':'s'}`;

      if (limit > 0) {
        pageInfoEl.textContent = `Page ${json.page} of ${json.pages}`;
        prevBtn.disabled = json.page <= 1;
        nextBtn.disabled = json.page >= json.pages;
        jumpEl.disabled = false;
      } else {
        pageInfoEl.textContent = `All results`;
        prevBtn.disabled = true;
        nextBtn.disabled = true;
        jumpEl.disabled = true;
        jumpEl.value = '';
      }

      if(!rows.length){
        bodyEl.innerHTML = `<tr><td colspan="19" class="text-center py-4">No results</td></tr>`;
        [footInputVat,footVatable,footNonVat,footTotal,footCash].forEach(el=>{ if(el) el.textContent=''; });
        return;
      }

      let sumInputVat=0, sumVatable=0, sumNonVat=0, sumTotal=0, sumCash=0;

      bodyEl.innerHTML = rows.map((e,i)=>{
        const iv=Number(e.input_vat||0), vt=Number(e.vatable||0), nv=Number(e.non_vat||0), tt=Number(e.total||0),
              cs=Number(e.cash||0);

        sumInputVat+=iv; sumVatable+=vt; sumNonVat+=nv; sumTotal+=tt; sumCash+=cs;

        const chip = e.vat_nvat==='VAT'
          ? `<span class="chip chip-vat">VAT</span>`
          : `<span class="chip chip-nvat">NVAT</span>`;

        const editBtn = e.is_owner
          ? `<a class="btn btn-sm btn-outline-primary me-1" href="purchase_edit.php?id=${esc(e.id)}" title="Edit"><i class="bi bi-pencil"></i></a>`
          : '';

        const delBtn = e.is_owner
          ? `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${esc(e.id)}" title="Hard delete (permanent)"><i class="bi bi-trash"></i></button>`
          : '';

        const enteredBy = esc(
          e.entered_by || e.created_by_name || e.created_by || e.user_name || e.owner_name || ''
        );

        const rowNo = (limit > 0) ? (((page-1)*limit)+i+1) : (i+1);

        return `<tr>
          <td class="text-center">${editBtn}${delBtn}</td>
          <td class="text-center num">${rowNo}</td>
          <td>${esc(e.date)}</td>
          <td>${esc(e.supplier)}</td>
          <td>${esc(e.ref_page)}</td>
          <td>${esc(e.tin)}</td>
          <td>${chip}</td>
          <td>${esc(e.address)}</td>
          <td class="col-desc"><div class="desc-box">${esc(e.description)}</div></td>
          <td>${esc(e.project_name)}</td>
          <td class="text-end num">${fmt(iv)}</td>
          <td class="text-end num">${fmt(vt)}</td>
          <td class="text-end num">${fmt(nv)}</td>
          <td class="text-end money-strong num">${fmt(tt)}</td>
          <td class="text-end num">${fmt(cs)}</td>
          <td class="text-end num">${fmt(e.debit)}</td>
          <td class="text-end num">${fmt(e.credit)}</td>
          <td>${enteredBy}</td>
          <td>${esc(e.remarks)}</td>
        </tr>`;
      }).join('');

      footInputVat.textContent = fmt(sumInputVat);
      footVatable.textContent  = fmt(sumVatable);
      footNonVat.textContent   = fmt(sumNonVat);
      footTotal.textContent    = fmt(sumTotal);
      footCash.textContent     = fmt(sumCash);

    }catch(err){
      bodyEl.innerHTML = `<tr><td colspan="19" class="text-danger text-center py-4">${esc(err.message||err)}</td></tr>`;
      [footInputVat,footVatable,footNonVat,footTotal,footCash].forEach(el=>{ if(el) el.textContent=''; });
    }
  }

  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

  // Events (guarded: toolbar elements may not exist on some layouts)
  if (searchEl) searchEl.addEventListener('input', debounce(()=>{ page=1; loadJournal(); }, 350));
  if (fromEl)   fromEl.addEventListener('change', ()=>{ page=1; loadJournal(); });
  if (toEl)     toEl.addEventListener('change',   ()=>{ page=1; loadJournal(); });
  if (presetEl) presetEl.addEventListener('change', (e)=>{ if(e.target.value){ applyPreset(e.target.value); e.target.value=''; } });
  if (clearBtn) clearBtn.addEventListener('click', ()=>{ if(fromEl) fromEl.value=''; if(toEl) toEl.value=''; page=1; loadJournal(); });

  if (prevBtn) prevBtn.addEventListener('click', ()=>{ if(limit>0 && page>1){ page--; loadJournal(); } });
  if (nextBtn) nextBtn.addEventListener('click', ()=>{ if(limit>0 && page<lastKnownPages){ page++; loadJournal(); } });

  if (sizeEl) sizeEl.addEventListener('change', ()=>{
    const v = parseInt(sizeEl.value||'0',10);
    limit = isNaN(v) ? 0 : v;
    page = 1;

    if (limit <= 0) {
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      jumpEl.disabled = true;
      jumpEl.value = '';
    }

    loadJournal();
  });

  if (jumpEl) jumpEl.addEventListener('change', ()=>{
    if (limit <= 0) return;
    const v = parseInt(jumpEl.value||'1',10);
    if(!isNaN(v) && v>0){ page=v; loadJournal(); }
  });

  // Delegated delete
  const tableEl = q('#journalTable');
  if (tableEl) tableEl.addEventListener('click', (ev)=>{
    const sortBtn = ev.target.closest('button[data-sort-base]');
    if(sortBtn){
      const current = sortBtn.dataset.sortCurrent || sortBtn.dataset.sortAsc || 'date_desc';
      const next = current === sortBtn.dataset.sortAsc
        ? (sortBtn.dataset.sortDesc || current)
        : (sortBtn.dataset.sortAsc || current);
      setSortValue(next);
      page = 1;
      loadJournal();
      return;
    }
    const btn = ev.target.closest('.btn-delete');
    if(!btn) return;
    const id = btn.getAttribute('data-id');
    if(!id) return;
    if(confirm('Hard delete this entry permanently? This cannot be undone.')) hardDelete(id);
  });

  // Initial: read dropdown (default is All)
  (function init(){
    const v = parseInt(sizeEl?.value || '0', 10);
    limit = isNaN(v) ? 0 : v;
    setSortValue(initialSort);
    if (limit <= 0) {
      if (prevBtn) prevBtn.disabled = true;
      if (nextBtn) nextBtn.disabled = true;
      if (jumpEl) jumpEl.disabled = true;
    }
    loadJournal();
  })();
})();
</script>

<?php include "templates/footer.php"; ?>

