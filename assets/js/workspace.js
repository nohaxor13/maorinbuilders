
(function(){
  const shell=document.querySelector('.mb-shell');
  const key='maorin.workspace.collapsed';
  if(!shell)return;
  if(localStorage.getItem(key)==='1') shell.classList.add('is-collapsed');
  document.querySelectorAll('[data-mb-toggle-sidebar]').forEach(btn=>btn.addEventListener('click',()=>{ if(window.innerWidth<900){shell.classList.toggle('is-mobile-open');return;} shell.classList.toggle('is-collapsed'); localStorage.setItem(key,shell.classList.contains('is-collapsed')?'1':'0'); }));
  document.querySelectorAll('[data-mb-open-modal]').forEach(btn=>btn.addEventListener('click',()=>{ const id=btn.getAttribute('data-mb-open-modal'); const el=document.getElementById(id); if(el&&window.bootstrap){ new bootstrap.Modal(el).show(); }}));
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{ if(!confirm(el.getAttribute('data-confirm')||'Continue?')) e.preventDefault(); }));
})();
