(function(){
  const root=document.getElementById('mbWorkspaceSpa'); if(!root||!window.MB_WORKSPACE) return;
  const content=document.getElementById('workspaceContent');
  const notice=document.getElementById('workspaceNotice');
  const search=document.getElementById('workspaceSearch');
  let active=location.hash.replace('#','')||root.dataset.defaultModule||'overview';
  let query='';
  function showNotice(type,msg){ if(!notice) return; notice.className='alert alert-'+type; notice.textContent=msg; notice.classList.remove('d-none'); setTimeout(()=>notice.classList.add('d-none'),5200); }
  function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  async function load(module){
    active=module||'overview'; location.hash=active;
    document.querySelectorAll('[data-module]').forEach(el=>el.classList.toggle('active',el.dataset.module===active));
    content.innerHTML='<div class="text-center text-muted py-5">Loading...</div>';
    try{
      const url=window.MB_WORKSPACE.api+'?module='+encodeURIComponent(active)+'&q='+encodeURIComponent(query);
      const res=await fetch(url,{headers:{'X-Requested-With':'fetch'}}); const html=await res.text();
      if(!res.ok) throw new Error(html||'Request failed'); content.innerHTML=html; bindContent();
    }catch(err){content.innerHTML='<div class="alert alert-danger">'+escapeHtml(err.message||String(err))+'</div>';}
  }
  function bindContent(){
    content.querySelectorAll('[data-workspace-open]').forEach(btn=>btn.addEventListener('click',()=>{const id=btn.getAttribute('data-workspace-open'); const el=document.getElementById(id); if(el&&window.bootstrap) new bootstrap.Modal(el).show();}));
    content.querySelectorAll('form[data-spa-form]').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault(); const fd=new FormData(form); fd.append('csrf_token',window.MB_WORKSPACE.csrf||''); try{const res=await fetch(window.MB_WORKSPACE.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}); const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.message||'Save failed'); document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide()); showNotice('success',data.message||'Saved.'); await load(active);}catch(err){showNotice('danger',err.message||String(err));}}));
    content.querySelectorAll('[data-confirm-action]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm(btn.dataset.confirmAction||'Continue?')) return; const fd=new FormData(); fd.append('csrf_token',window.MB_WORKSPACE.csrf||''); fd.append('module',active); fd.append('action','delete'); fd.append('id',btn.dataset.id||''); try{const res=await fetch(window.MB_WORKSPACE.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}); const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.message||'Delete failed'); showNotice('success',data.message||'Deleted.'); await load(active);}catch(err){showNotice('danger',err.message||String(err));}}));
    content.querySelectorAll('[data-estimate-builder]').forEach(initEstimateBuilder);
  }
  function num(v){ const n=parseFloat(String(v||'').replace(/,/g,'')); return isFinite(n)?n:0; }
  function peso(v){ return '₱'+num(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function pct(v){ return num(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+'%'; }
  function lineTemplate(type, idx){
    if(type==='materials') return `<div class="estimate-line" data-line="materials"><div><label>Material</label><input class="form-control form-control-sm" name="materials[${idx}][material_name]" placeholder="Cement, steel bar, tiles"></div><div><label>Unit</label><input class="form-control form-control-sm" name="materials[${idx}][unit]" placeholder="bag, pc, sqm"></div><div><label>Qty</label><input class="form-control form-control-sm" name="materials[${idx}][quantity]" type="number" step="0.001" value="0" data-est-number></div><div><label>Unit Cost</label><input class="form-control form-control-sm" name="materials[${idx}][unit_cost]" type="number" step="0.01" value="0" data-est-number></div><div><label>Waste %</label><input class="form-control form-control-sm" name="materials[${idx}][waste_percent]" type="number" step="0.01" value="5" data-est-number></div><div><label>Supplier</label><input class="form-control form-control-sm" name="materials[${idx}][supplier]"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div>`;
    if(type==='labor') return `<div class="estimate-line labor-line" data-line="labor"><div><label>Worker Type</label><input class="form-control form-control-sm" name="labor[${idx}][role_name]" placeholder="Mason, carpenter, helper"></div><div><label>Workers</label><input class="form-control form-control-sm" name="labor[${idx}][worker_count]" type="number" step="0.01" value="1" data-est-number></div><div><label>Daily Rate</label><input class="form-control form-control-sm" name="labor[${idx}][daily_rate]" type="number" step="0.01" value="0" data-est-number></div><div><label>Days</label><input class="form-control form-control-sm" name="labor[${idx}][days_count]" type="number" step="0.01" value="1" data-est-number></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div>`;
    return `<div class="estimate-line equipment-line" data-line="equipment"><div><label>Equipment</label><input class="form-control form-control-sm" name="equipment[${idx}][equipment_name]" placeholder="Mixer, scaffolding, truck"></div><div><label>Rate Type</label><select class="form-select form-select-sm" name="equipment[${idx}][rate_type]"><option value="daily">Daily</option><option value="hourly">Hourly</option><option value="fixed">Fixed</option></select></div><div><label>Rate</label><input class="form-control form-control-sm" name="equipment[${idx}][rate]" type="number" step="0.01" value="0" data-est-number></div><div><label>Duration</label><input class="form-control form-control-sm" name="equipment[${idx}][duration]" type="number" step="0.01" value="1" data-est-number></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div>`;
  }
  function initEstimateBuilder(form){
    const counters={materials:0,labor:0,equipment:0};
    const add=(type)=>{ const wrap=form.querySelector(`[data-lines="${type}"]`); if(!wrap) return; wrap.insertAdjacentHTML('beforeend',lineTemplate(type,counters[type]++)); recalc(); };
    form.querySelectorAll('[data-add-row]').forEach(btn=>btn.addEventListener('click',()=>add(btn.dataset.addRow)));
    form.addEventListener('input',recalc); form.addEventListener('change',recalc);
    form.addEventListener('click',e=>{ const rm=e.target.closest('[data-remove-line]'); if(rm){rm.closest('[data-line]')?.remove(); recalc();} });
    const projectPicker=form.querySelector('[data-project-picker]');
    projectPicker?.addEventListener('change',()=>{ const opt=projectPicker.selectedOptions[0]; if(!opt) return; const client=form.querySelector('[data-project-client]'); const loc=form.querySelector('[data-project-location]'); const type=form.querySelector('[data-project-type]'); if(client&&!client.value) client.value=opt.dataset.client||''; if(loc&&!loc.value) loc.value=opt.dataset.location||''; if(type&&opt.dataset.type) type.value=opt.dataset.type; recalc(); });
    add('materials'); add('labor'); add('equipment');
    function recalc(){
      let materials=0,labor=0,equipment=0;
      form.querySelectorAll('[data-line="materials"]').forEach(line=>{ const qty=num(line.querySelector('[name*="[quantity]"]')?.value); const cost=num(line.querySelector('[name*="[unit_cost]"]')?.value); const waste=num(line.querySelector('[name*="[waste_percent]"]')?.value); const total=qty*cost*(1+waste/100); line.querySelector('[data-line-total]').textContent=peso(total); materials+=total; });
      form.querySelectorAll('[data-line="labor"]').forEach(line=>{ const w=num(line.querySelector('[name*="[worker_count]"]')?.value); const r=num(line.querySelector('[name*="[daily_rate]"]')?.value); const d=num(line.querySelector('[name*="[days_count]"]')?.value); const total=w*r*d; line.querySelector('[data-line-total]').textContent=peso(total); labor+=total; });
      form.querySelectorAll('[data-line="equipment"]').forEach(line=>{ const r=num(line.querySelector('[name*="[rate]"]')?.value); const d=num(line.querySelector('[name*="[duration]"]')?.value); const total=r*d; line.querySelector('[data-line-total]').textContent=peso(total); equipment+=total; });
      const val=(name)=>num(form.elements[name]?.value);
      const fees=val('professional_fee')+val('permit_fee')+val('mobilization_fee')+val('supervision_fee');
      const overhead=val('overhead_cost');
      const base=materials+labor+equipment+fees+overhead;
      const contingency=base*(val('contingency_percent')/100);
      const subtotal=base+contingency;
      const markup=subtotal*(val('markup_percent')/100);
      const tax=(subtotal+markup)*(val('tax_percent')/100);
      const discount=val('discount_amount');
      const grand=Math.max(0, subtotal+markup+tax-discount);
      const profit=grand-subtotal-tax;
      const margin=grand>0 ? profit/grand*100 : 0;
      const target=val('target_margin_percent')||15;
      const set=(k,v,isPct=false)=>{ const el=form.querySelector(`[data-sum="${k}"]`); if(el) el.textContent=isPct?pct(v):peso(v); };
      set('materials',materials); set('labor',labor); set('equipment',equipment); set('fees',fees); set('overhead',overhead); set('contingency',contingency); set('subtotal',subtotal); set('markup',markup); set('tax',tax); set('discount',discount); set('grand',grand); set('profit',profit); set('margin',margin,true);
      const warnings=[];
      let risk='safe';
      if(profit<0 || grand<subtotal){ risk='danger'; warnings.push('Estimated result is a loss. Increase markup, reduce cost, or review scope.'); }
      if(margin<target){ risk=risk==='danger'?'danger':'review'; warnings.push(`Profit margin is below target ${target.toFixed(2)}%.`); }
      if(val('contingency_percent')<5){ risk=risk==='danger'?'danger':'review'; warnings.push('Contingency is below 5%; project variations may erase profit.'); }
      if(materials>0 && !form.querySelector('[name*="[waste_percent]"]')) warnings.push('Materials have no waste allowance.');
      if(labor>grand*0.45 && grand>0) warnings.push('Labor is above 45% of client price; check manpower and duration.');
      if(!warnings.length) warnings.push('Estimate looks acceptable, but verify actual supplier quotes and site conditions.');
      const riskEl=form.querySelector('[data-risk-label]'); if(riskEl){ riskEl.className='mb-risk '+(risk==='safe'?'success':risk==='danger'?'danger':'warn'); riskEl.textContent=risk.charAt(0).toUpperCase()+risk.slice(1); }
      const warn=form.querySelector('[data-warnings]'); if(warn) warn.innerHTML=warnings.map(w=>`<div class="estimate-warning ${risk==='danger'?'danger':''}">${escapeHtml(w)}</div>`).join('');
    }
  }
  document.querySelectorAll('[data-module]').forEach(el=>el.addEventListener('click',()=>load(el.dataset.module)));
  document.getElementById('workspaceRefresh')?.addEventListener('click',()=>load(active));
  let t=null; search?.addEventListener('input',()=>{clearTimeout(t); t=setTimeout(()=>{query=search.value.trim(); load(active);},300);});
  window.addEventListener('hashchange',()=>{const m=location.hash.replace('#','')||'overview'; if(m!==active) load(m);});
  load(active);
})();
