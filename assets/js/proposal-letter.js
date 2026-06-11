(function(){
const api='proposals/api/proposal_letter_api.php', tpl='proposals/api/proposal_letter_template_api.php';
const paperSizeMap={'A4':'paper-a4','Letter':'paper-letter','Legal':'paper-legal','Short Bond Paper':'paper-short-bond','Long Bond Paper':'paper-long-bond'};
const printPageSizeMap={'A4':'A4','Letter':'letter','Legal':'legal','Short Bond Paper':'8.5in 11in','Long Bond Paper':'8.5in 13in'};
const zoomSteps=['fit','0.75','0.9','1','1.1','1.25','1.5'];
let proposalId=null,currentLetterId=null,dirty=false,wiredObserver=false,tempHeaderImageSrc='',currentContext=null;
function q(s){return document.querySelector(s)}
function qa(s){return Array.from(document.querySelectorAll(s))}
function post(url,data){return fetch(url,{method:'POST',body:data instanceof FormData?data:new URLSearchParams(data)}).then(r=>r.json())}
function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function editor(){return q('#plEditor')}
function modal(){return q('#proposalLetterModal')}
function sheet(){return q('#plSheet')}
function focusEditor(){const ed=editor(); if(ed) ed.focus();}
function defaultHeaderHtml(){
  return '<div class="pl-brand-line"><div class="pl-brand-mark">MB</div><div><div class="pl-brand-name">Maorin Builders</div><div class="pl-brand-sub">Construction - Renovation - Design & Build</div><div class="pl-brand-meta">Address - Contact Number - Email</div></div></div>';
}
function markDirty(){dirty=true; updateStatusBadge();}
function setCollapsed(section,open){
  const box=q(section);
  if(!box) return;
  box.dataset.open=open?'true':'false';
  const indicator=box.querySelector('.pl-collapse-indicator');
  if(indicator) indicator.textContent=open?'x':'+';
}
function togglePanel(className,buttonId,showText,hideText){
  const m=modal(); if(!m) return;
  m.classList.toggle(className);
  const hidden=m.classList.contains(className);
  const btn=q(buttonId);
  if(btn) btn.textContent=hidden?showText:hideText;
}
function updateStatusBadge(label){
  const badge=q('#plStatusBadge');
  if(!badge) return;
  badge.textContent=label || (dirty?'Unsaved changes':'Draft ready');
}
function updateContextLabel(ctx){
  currentContext=ctx||null;
  const meta=ctx ? `${ctx.proposal_number||'Proposal'} • ${ctx.proposal_title||ctx.project_name||'Untitled'}` : 'Select a proposal';
  const metaTarget=q('#plProposalMeta');
  if(metaTarget) metaTarget.textContent=meta;
  const proposalInfo=q('#plInfoProposalNumber');
  if(proposalInfo) proposalInfo.textContent=ctx ? (ctx.proposal_number||'Proposal') : 'Not selected';
  const projectInfo=q('#plInfoProjectName');
  if(projectInfo) projectInfo.textContent=ctx ? (ctx.project_name||ctx.proposal_title||'Project') : 'Choose a proposal to begin.';
}
function syncHeaderInputs(settings){
  const normalized=settings||{};
  const title=q('#plHeaderTitle'), subtitle=q('#plHeaderSubtitle'), line1=q('#plHeaderLine1'), line2=q('#plHeaderLine2'), show=q('#plShowHeader');
  if(title) title.value=normalized.header_title||'Maorin Builders';
  if(subtitle) subtitle.value=normalized.header_subtitle||'Construction - Renovation - Design & Build';
  if(line1) line1.value=normalized.header_line1||'Address - Contact Number - Email';
  if(line2) line2.value=normalized.header_line2||'';
  if(show) show.checked=String(normalized.show_header ?? 1)!=='0';
}
function applyHeaderTextFromInputs(){
  const header=q('#plDocHeader');
  header.dataset.mode='text';
  header.contentEditable='true';
  header.hidden=!q('#plShowHeader').checked;
  header.innerHTML='<div class="pl-brand-line"><div class="pl-brand-mark">MB</div><div><div class="pl-brand-name">'+escapeHtml(q('#plHeaderTitle').value||'Maorin Builders')+'</div><div class="pl-brand-sub">'+escapeHtml(q('#plHeaderSubtitle').value||'Construction - Renovation - Design & Build')+'</div><div class="pl-brand-meta">'+escapeHtml(q('#plHeaderLine1').value||'Address - Contact Number - Email')+'</div>'+(q('#plHeaderLine2').value?'<div class="pl-brand-meta">'+escapeHtml(q('#plHeaderLine2').value)+'</div>':'')+'</div></div>';
}
function applySettings(s){
  const settings=s||{};
  const header=q('#plDocHeader');
  q('#plHeaderMode').value=settings.header_mode||'text';
  q('#plExistingHeaderImagePath').value=settings.header_image_path||'';
  syncHeaderInputs(settings);
  header.hidden=String(settings.show_header ?? 1)==='0';
  if((settings.header_mode||'text')==='image' && (tempHeaderImageSrc || settings.header_image_path)){
    renderImageHeader(tempHeaderImageSrc || settings.header_image_path);
  }else{
    applyHeaderTextFromInputs();
  }
}
function renderImageHeader(path){
  const header=q('#plDocHeader');
  const raw=String(path||'');
  const src=(raw.startsWith('data:') || raw.startsWith('http') || raw.startsWith('/')) ? raw : raw.replace(/^\.\.\//,'');
  header.dataset.mode='image';
  header.hidden=!q('#plShowHeader').checked;
  header.contentEditable='false';
  header.innerHTML='<img class="pl-header-image" src="'+escapeHtml(src)+'" alt="Letterhead">';
}
function readHeaderSettings(){
  return {
    header_mode:q('#plHeaderMode').value,
    header_title:q('#plHeaderTitle').value||'Maorin Builders',
    header_subtitle:q('#plHeaderSubtitle').value||'Construction - Renovation - Design & Build',
    header_line1:q('#plHeaderLine1').value||'Address - Contact Number - Email',
    header_line2:q('#plHeaderLine2').value||'',
    show_header:q('#plShowHeader').checked?'1':'0',
    existing_header_image_path:q('#plExistingHeaderImagePath').value
  };
}
function applyPaperSize(value){
  const selected=value||'A4';
  Object.values(paperSizeMap).forEach(c=>sheet().classList.remove(c));
  sheet().classList.add(paperSizeMap[selected]||paperSizeMap.A4);
  q('#plPaperSize').value=selected;
  if(q('#plPreviewPaperSize')) q('#plPreviewPaperSize').value=selected;
  updatePrintStyle();
  requestAnimationFrame(applyZoom);
}
function updatePrintStyle(){
  let style=q('#proposalLetterDynamicPrintStyle');
  if(!style){style=document.createElement('style');style.id='proposalLetterDynamicPrintStyle';document.head.appendChild(style);}
  const size=printPageSizeMap[q('#plPaperSize').value]||'A4';
  style.textContent='@page { size: '+size+'; margin: 12mm; }';
}
function getSheetWidth(){
  const map={'A4':793.7,'Letter':816,'Short Bond Paper':816,'Legal':816,'Long Bond Paper':816};
  return map[q('#plPaperSize').value]||793.7;
}
function applyZoom(){
  const scaler=q('#plSheetScaler'), viewport=q('#plSheetViewport'), control=q('#plZoom');
  if(!scaler || !viewport || !control) return;
  let scale=1;
  if(control.value==='fit'){
    const padding=48;
    scale=Math.max(.55,(viewport.clientWidth-padding)/getSheetWidth());
  }else{
    scale=parseFloat(control.value)||1;
  }
  scaler.style.transform='scale('+scale+')';
  scaler.style.width=(getSheetWidth()*scale)+'px';
}
function stepZoom(direction){
  const control=q('#plZoom');
  if(!control) return;
  const current=control.value||'fit';
  let index=zoomSteps.indexOf(current);
  if(index===-1) index=zoomSteps.indexOf('1');
  index=Math.max(0,Math.min(zoomSteps.length-1,index+direction));
  control.value=zoomSteps[index];
  applyZoom();
}
function exec(command,value=null){focusEditor(); document.execCommand(command,false,value); markDirty();}
function wrapSelection(style){
  focusEditor();
  const sel=window.getSelection();
  if(!sel || !sel.rangeCount || sel.isCollapsed){document.execCommand('insertHTML',false,'<span style="'+style+'">&#8203;</span>'); markDirty(); return;}
  const range=sel.getRangeAt(0), span=document.createElement('span');
  span.setAttribute('style',style);
  span.appendChild(range.extractContents());
  range.insertNode(span);
  sel.removeAllRanges();
  const newRange=document.createRange();
  newRange.selectNodeContents(span);
  sel.addRange(newRange);
  markDirty();
}
function insertHtml(html){focusEditor(); document.execCommand('insertHTML',false,html); markDirty();}
function insertTable(){
  const rows=Math.max(1,Math.min(20,parseInt(prompt('Rows', '3'),10)||3));
  const cols=Math.max(1,Math.min(10,parseInt(prompt('Columns', '2'),10)||2));
  let html='<table class="proposal-editor-table"><tbody>';
  for(let r=0;r<rows;r++){html+='<tr>'; for(let c=0;c<cols;c++){html+='<td>'+((r===0&&c===0)?'Item':(r===0&&c===1)?'Details':'&nbsp;')+'</td>';} html+='</tr>';}
  insertHtml(html+'</tbody></table><p><br></p>');
}
function selectedTableCell(){
  const sel=window.getSelection();
  if(!sel || !sel.anchorNode) return null;
  const base=sel.anchorNode.nodeType===1?sel.anchorNode:sel.anchorNode.parentElement;
  return base ? base.closest('td,th') : null;
}
function addTableRow(){
  const cell=selectedTableCell();
  if(!cell) return alert('Click inside a table first.');
  const tr=cell.closest('tr'), clone=tr.cloneNode(true);
  clone.querySelectorAll('td,th').forEach(td=>td.innerHTML='&nbsp;');
  tr.after(clone);
  markDirty();
}
function addTableColumn(){
  const cell=selectedTableCell();
  if(!cell) return alert('Click inside a table first.');
  const index=[...cell.parentElement.children].indexOf(cell);
  cell.closest('table').querySelectorAll('tr').forEach(tr=>{
    const ref=tr.children[index];
    const td=document.createElement(ref&&ref.tagName==='TH'?'th':'td');
    td.innerHTML='&nbsp;';
    if(ref) ref.after(td); else tr.appendChild(td);
  });
  markDirty();
}
function loadContext(){
  return fetch(api+'?action=get_proposal_context&proposal_id='+encodeURIComponent(proposalId)).then(r=>r.json()).then(j=>{
    if(j.ok&&j.settings) applySettings(j.settings);
    updateContextLabel(j.context);
    return j;
  });
}
function generate(confirmReplace=true,ctx){
  if(confirmReplace && dirty && !confirm('Replace your current edits with proposal data?')) return Promise.resolve();
  return fetch(tpl+'?action=generate_template&proposal_id='+encodeURIComponent(proposalId)+'&template_type='+encodeURIComponent(q('#plTemplate').value))
    .then(r=>r.json())
    .then(j=>{
      if(!j.ok) return;
      editor().innerHTML=j.html;
      if(j.settings) applySettings(j.settings);
      if(ctx) updateContextLabel(ctx);
      currentLetterId=null;
      dirty=false;
      updateStatusBadge('Draft ready');
      requestAnimationFrame(applyZoom);
    });
}
function save(status){
  const fd=new FormData(), settings=readHeaderSettings();
  fd.append('proposal_id',proposalId);
  if(currentLetterId) fd.append('letter_id',currentLetterId);
  fd.append('template_type',q('#plTemplate').value);
  fd.append('paper_size',q('#plPaperSize').value);
  fd.append('subject','Proposal Letter');
  fd.append('body',editor().innerHTML);
  fd.append('status',status);
  Object.keys(settings).forEach(k=>fd.append(k,settings[k]));
  if(q('#plHeaderImage').files[0]) fd.append('header_image',q('#plHeaderImage').files[0]);
  post(api+'?action=save_letter',fd).then(j=>{
    if(!j.ok){alert(j.message||'Unable to save letter.'); return;}
    currentLetterId=j.letter_id||currentLetterId;
    dirty=false;
    updateStatusBadge((j.saved_status||status)==='final'?'Final saved':'Draft saved');
    if(j.header_image_path){q('#plExistingHeaderImagePath').value=j.header_image_path; tempHeaderImageSrc=''; if(q('#plHeaderMode').value==='image') renderImageHeader(j.header_image_path);}
    history();
  });
}
function statusPill(status){
  const key=(status||'draft').toLowerCase();
  return '<span class="pl-pill '+escapeHtml(key)+'">'+escapeHtml(key)+'</span>';
}
function formatDate(value){
  const d=value?new Date(value.replace(' ','T')):null;
  return d && !Number.isNaN(d.getTime()) ? d.toLocaleString() : (value||'');
}
function history(){
  fetch(api+'?action=list_letters&proposal_id='+encodeURIComponent(proposalId)).then(r=>r.json()).then(j=>{
    const letters=j.letters||[];
    q('#plHistory').innerHTML=letters.length
      ? letters.map(l=>`<div class="pl-history-card"><div class="pl-history-card-top"><div class="pl-history-main"><strong>${escapeHtml(l.letter_number||'Letter')}</strong><span class="pl-history-meta">${escapeHtml((l.template_type||'Template').slice(0,44))}</span><span class="pl-history-submeta">${escapeHtml(l.paper_size||'A4')} • ${escapeHtml(formatDate(l.updated_at))}</span></div>${statusPill(l.status)}</div><div class="pl-history-actions"><button type="button" data-letter="${l.id}">Open</button><button type="button" data-letter-print="${l.id}">Print</button><button type="button" data-letter-duplicate="${l.id}">Duplicate</button></div></div>`).join('')
      : '<div class="pl-empty-state">No saved letters yet. Save a draft to keep your work.</div>';
  });
}
function openLetter(id,after){
  return fetch(api+'?action=get_letter&letter_id='+encodeURIComponent(id)).then(r=>r.json()).then(j=>{
    if(j.ok&&j.letter){
      currentLetterId=j.letter.id;
      editor().innerHTML=j.letter.body;
      q('#plTemplate').value=j.letter.template_type||q('#plTemplate').value;
      applyPaperSize(j.letter.paper_size||'A4');
      applySettings(j.settings||{});
      dirty=false;
      updateStatusBadge(j.letter.status==='final'?'Final opened':'Draft opened');
      requestAnimationFrame(applyZoom);
      if(after) after(j.letter);
    }
  });
}
function enterPreview(){
  modal().classList.add('is-print-preview');
  editor().setAttribute('contenteditable','false');
  q('#plDocHeader').setAttribute('contenteditable','false');
}
function exitPreview(){
  modal().classList.remove('is-print-preview');
  editor().setAttribute('contenteditable','true');
  if(q('#plDocHeader').dataset.mode!=='image') q('#plDocHeader').setAttribute('contenteditable','true');
}
function printLetter(){
  updatePrintStyle();
  modal().classList.add('is-printing');
  setTimeout(()=>window.print(),50);
  setTimeout(()=>modal().classList.remove('is-printing'),500);
}
function ensureWorkspaceButtons(){
  document.querySelectorAll('[data-ws-view="proposals"]').forEach(viewBtn=>{
    const actions=viewBtn.closest('td, .workspace-actions, .mb-actions'), id=viewBtn.getAttribute('data-id');
    if(!actions || !id || actions.querySelector('.js-proposal-letter-open,[data-proposal-letter]')) return;
    const button=document.createElement('button');
    button.type='button';
    button.className='btn btn-sm btn-primary js-proposal-letter-open';
    button.dataset.proposalId=id;
    button.textContent='Generate Letter';
    const editBtn=actions.querySelector('[data-ws-edit="proposals"]');
    if(editBtn) editBtn.insertAdjacentElement('afterend', button); else actions.appendChild(button);
  });
}
function bindWorkspaceObserver(){
  if(wiredObserver) return;
  wiredObserver=true;
  const root=document.getElementById('workspaceContent') || document.body;
  new MutationObserver(()=>ensureWorkspaceButtons()).observe(root,{childList:true,subtree:true});
}
function open(id){
  proposalId=id;
  currentLetterId=null;
  tempHeaderImageSrc='';
  dirty=false;
  modal().hidden=false;
  modal().classList.remove('is-details-collapsed','is-history-collapsed');
  q('#plToggleDetailsPanel').textContent='Hide Details';
  q('#plToggleHistoryPanel').textContent='Hide History';
  setCollapsed('.pl-collapsible',false);
  exitPreview();
  loadContext().then(j=>generate(false,j.context)).then(history);
}
document.addEventListener('click',e=>{
  const b=e.target.closest('button,.js-proposal-letter-open,[data-proposal-letter],[data-pl-command],[data-letter],[data-letter-print],[data-letter-duplicate]');
  if(!b) return;
  if(b.matches('.js-proposal-letter-open,[data-proposal-letter]')) open(b.dataset.proposalId);
  if(b.matches('[data-plm-close]')){ if(!dirty||confirm('Close without saving changes?')) modal().hidden=true; }
  if(b.dataset.plCommand) exec(b.dataset.plCommand);
  if(b.id==='plInsertRule') insertHtml('<hr>');
  if(b.id==='plInsertTable') insertTable();
  if(b.id==='plAddTableRow') addTableRow();
  if(b.id==='plAddTableColumn') addTableColumn();
  if(b.id==='plResetTemplate'&&confirm('Regenerate from proposal data? Manual edits will be replaced.')) generate(false,currentContext);
  if(b.id==='plSaveDraft') save('draft');
  if(b.id==='plSaveFinal') save('final');
  if(b.id==='plPreview') enterPreview();
  if(b.id==='plBackToEdit') exitPreview();
  if(b.id==='plPrint' || b.id==='plPreviewPrint') printLetter();
  if(b.id==='plZoomOut') stepZoom(-1);
  if(b.id==='plZoomIn') stepZoom(1);
  if(b.id==='plToggleLetterhead' || b.id==='plToggleLetterheadPanel') setCollapsed('.pl-collapsible', q('.pl-collapsible').dataset.open!=='true');
  if(b.id==='plToggleDetailsPanel') togglePanel('is-details-collapsed','#plToggleDetailsPanel','Show Details','Hide Details');
  if(b.id==='plToggleHistoryPanel') togglePanel('is-history-collapsed','#plToggleHistoryPanel','Show History','Hide History');
  if(b.dataset.letter) openLetter(b.dataset.letter);
  if(b.dataset.letterPrint) openLetter(b.dataset.letterPrint,()=>{enterPreview(); printLetter();});
  if(b.dataset.letterDuplicate) openLetter(b.dataset.letterDuplicate,()=>{currentLetterId=null; dirty=true; updateStatusBadge('Duplicated draft');});
});
document.addEventListener('change',e=>{
  if(e.target.id==='plPaperSize' || e.target.id==='plPreviewPaperSize'){applyPaperSize(e.target.value); markDirty();}
  if(e.target.id==='plZoom') applyZoom();
  if(e.target.id==='plTemplate'){ if(!dirty || confirm('Replace the editor content with this template?')) generate(false,currentContext); else e.preventDefault(); }
  if(e.target.id==='plFontFamily') wrapSelection('font-family:'+e.target.value);
  if(e.target.id==='plFontSize') wrapSelection('font-size:'+e.target.value);
  if(e.target.id==='plTextColor') wrapSelection('color:'+e.target.value);
  if(e.target.id==='plHighlightColor') wrapSelection('background-color:'+e.target.value);
  if(e.target.id==='plHeaderMode'){
    if(e.target.value==='text'){tempHeaderImageSrc=''; applyHeaderTextFromInputs();}
    else if(tempHeaderImageSrc || q('#plExistingHeaderImagePath').value){renderImageHeader(tempHeaderImageSrc || q('#plExistingHeaderImagePath').value);}
    markDirty();
  }
  if(['plHeaderTitle','plHeaderSubtitle','plHeaderLine1','plHeaderLine2','plShowHeader'].includes(e.target.id)){
    if(q('#plHeaderMode').value==='text') applyHeaderTextFromInputs();
    else q('#plDocHeader').hidden=!q('#plShowHeader').checked;
    markDirty();
  }
  if(e.target.id==='plHeaderImage' && e.target.files[0]){
    const reader=new FileReader();
    reader.onload=ev=>{
      tempHeaderImageSrc=ev.target.result;
      q('#plHeaderMode').value='image';
      q('#plShowHeader').checked=true;
      renderImageHeader(tempHeaderImageSrc);
      markDirty();
    };
    reader.readAsDataURL(e.target.files[0]);
  }
});
document.addEventListener('input',e=>{
  if(e.target.id==='plEditor'||e.target.id==='plDocHeader'||e.target.closest('#plDocHeader')) markDirty();
  if(['plHeaderTitle','plHeaderSubtitle','plHeaderLine1','plHeaderLine2'].includes(e.target.id) && q('#plHeaderMode').value==='text'){applyHeaderTextFromInputs(); markDirty();}
});
window.addEventListener('resize',()=>requestAnimationFrame(applyZoom));
window.addEventListener('afterprint',()=>modal().classList.remove('is-printing'));
function init(){
  ensureWorkspaceButtons();
  bindWorkspaceObserver();
  if(q('#plDocHeader')&&!q('#plDocHeader').innerHTML.trim()) q('#plDocHeader').innerHTML=defaultHeaderHtml();
  applyPaperSize(q('#plPaperSize')?q('#plPaperSize').value:'A4');
  updateStatusBadge();
  syncHeaderInputs({});
  setCollapsed('.pl-collapsible',false);
  requestAnimationFrame(applyZoom);
}
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true}); else init();
})();
