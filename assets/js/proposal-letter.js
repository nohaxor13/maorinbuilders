(function(){
const api='proposals/api/proposal_letter_api.php', tpl='proposals/api/proposal_letter_template_api.php';
let proposalId=null, dirty=false, wiredObserver=false;
const cls={'A4':'paper-a4','Letter':'paper-letter','Legal':'paper-legal','Short Bond Paper':'paper-short-bond','Long Bond Paper':'paper-long-bond'};
function q(s){return document.querySelector(s)}
function post(url,data){return fetch(url,{method:'POST',body:new URLSearchParams(data)}).then(r=>r.json())}
function open(id){proposalId=id;q('#proposalLetterModal').hidden=false;generate();history()}
function generate(){fetch(tpl+'?action=generate_template&proposal_id='+proposalId+'&template_type='+encodeURIComponent(q('#plTemplate').value)).then(r=>r.json()).then(j=>{if(j.ok){q('#plEditor').innerHTML=j.html;dirty=true}})}
function paper(){let v=q('#plPaperSize').value,s=q('#plSheet');Object.values(cls).forEach(c=>s.classList.remove(c));s.classList.add(cls[v]||'paper-a4')}
function save(status){post(api+'?action=save_letter',{proposal_id:proposalId,template_type:q('#plTemplate').value,paper_size:q('#plPaperSize').value,subject:'Proposal Letter',body:q('#plEditor').innerHTML,status:status}).then(j=>{alert(j.ok?'Letter saved.':j.message);dirty=false;history()})}
function history(){fetch(api+'?action=list_letters&proposal_id='+proposalId).then(r=>r.json()).then(j=>{q('#plHistory').innerHTML='<strong>Saved Letters</strong><br>'+(j.letters||[]).map(l=>`<button class="pl-letter-action" data-letter="${l.id}">${l.letter_number||'Letter'} â€¢ ${l.status} â€¢ ${l.paper_size}</button>`).join('')})}
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
    if(editBtn){
      editBtn.insertAdjacentElement('afterend', button);
    }else{
      actions.appendChild(button);
    }
  });
}
function bindWorkspaceObserver(){
  if(wiredObserver) return;
  wiredObserver=true;
  const root=document.getElementById('workspaceContent') || document.body;
  new MutationObserver(()=>ensureWorkspaceButtons()).observe(root,{childList:true,subtree:true});
}
document.addEventListener('click',e=>{
  let b=e.target.closest('.js-proposal-letter-open,[data-proposal-letter],[data-plm-close],#plResetTemplate,#plSaveDraft,#plSaveFinal,#plPrint,.pl-letter-action,.pl-toolbar button');
  if(!b) return;
  if(b.matches('.js-proposal-letter-open,[data-proposal-letter]')) open(b.dataset.proposalId);
  if(b.matches('[data-plm-close]')){ if(!dirty||confirm('Close without saving changes?')) q('#proposalLetterModal').hidden=true }
  if(b.id==='plResetTemplate'&&confirm('Regenerate from proposal data? Manual edits will be replaced.')) generate();
  if(b.id==='plSaveDraft') save('draft');
  if(b.id==='plSaveFinal') save('final');
  if(b.id==='plPrint') window.print();
  if(b.dataset.letter) fetch(api+'?action=get_letter&letter_id='+b.dataset.letter).then(r=>r.json()).then(j=>{if(j.ok&&j.letter){q('#plEditor').innerHTML=j.letter.body;q('#plPaperSize').value=j.letter.paper_size||'A4';paper();dirty=false}});
  if(b.closest('.pl-toolbar')){let cmd=b.dataset.cmd;if(cmd)document.execCommand(cmd,false,null)}
});
document.addEventListener('change',e=>{if(e.target.id==='plPaperSize')paper();if(e.target.id==='plTemplate')generate();if(e.target.closest('.pl-toolbar select'))document.execCommand(e.target.dataset.cmd,false,e.target.value)});
document.addEventListener('input',e=>{if(e.target.id==='plEditor')dirty=true});
if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',()=>{ensureWorkspaceButtons();bindWorkspaceObserver()},{once:true});
}else{
  ensureWorkspaceButtons();
  bindWorkspaceObserver();
}
})();
