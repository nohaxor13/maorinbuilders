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
function defaultHeaderHtml(){
  return '<div class="pl-brand-line"><div class="pl-brand-mark">MB</div><div><div class="pl-brand-name">Maorin Builders</div><div class="pl-brand-sub">Construction - Renovation - Design & Build</div><div class="pl-brand-meta">Address - Contact Number - Email</div></div></div>';
}
function applySettings(s){
  const header=q('#plDocHeader');
  q('#plHeaderMode').value=s.header_mode||'text';
  q('#plExistingHeaderImagePath').value=s.header_image_path||'';
  header.hidden=String(s.show_header ?? 1) === '0';
  if((s.header_mode||'text')==='image' && (s.header_image_path||'')){
    renderImageHeader(s.header_image_path);
  }else{
    header.dataset.mode='text';
    header.contentEditable='true';
    header.innerHTML='<div class="pl-brand-line"><div class="pl-brand-mark">MB</div><div><div class="pl-brand-name">'+escapeHtml(s.header_title||'Maorin Builders')+'</div><div class="pl-brand-sub">'+escapeHtml(s.header_subtitle||'Construction - Renovation - Design & Build')+'</div><div class="pl-brand-meta">'+escapeHtml(s.header_line1||'Address - Contact Number - Email')+'</div>'+((s.header_line2||'')?'<div class="pl-brand-meta">'+escapeHtml(s.header_line2)+'</div>':'')+'</div></div>';
  }
  syncToggleLabel();
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
function renderImageHeader(path){
  const header=q('#plDocHeader');
  header.dataset.mode='image';
  header.contentEditable='false';
  header.innerHTML='<img class="pl-header-image" src="../'+path+'" alt="Header">';
}
function readHeaderSettings(){
  const header=q('#plDocHeader');
  const mode=q('#plHeaderMode').value;
  const settings={
    header_mode: mode,
    header_title: 'Maorin Builders',
    header_subtitle: 'Construction - Renovation - Design & Build',
    header_line1: 'Address - Contact Number - Email',
    header_line2: '',
    show_header: header.hidden ? '0' : '1',
    existing_header_image_path: q('#plExistingHeaderImagePath').value
  };
  if(mode==='text'){
    const name=header.querySelector('.pl-brand-name');
    const sub=header.querySelector('.pl-brand-sub');
    const meta=header.querySelectorAll('.pl-brand-meta');
    settings.header_title=name ? name.textContent.trim() : settings.header_title;
    settings.header_subtitle=sub ? sub.textContent.trim() : settings.header_subtitle;
    settings.header_line1=meta[0] ? meta[0].textContent.trim() : settings.header_line1;
    settings.header_line2=meta[1] ? meta[1].textContent.trim() : '';
  }
  return settings;
}
function syncToggleLabel(){
  const btn=q('#plToggleHeader');
  if(btn) btn.textContent=q('#plDocHeader').hidden?'Show Header':'Hide Header';
}
function escapeHtml(v){return String(v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function save(status){
  const fd=new FormData();
  const settings=readHeaderSettings();
  fd.append('proposal_id',proposalId);
  fd.append('template_type',q('#plTemplate').value);
  fd.append('paper_size',q('#plPaperSize').value);
  fd.append('subject','Proposal Letter');
  fd.append('body',q('#plEditor').innerHTML);
  fd.append('status',status);
  fd.append('header_mode',settings.header_mode);
  fd.append('header_title',settings.header_title);
  fd.append('header_subtitle',settings.header_subtitle);
  fd.append('header_line1',settings.header_line1);
  fd.append('header_line2',settings.header_line2);
  fd.append('show_header',settings.show_header);
  fd.append('existing_header_image_path',settings.existing_header_image_path);
  if(q('#plHeaderImage').files[0]) fd.append('header_image',q('#plHeaderImage').files[0]);
  post(api+'?action=save_letter',fd).then(j=>{alert(j.ok?'Letter saved.':j.message);dirty=false;history()})
}
function history(){fetch(api+'?action=list_letters&proposal_id='+proposalId).then(r=>r.json()).then(j=>{q('#plHistory').innerHTML='<strong>Saved Letters</strong><br>'+(j.letters||[]).map(l=>`<button type="button" class="pl-letter-action" data-letter="${l.id}">${escapeHtml(l.letter_number||'Letter')} - ${escapeHtml(l.status)} - ${escapeHtml(l.paper_size)}</button>`).join('')})}
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
  const b=e.target.closest('.js-proposal-letter-open,[data-proposal-letter],[data-plm-close],#plResetTemplate,#plSaveDraft,#plSaveFinal,#plPrint,.pl-letter-action,#plToggleHeader');
  if(!b) return;
  if(b.matches('.js-proposal-letter-open,[data-proposal-letter]')) open(b.dataset.proposalId);
  if(b.matches('[data-plm-close]')){ if(!dirty||confirm('Close without saving changes?')) q('#proposalLetterModal').hidden=true }
  if(b.id==='plResetTemplate'&&confirm('Regenerate from proposal data? Manual edits will be replaced.')) generate();
  if(b.id==='plSaveDraft') save('draft');
  if(b.id==='plSaveFinal') save('final');
  if(b.id==='plPrint') window.print();
  if(b.id==='plToggleHeader'){ q('#plDocHeader').hidden=!q('#plDocHeader').hidden; syncToggleLabel(); dirty=true; }
  if(b.dataset.letter) fetch(api+'?action=get_letter&letter_id='+b.dataset.letter).then(r=>r.json()).then(j=>{if(j.ok&&j.letter){q('#plEditor').innerHTML=j.letter.body;q('#plPaperSize').value=j.letter.paper_size||'A4';applySettings(j.settings||{});dirty=false}});
});
document.addEventListener('change',e=>{
  if(e.target.id==='plPaperSize') q('#plSheet').className='proposal-letter-sheet paper-'+e.target.value.toLowerCase().replace(/\s+/g,'-');
  if(e.target.id==='plTemplate') generate();
  if(e.target.id==='plHeaderMode'){
    if(e.target.value==='text'){
      q('#plExistingHeaderImagePath').value='';
      q('#plDocHeader').contentEditable='true';
      if(!q('#plDocHeader').querySelector('.pl-brand-line')) q('#plDocHeader').innerHTML=defaultHeaderHtml();
      q('#plDocHeader').dataset.mode='text';
    }else if(q('#plExistingHeaderImagePath').value){
      renderImageHeader(q('#plExistingHeaderImagePath').value);
    }
    dirty=true;
  }
  if(e.target.id==='plHeaderImage' && e.target.files[0]) {
    q('#plHeaderMode').value='image';
    q('#plDocHeader').hidden=false;
    syncToggleLabel();
    dirty=true;
  }
});
document.addEventListener('input',e=>{
  if(e.target.id==='plEditor' || e.target.id==='plDocHeader' || e.target.closest('#plDocHeader')) dirty=true;
});
if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',()=>{ensureWorkspaceButtons();bindWorkspaceObserver();if(q('#plDocHeader')&&!q('#plDocHeader').innerHTML.trim()) q('#plDocHeader').innerHTML=defaultHeaderHtml();syncToggleLabel()},{once:true});
}else{
  ensureWorkspaceButtons();
  bindWorkspaceObserver();
  if(q('#plDocHeader')&&!q('#plDocHeader').innerHTML.trim()) q('#plDocHeader').innerHTML=defaultHeaderHtml();
  syncToggleLabel();
}
})();
