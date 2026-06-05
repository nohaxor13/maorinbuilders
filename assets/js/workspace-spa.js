(function(){
  const app=document.getElementById('mbWorkspaceSpa'); if(!app) return;
  const content=document.getElementById('workspaceContent');
  const search=document.getElementById('workspaceSearch');
  const notice=document.getElementById('workspaceNotice');
  let active=location.hash ? location.hash.replace('#','') : (app.dataset.defaultModule||'overview');
  let query='';
  function showNotice(type,msg){notice.className='alert alert-'+type; notice.textContent=msg; notice.classList.toggle('d-none',!msg); if(msg) setTimeout(()=>notice.classList.add('d-none'),3500);}
  function markActive(module){document.querySelectorAll('[data-module]').forEach(el=>{el.classList.toggle('active',el.dataset.module===module)});}
  async function load(module){active=module; location.hash=module; markActive(module); content.innerHTML='<div class="text-center text-muted py-5">Loading...</div>'; try{const url=window.MB_WORKSPACE.api+'?module='+encodeURIComponent(module)+'&q='+encodeURIComponent(query); const res=await fetch(url,{headers:{'X-Requested-With':'fetch'}}); const html=await res.text(); if(!res.ok) throw new Error(html||'Request failed'); content.innerHTML=html; bindContent();}catch(err){content.innerHTML='<div class="alert alert-danger">'+escapeHtml(err.message||String(err))+'</div>';}}
  function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  function bindContent(){
    content.querySelectorAll('[data-workspace-open]').forEach(btn=>btn.addEventListener('click',()=>{const id=btn.getAttribute('data-workspace-open'); const el=document.getElementById(id); if(el&&window.bootstrap) new bootstrap.Modal(el).show();}));
    content.querySelectorAll('form[data-spa-form]').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault(); const fd=new FormData(form); fd.append('csrf_token',window.MB_WORKSPACE.csrf||''); try{const res=await fetch(window.MB_WORKSPACE.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}); const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.message||'Save failed'); document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide()); showNotice('success',data.message||'Saved.'); await load(active);}catch(err){showNotice('danger',err.message||String(err));}}));
    content.querySelectorAll('[data-confirm-action]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm(btn.dataset.confirmAction||'Continue?')) return; const fd=new FormData(); fd.append('csrf_token',window.MB_WORKSPACE.csrf||''); fd.append('module',active); fd.append('action','delete'); fd.append('id',btn.dataset.id||''); try{const res=await fetch(window.MB_WORKSPACE.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}); const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.message||'Delete failed'); showNotice('success',data.message||'Deleted.'); await load(active);}catch(err){showNotice('danger',err.message||String(err));}}));
  }
  document.querySelectorAll('[data-module]').forEach(el=>el.addEventListener('click',()=>load(el.dataset.module)));
  document.getElementById('workspaceRefresh')?.addEventListener('click',()=>load(active));
  let t=null; search?.addEventListener('input',()=>{clearTimeout(t); t=setTimeout(()=>{query=search.value.trim(); load(active);},300);});
  window.addEventListener('hashchange',()=>{const m=location.hash.replace('#','')||'overview'; if(m!==active) load(m);});
  load(active);
})();
