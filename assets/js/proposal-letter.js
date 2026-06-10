(function(){
const api='proposals/api/proposal_letter_api.php', tpl='proposals/api/proposal_letter_template_api.php';
let proposalId=null, dirty=false, wiredObserver=false;
function q(s){return document.querySelector(s)}
function post(url,data){return fetch(url,{method:'POST',body:data instanceof FormData?data:new URLSearchParams(data)}).then(r=>r.json())}
function open(id){proposalId=id;q('#proposalLetterModal').hidden=false;loadContext().then(generate).then(history)}
function loadContext(){
  return fetch(api+'?action=get_proposal_context&proposal_id='+proposalId).then(r=>r.json()).then(j=>{
    if(j.ok&&j.settings) applySettings(j.settings);
    return j;
  });
}
function applySettings(s){
  q('#plHeaderMode').value=s.header_mode||'text';
  q('#plHeaderTitle').value=s.header_title||'Maorin Builders';
  q('#plHeaderSubtitle').value=s.header_subtitle||'Construction • Renovation • Design & Build';
  q('#plHeaderLine1').value=s.header_line1||'Address • Contact Number • Email';
  q('#plHeaderLine2').value=s.header_line2||'';
  q('#plExistingHeaderImagePath').value=s.header_image_path||'';
  q('#plShowHeader').checked=String(s.show_header ?? 1) !== '0';
  renderHeader();
}
function generate(){
  fetch(tpl+'?action=generate_template&proposal_id='+proposalId+'&template_type='+encodeURIComponent(q('#plTemplate').value))
    .then(r=>r.json())
    .then(j=>{
      if(!j.ok) return;
      q('#plEditor').innerHTML=j.html;
      if(j.settings) applySettings(j.settings);
      dirty=false;
    });
}
function renderHeader(){
  const header=q('#plDocHeader');
  if(!header) return;
  header.hidden=!q('#plShowHeader').checked;
  const mode=q('#plHeaderMode').value;
  if(mode==='image' && q('#plExistingHeaderImagePath').value){
    header.innerHTML=`<img class="pl-header-image" src="../${q('#plExistingHeaderImagePath').value}" alt="Header">`;
    return;
  }
  header.innerHTML=`<div class="pl-brand-line"><div class="pl-brand-mark">MB</div><div><div class="pl-brand-name">${escapeHtml(q('#plHeaderTitle').value||'Maorin Builders')}</div><div class="pl-brand-sub">${escapeHtml(q('#plHeaderSubtitle').value||'Construction • Renovation • Design & Build')}</div><div class="pl-brand-meta">${escapeHtml(q('#plHeaderLine1').value||'Address • Contact Number • Email')}</div>${q('#plHeaderLine2').value?`<div class="pl-brand-meta">${escapeHtml(q('#plHeaderLine2').value)}</div>`:''}</div></div>`;
}
function escapeHtml(v){return String(v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function save(status){
  const fd=new FormData();
  fd.append('proposal_id',proposalId);
  fd.append('template_type',q('#plTemplate').value);
  fd.append('paper_size',q('#plPaperSize').value);
  fd.append('subject','Proposal Letter');
  fd.append('body',q('#plEditor').innerHTML);
  fd.append('status',status);
  fd.append('header_mode',q('#plHeaderMode').value);
  fd.append('header_title',q('#plHeaderTitle').value);
  fd.append('header_subtitle',q('#plHeaderSubtitle').value);
  fd.append('header_line1',q('#plHeaderLine1').value);
  fd.append('header_line2',q('#plHeaderLine2').value);
  fd.append('show_header',q('#plShowHeader').checked?'1':'0');
  fd.append('existing_header_image_path',q('#plExistingHeaderImagePath').value);
  if(q('#plHeaderImage').files[0]) fd.append('header_image',q('#plHeaderImage').files[0]);
  post(api+'?action=save_letter',fd).then(j=>{alert(j.ok?'Letter saved.':j.message);dirty=false;history()})
}
function history(){fetch(api+'?action=list_letters&proposal_id='+proposalId).then(r=>r.json()).then(j=>{q('#plHistory').innerHTML='<strong>Saved Letters</strong><br>'+(j.letters||[]).map(l=>`<button type="button" class="pl-letter-action" data-letter="${l.id}">${escapeHtml(l.letter_number||'Letter')} • ${escapeHtml(l.status)} • ${escapeHtml(l.paper_size)}</button>`).join('')})}
function ensureWorkspaceButtons(){
  document.querySelectorAll('[data-ws-view="proposals"]').forEach(viewBtn=>{
    const actions=viewBtn.closest('td, .workspace-actions, .mb-actions');
    const id=viewBtn.getAttribute('data-id');
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
function bindWorkspaceObserver(){ if(wiredObserver) return; wiredObserver=true; const root=document.getElementById('workspaceContent') || document.body; new MutationObserver(()=>ensureWorkspaceButtons()).observe(root,{childList:true,subtree:true}); }
document.addEventListener('click',e=>{
  const b=e.target.closest('.js-proposal-letter-open,[data-proposal-letter],[data-plm-close],#plResetTemplate,#plSaveDraft,#plSaveFinal,#plPrint,.pl-letter-action');
  if(!b) return;
  if(b.matches('.js-proposal-letter-open,[data-proposal-letter]')) open(b.dataset.proposalId);
  if(b.matches('[data-plm-close]')){ if(!dirty||confirm('Close without saving changes?')) q('#proposalLetterModal').hidden=true }
  if(b.id==='plResetTemplate'&&confirm('Regenerate from proposal data? Manual edits will be replaced.')) generate();
  if(b.id==='plSaveDraft') save('draft');
  if(b.id==='plSaveFinal') save('final');
  if(b.id==='plPrint') window.print();
  if(b.dataset.letter) fetch(api+'?action=get_letter&letter_id='+b.dataset.letter).then(r=>r.json()).then(j=>{if(j.ok&&j.letter){q('#plEditor').innerHTML=j.letter.body;q('#plPaperSize').value=j.letter.paper_size||'A4';applySettings(j.settings||{});dirty=false}}); 
});
document.addEventListener('change',e=>{
  if(e.target.id==='plPaperSize') q('#plSheet').className='proposal-letter-sheet paper-'+e.target.value.toLowerCase().replace(/\s+/g,'-');
  if(e.target.id==='plTemplate') generate();
  if(e.target.id==='plHeaderMode'||e.target.id==='plHeaderTitle'||e.target.id==='plHeaderSubtitle'||e.target.id==='plHeaderLine1'||e.target.id==='plHeaderLine2'||e.target.id==='plShowHeader') renderHeader();
  if(e.target.id==='plHeaderImage' && e.target.files[0]) {
    const reader=new FileReader();
    reader.onload=()=>{ q('#plExistingHeaderImagePath').value=''; q('#plHeaderMode').value='image'; q('#plShowHeader').checked=true; renderHeader(); };
    reader.readAsDataURL(e.target.files[0]);
  }
});
document.addEventListener('input',e=>{ if(e.target.id==='plEditor') dirty=true; if(e.target.id==='plHeaderTitle'||e.target.id==='plHeaderSubtitle'||e.target.id==='plHeaderLine1'||e.target.id==='plHeaderLine2') renderHeader(); });
if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded',()=>{ensureWorkspaceButtons();bindWorkspaceObserver();renderHeader()},{once:true}); } else { ensureWorkspaceButtons(); bindWorkspaceObserver(); renderHeader(); }
})();
