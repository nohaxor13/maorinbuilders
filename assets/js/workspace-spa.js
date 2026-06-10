(function(){
  const root=document.getElementById('mbWorkspaceSpa'); if(!root||!window.MB_WORKSPACE) return;
  const content=document.getElementById('workspaceContent');
  const notice=document.getElementById('workspaceNotice');
  const search=document.getElementById('workspaceSearch');
  let active=location.hash.replace('#','')||root.dataset.defaultModule||'overview';
  let query='';
  function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  function showNotice(type,msg){ if(!notice) return; notice.className='alert alert-'+type; notice.textContent=msg; notice.classList.remove('d-none'); setTimeout(()=>notice.classList.add('d-none'),5200); }
  async function fetchHtml(module, mode='list', id=''){
    const url=new URL(window.MB_WORKSPACE.api, window.location.href);
    url.searchParams.set('module',module); url.searchParams.set('q',query);
    if(mode!=='list') url.searchParams.set('mode',mode);
    if(id) url.searchParams.set('id',id);
    const res=await fetch(url,{headers:{'X-Requested-With':'fetch'}});
    const html=await res.text();
    if(!res.ok) throw new Error(html.replace(/<[^>]*>/g,'').trim()||'Request failed');
    return html;
  }
  async function load(module){
    active=module||'overview'; location.hash=active;
    document.querySelectorAll('[data-module]').forEach(el=>el.classList.toggle('active',el.dataset.module===active));
    content.innerHTML='<div class="text-center text-muted py-5">Loading...</div>';
    try{ content.innerHTML=await fetchHtml(active); bindContent(); }
    catch(err){ content.innerHTML='<div class="alert alert-danger">'+escapeHtml(err.message||String(err))+'</div>'; }
  }
  function modalShell(title, html){
    const old=document.getElementById('workspaceDynamicModal'); old?.remove();
    document.querySelectorAll('.modal-backdrop').forEach(el=>el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    const wrap=document.createElement('div');
    wrap.innerHTML=`<div class="modal fade" id="workspaceDynamicModal"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">${escapeHtml(title)}</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body">${html}</div></div></div></div>`;
    document.body.appendChild(wrap.firstElementChild);
    const el=document.getElementById('workspaceDynamicModal');
    const modal=new bootstrap.Modal(el); modal.show();
    el.addEventListener('hidden.bs.modal',()=>{
      el.remove();
      document.querySelectorAll('.modal-backdrop').forEach(node=>node.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.body.style.removeProperty('overflow');
    },{once:true});
    bindInside(el);
  }
  function openExistingModal(id){ const el=document.getElementById(id); if(el&&window.bootstrap){ new bootstrap.Modal(el).show(); bindInside(el); } }
  function bindContent(){ bindInside(content); }
  function to24HourValue(time){
    const value=String(time||'').trim();
    if(!value) return '';
    if(/^\d{2}:\d{2}$/.test(value)) return value;
    if(/^\d{2}:\d{2}:\d{2}$/.test(value)) return value.slice(0,5);
    const match=value.match(/^(\d{1,2}):(\d{2})\s*([AaPp][Mm])$/);
    if(!match) return '';
    let hour=Number(match[1]);
    const minute=Number(match[2]);
    const suffix=match[3].toUpperCase();
    if(hour < 1 || hour > 12 || minute < 0 || minute > 59) return '';
    if(suffix==='AM' && hour===12) hour=0;
    if(suffix==='PM' && hour!==12) hour+=12;
    return `${String(hour).padStart(2,'0')}:${String(minute).padStart(2,'0')}`;
  }
  function to12HourDisplay(time){
    const normalized=to24HourValue(time);
    if(!normalized) return String(time||'').trim();
    const [hour24, minute] = normalized.split(':').map(Number);
    const suffix=hour24>=12?'PM':'AM';
    let hour12=hour24%12;
    if(hour12===0) hour12=12;
    return `${hour12}:${String(minute).padStart(2,'0')} ${suffix}`;
  }
  function setEmployeeDrawerState(shell, open){
    if(!shell) return;
    shell.classList.toggle('employee-drawer-open', !!open);
    shell.querySelectorAll('[data-employee-edit-toggle]').forEach(btn=>{
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    if(open){
      const target=shell.querySelector('.employee-edit-panel input, .employee-edit-panel select, .employee-edit-panel textarea, .employee-edit-panel button:not([data-employee-edit-close])');
      target?.focus();
    }
  }
  function openEmployeeDrawer(trigger){
    const shell=trigger?.closest('.employee-profile-shell');
    setEmployeeDrawerState(shell, true);
  }
  function closeEmployeeDrawer(trigger){
    const shell=trigger?.closest('.employee-profile-shell');
    setEmployeeDrawerState(shell, false);
  }
  function parseHtmlDocument(html){
    return new DOMParser().parseFromString(html,'text/html');
  }
  async function openPayrollPreviewModal(){
    const html=await fetchHtml('payroll');
    modalShell('Payroll Preview', html);
  }
  async function refreshAttendanceDate(dateValue, shell){
    const url=new URL(location.href);
    url.hash='attendance';
    history.replaceState(null,'',url.pathname+url.search+'#attendance');
    const api=new URL(window.MB_WORKSPACE.api, window.location.href);
    api.searchParams.set('module','attendance');
    api.searchParams.set('date',dateValue);
    const host=content.querySelector('.attendance-shell')||shell;
    if(host) host.classList.add('is-loading');
    try{
      const res=await fetch(api,{headers:{'X-Requested-With':'fetch'}});
      const html=await res.text();
      if(!res.ok) throw new Error(html.replace(/<[^>]*>/g,'').trim()||'Request failed');
      const nextDoc=parseHtmlDocument(html);
      const nextShell=nextDoc.querySelector('.attendance-shell');
      if(!nextShell) throw new Error('Attendance board failed to load.');
      if(host){
        host.replaceWith(nextShell);
      }else{
        content.innerHTML=html;
      }
      bindContent();
    }catch(err){
      if(host) host.classList.remove('is-loading');
      showNotice('danger',err.message||String(err));
    }
  }

  function bindEstimateTabs(scope){
    scope.querySelectorAll('.professional-estimate-modal .estimate-subtabs [data-bs-toggle="pill"]').forEach(btn=>{
      if(btn.dataset.tabFallbackBound) return;
      btn.dataset.tabFallbackBound='1';
      btn.addEventListener('click',()=>{
        const modal=btn.closest('.professional-estimate-modal');
        if(!modal) return;
        modal.querySelectorAll('.estimate-subtabs .nav-link').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const target=btn.getAttribute('data-bs-target');
        modal.querySelectorAll('.tab-content > .tab-pane').forEach(p=>p.classList.remove('show','active'));
        const pane=target ? modal.querySelector(target) : null;
        if(pane) pane.classList.add('show','active');
      });
    });
  }

  function bindInside(scope){
    bindEstimateTabs(scope);
    scope.querySelectorAll('[data-workspace-open]').forEach(btn=>{ if(btn.dataset.bound) return; btn.dataset.bound='1'; btn.addEventListener('click',()=>openExistingModal(btn.getAttribute('data-workspace-open'))); });
    scope.querySelectorAll('form[data-spa-form]').forEach(form=>{ if(form.dataset.bound) return; form.dataset.bound='1'; form.addEventListener('submit',submitSpaForm); });
    scope.querySelectorAll('[data-confirm-action]').forEach(btn=>{ if(btn.dataset.bound) return; btn.dataset.bound='1'; btn.addEventListener('click',()=>deleteRecord(btn)); });
    scope.querySelectorAll('[data-ws-view]').forEach(btn=>{ if(btn.dataset.bound) return; btn.dataset.bound='1'; btn.addEventListener('click',async()=>{ try{ const html=await fetchHtml(btn.dataset.wsView,'view',btn.dataset.id); modalShell('Record Details',html); }catch(e){showNotice('danger',e.message);} }); });
    scope.querySelectorAll('.employee-col[data-ws-view="employees"]').forEach(el=>{
      if(el.dataset.boundEmployeeView) return;
      el.dataset.boundEmployeeView='1';
      const open=async()=>{ try{ const html=await fetchHtml('employees','view',el.dataset.id); modalShell('Employee Profile',html); }catch(e){ showNotice('danger',e.message); } };
      el.addEventListener('click',open);
      el.addEventListener('keydown',e=>{ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); open(); } });
    });
    scope.querySelectorAll('[data-ws-edit]').forEach(btn=>{ if(btn.dataset.bound) return; btn.dataset.bound='1'; btn.addEventListener('click',async()=>{ try{ const html=await fetchHtml(btn.dataset.wsEdit,'edit',btn.dataset.id); const old=document.getElementById('workspaceEditMount'); old?.remove(); const mount=document.createElement('div'); mount.id='workspaceEditMount'; mount.innerHTML=html; document.body.appendChild(mount); const modalEl=mount.querySelector('.modal'); new bootstrap.Modal(modalEl).show(); modalEl.addEventListener('hidden.bs.modal',()=>mount.remove(),{once:true}); bindInside(mount); }catch(e){showNotice('danger',e.message);} }); });
    scope.querySelectorAll('[data-ws-approve-proposal]').forEach(btn=>{ if(btn.dataset.bound) return; btn.dataset.bound='1'; btn.addEventListener('click',async()=>{ if(!confirm('Approve this proposal and create/link a project file?')) return; await postAction({module:'proposals',action:'approve',id:btn.dataset.wsApproveProposal}); }); });
    scope.querySelectorAll('[data-module]').forEach(el=>{ if(el.dataset.boundModule) return; el.dataset.boundModule='1'; el.addEventListener('click',()=>load(el.dataset.module)); });
    scope.querySelectorAll('[data-emp-cat]').forEach(btn=>{ if(btn.dataset.boundCat) return; btn.dataset.boundCat='1'; btn.addEventListener('click',()=>{ const host=btn.closest('#workspaceContent')||scope; host.querySelectorAll('[data-emp-cat]').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); host.querySelectorAll('[data-emp-pane]').forEach(p=>p.classList.toggle('active',p.dataset.empPane===btn.dataset.empCat)); }); });
    scope.querySelectorAll('[data-attendance-date]').forEach(inp=>{ if(inp.dataset.boundDate) return; inp.dataset.boundDate='1'; inp.addEventListener('change',()=>refreshAttendanceDate(inp.value, inp.closest('.attendance-shell'))); });
    scope.querySelectorAll('[data-att-date]').forEach(btn=>{ if(btn.dataset.boundCal) return; btn.dataset.boundCal='1'; btn.addEventListener('click',()=>{ const inp=scope.querySelector('[data-attendance-date]'); if(inp){ inp.value=btn.dataset.attDate; inp.dispatchEvent(new Event('change')); } }); });
    scope.querySelectorAll('[data-payroll-preview]').forEach(btn=>{ if(btn.dataset.boundPayrollPreview) return; btn.dataset.boundPayrollPreview='1'; btn.addEventListener('click',async()=>{ try{ await openPayrollPreviewModal(); }catch(err){ showNotice('danger',err.message||String(err)); } }); });
    scope.querySelectorAll('[data-payroll-filter]').forEach(form=>{ if(form.dataset.boundPayroll) return; form.dataset.boundPayroll='1'; form.addEventListener('submit',e=>{ e.preventDefault(); const fd=new FormData(form); const api=new URL(window.MB_WORKSPACE.api, window.location.href); api.searchParams.set('module','payroll'); ['period_type','period_anchor','start','end'].forEach(key=>{ const value=fd.get(key); if(value) api.searchParams.set(key, value); }); content.innerHTML='<div class="text-center text-muted py-5">Loading...</div>'; fetch(api,{headers:{'X-Requested-With':'fetch'}}).then(r=>r.text()).then(html=>{content.innerHTML=html; bindContent();}); }); });
    scope.querySelectorAll('[data-job-title-select]').forEach(sel=>{ if(sel.dataset.boundJob) return; sel.dataset.boundJob='1'; sel.addEventListener('change',()=>{ const opt=sel.selectedOptions[0]; const form=sel.closest('form'); if(!opt||!form) return; if(opt.dataset.rate) form.elements['salary_rate'].value=opt.dataset.rate; if(opt.dataset.rateType) form.elements['rate_type'].value=opt.dataset.rateType; if(opt.dataset.category) form.elements['category'].value=opt.dataset.category; if(opt.dataset.department) form.elements['department_id'].value=opt.dataset.department; if(form.elements['job_title']) form.elements['job_title'].value=opt.textContent.trim(); }); });
    scope.querySelectorAll('[data-photo-input]').forEach(inp=>{
      if(inp.dataset.boundPhoto) return;
      inp.dataset.boundPhoto='1';
      inp.addEventListener('change',()=>{
        const file=inp.files&&inp.files[0];
        const panel=inp.closest('.employee-photo-panel');
        let preview=panel?.querySelector('.employee-photo-preview');
        if(!preview || !panel) return;
        if(!file){
          preview.innerHTML = '<span>Photo</span>';
          return;
        }
        const reader=new FileReader();
        reader.onload=()=>{ preview.innerHTML=`<img src="${escapeHtml(reader.result)}" alt="Preview">`; };
        reader.readAsDataURL(file);
      });
    });
    scope.querySelectorAll('[data-employee-edit-toggle]').forEach(btn=>{
      if(btn.dataset.boundEditToggle) return;
      btn.dataset.boundEditToggle='1';
      btn.setAttribute('aria-expanded', 'false');
      btn.addEventListener('click',()=>openEmployeeDrawer(btn));
    });
    scope.querySelectorAll('[data-employee-edit-close]').forEach(btn=>{
      if(btn.dataset.boundEditClose) return;
      btn.dataset.boundEditClose='1';
      btn.addEventListener('click',()=>closeEmployeeDrawer(btn));
    });
    scope.querySelectorAll('.employee-edit-overlay').forEach(overlay=>{
      if(overlay.dataset.boundEmployeeOverlay) return;
      overlay.dataset.boundEmployeeOverlay='1';
      overlay.addEventListener('click',()=>setEmployeeDrawerState(overlay.closest('.employee-profile-shell'), false));
    });
    scope.querySelectorAll('[data-employee-tab]').forEach(btn=>{
      if(btn.dataset.boundEmployeeTab) return;
      btn.dataset.boundEmployeeTab='1';
      btn.addEventListener('click',async()=>{
        const shell=btn.closest('.employee-profile-shell');
        const tab=btn.dataset.employeeTab;
        shell?.querySelectorAll('.employee-profile-tabs button').forEach(b=>b.classList.toggle('active', b===btn));
        if(tab==='overview') return shell?.querySelector('[data-employee-section="overview"]')?.scrollIntoView({behavior:'smooth', block:'start'});
        if(tab==='documents') return shell?.querySelector('[data-employee-section="documents"]')?.scrollIntoView({behavior:'smooth', block:'start'});
        if(tab==='attendance' || tab==='payroll' || tab==='performance' || tab==='history'){
          return shell?.querySelector(`[data-employee-section="${tab}"]`)?.scrollIntoView({behavior:'smooth', block:'start'});
        }
        const target=shell?.querySelector(`[data-employee-section="${tab}"]`);
        if(target) target.scrollIntoView({behavior:'smooth', block:'start'});
      });
    });
    scope.querySelectorAll('[data-attendance-board]').forEach(initAttendanceBoard);
    scope.querySelectorAll('[data-estimate-builder]').forEach(initEstimateBuilder);
    scope.querySelectorAll('[data-proposal-builder]').forEach(initProposalBuilder);
  }
  async function submitSpaForm(e){
    e.preventDefault(); const form=e.currentTarget; const fd=new FormData(form); fd.append('csrf_token',window.MB_WORKSPACE.csrf||'');
    try{ const res=await fetch(window.MB_WORKSPACE.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}); const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.message||'Save failed'); document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide()); showNotice('success',data.message||'Saved.'); await load(active); }
    catch(err){ showNotice('danger',err.message||String(err)); }
  }
  async function postAction(obj){
    const fd=new FormData(); Object.entries(obj).forEach(([k,v])=>fd.append(k,v)); fd.append('csrf_token',window.MB_WORKSPACE.csrf||'');
    try{ const res=await fetch(window.MB_WORKSPACE.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}); const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.message||'Action failed'); showNotice('success',data.message||'Done.'); await load(active); }
    catch(err){ showNotice('danger',err.message||String(err)); }
  }
  async function deleteRecord(btn){ if(!confirm(btn.dataset.confirmAction||'Delete record?')) return; await postAction({module:active,action:'delete',id:btn.dataset.id||''}); }
  function num(v){ const n=parseFloat(String(v||'').replace(/,/g,'')); return isFinite(n)?n:0; }
  function peso(v){ return '₱'+num(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function pct(v){ return num(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+'%'; }
  function lineTemplate(type, idx){
    if(type==='materials') return `<div class="estimate-line" data-line="materials"><div><label>Material</label><input class="form-control form-control-sm" name="materials[${idx}][material_name]" placeholder="Cement, steel bar, tiles"></div><div><label>Unit</label><input class="form-control form-control-sm" name="materials[${idx}][unit]" placeholder="bag, pc, sqm"></div><div><label>Qty</label><input class="form-control form-control-sm" name="materials[${idx}][quantity]" type="number" step="0.001" value="0"></div><div><label>Unit Cost</label><input class="form-control form-control-sm" name="materials[${idx}][unit_cost]" type="number" step="0.01" value="0"></div><div><label>Waste %</label><input class="form-control form-control-sm" name="materials[${idx}][waste_percent]" type="number" step="0.01" value="5"></div><div><label>Supplier</label><input class="form-control form-control-sm" name="materials[${idx}][supplier]"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div>`;
    if(type==='labor') return `<div class="estimate-line labor-line" data-line="labor"><div><label>Worker Type</label><input class="form-control form-control-sm" name="labor[${idx}][role_name]" placeholder="Mason, carpenter, helper"></div><div><label>Workers</label><input class="form-control form-control-sm" name="labor[${idx}][worker_count]" type="number" step="0.01" value="1"></div><div><label>Daily Rate</label><input class="form-control form-control-sm" name="labor[${idx}][daily_rate]" type="number" step="0.01" value="0"></div><div><label>Days</label><input class="form-control form-control-sm" name="labor[${idx}][days_count]" type="number" step="0.01" value="1"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div>`;
    return `<div class="estimate-line equipment-line" data-line="equipment"><div><label>Equipment</label><input class="form-control form-control-sm" name="equipment[${idx}][equipment_name]" placeholder="Mixer, scaffolding, truck"></div><div><label>Rate Type</label><select class="form-select form-select-sm" name="equipment[${idx}][rate_type]"><option value="daily">Daily</option><option value="hourly">Hourly</option><option value="fixed">Fixed</option></select></div><div><label>Rate</label><input class="form-control form-control-sm" name="equipment[${idx}][rate]" type="number" step="0.01" value="0"></div><div><label>Duration</label><input class="form-control form-control-sm" name="equipment[${idx}][duration]" type="number" step="0.01" value="1"></div><div class="line-total" data-line-total>₱0.00</div><button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>×</button></div>`;
  }
  function initEstimateBuilder(form){
    if(form.dataset.estimateBound) return; form.dataset.estimateBound='1';
    const counters={materials:form.querySelectorAll('[data-line="materials"]').length,labor:form.querySelectorAll('[data-line="labor"]').length,equipment:form.querySelectorAll('[data-line="equipment"]').length};
    const add=(type)=>{ const wrap=form.querySelector(`[data-lines="${type}"]`); if(!wrap) return; wrap.insertAdjacentHTML('beforeend',lineTemplate(type,counters[type]++)); recalc(); };
    form.querySelectorAll('[data-add-row]').forEach(btn=>btn.addEventListener('click',()=>add(btn.dataset.addRow)));
    form.addEventListener('input',recalc); form.addEventListener('change',recalc);
    form.addEventListener('click',e=>{ const rm=e.target.closest('[data-remove-line]'); if(rm){rm.closest('[data-line]')?.remove(); recalc();} });
    const projectPicker=form.querySelector('[data-project-picker]');
    projectPicker?.addEventListener('change',()=>{ const opt=projectPicker.selectedOptions[0]; if(!opt) return; const client=form.querySelector('[data-project-client]'); const loc=form.querySelector('[data-project-location]'); const type=form.querySelector('[data-project-type]'); if(client&&!client.value) client.value=opt.dataset.client||''; if(loc&&!loc.value) loc.value=opt.dataset.location||''; if(type&&opt.dataset.type) type.value=opt.dataset.type; recalc(); });
    if(!counters.materials) add('materials'); if(!counters.labor) add('labor'); if(!counters.equipment) add('equipment'); recalc();
    function recalc(){
      let materials=0,labor=0,equipment=0;
      form.querySelectorAll('[data-line="materials"]').forEach(line=>{ const qty=num(line.querySelector('[name*="[quantity]"]')?.value); const cost=num(line.querySelector('[name*="[unit_cost]"]')?.value); const waste=num(line.querySelector('[name*="[waste_percent]"]')?.value); const total=qty*cost*(1+waste/100); const t=line.querySelector('[data-line-total]'); if(t) t.textContent=peso(total); materials+=total; });
      form.querySelectorAll('[data-line="labor"]').forEach(line=>{ const w=num(line.querySelector('[name*="[worker_count]"]')?.value); const r=num(line.querySelector('[name*="[daily_rate]"]')?.value); const d=num(line.querySelector('[name*="[days_count]"]')?.value); const total=w*r*d; const t=line.querySelector('[data-line-total]'); if(t) t.textContent=peso(total); labor+=total; });
      form.querySelectorAll('[data-line="equipment"]').forEach(line=>{ const r=num(line.querySelector('[name*="[rate]"]')?.value); const d=num(line.querySelector('[name*="[duration]"]')?.value); const total=r*d; const t=line.querySelector('[data-line-total]'); if(t) t.textContent=peso(total); equipment+=total; });
      const val=(name)=>num(form.elements[name]?.value);
      const fees=val('professional_fee')+val('permit_fee')+val('mobilization_fee')+val('supervision_fee'); const overhead=val('overhead_cost'); const base=materials+labor+equipment+fees+overhead;
      const contingency=base*(val('contingency_percent')/100); const subtotal=base+contingency; const markup=subtotal*(val('markup_percent')/100); const tax=(subtotal+markup)*(val('tax_percent')/100); const discount=val('discount_amount'); const grand=Math.max(0,subtotal+markup+tax-discount); const profit=grand-subtotal-tax; const margin=grand>0?profit/grand*100:0; const target=val('target_margin_percent')||15;
      const set=(k,v,isPct=false)=>{ const el=form.querySelector(`[data-sum="${k}"]`); if(el) el.textContent=isPct?pct(v):peso(v); };
      [['materials',materials],['labor',labor],['equipment',equipment],['fees',fees],['overhead',overhead],['contingency',contingency],['subtotal',subtotal],['markup',markup],['tax',tax],['discount',discount],['grand',grand],['profit',profit]].forEach(x=>set(x[0],x[1])); set('margin',margin,true);
      const warnings=[]; let risk='safe';
      if(profit<0 || grand<subtotal){ risk='danger'; warnings.push('Estimated result is a loss. Increase markup, reduce cost, or review scope.'); }
      if(margin<target){ risk=risk==='danger'?'danger':'review'; warnings.push(`Profit margin is below target ${target.toFixed(2)}%.`); }
      if(val('contingency_percent')<5){ risk=risk==='danger'?'danger':'review'; warnings.push('Contingency is below 5%; project variations may erase profit.'); }
      if(labor>grand*0.45 && grand>0) warnings.push('Labor is above 45% of client price; check manpower and duration.');
      if(!warnings.length) warnings.push('Estimate looks acceptable. Verify actual supplier quotes and site conditions.');
      const riskEl=form.querySelector('[data-risk-label]'); if(riskEl){ riskEl.className='mb-risk '+(risk==='safe'?'success':risk==='danger'?'danger':'warn'); riskEl.textContent=risk.charAt(0).toUpperCase()+risk.slice(1); }
      const warn=form.querySelector('[data-warnings]'); if(warn) warn.innerHTML=warnings.map(w=>`<div class="estimate-warning ${risk==='danger'?'danger':''}">${escapeHtml(w)}</div>`).join('');
    }
  }
  function initProposalBuilder(form){
    if(form.dataset.proposalBound) return; form.dataset.proposalBound='1';
    const est=form.querySelector('[data-proposal-estimate]');
    est?.addEventListener('change',()=>{ const opt=est.selectedOptions[0]; if(!opt) return; const title=form.querySelector('[data-proposal-title]'); const client=form.querySelector('[data-proposal-client]'); const loc=form.querySelector('[data-proposal-location]'); const type=form.querySelector('[data-proposal-type]'); const amount=form.querySelector('[data-proposal-amount]'); if(title&&!title.value) title.value=opt.dataset.title||''; if(client&&!client.value) client.value=opt.dataset.client||''; if(loc&&!loc.value) loc.value=opt.dataset.location||''; if(type&&opt.dataset.type) type.value=opt.dataset.type; if(amount&&num(amount.value)<=0) amount.value=opt.dataset.amount||0; });
  }
  function initAttendanceBoard(form){
    if(form.dataset.attendanceBound) return; form.dataset.attendanceBound='1';
    const start=form.dataset.start||'08:00';
    const end=form.dataset.end||'17:00';
    const grace=Math.max(0, parseInt(form.dataset.grace||'0',10)||0);
    const shell=form.closest('.attendance-shell')||form;
    const modalEl=shell.querySelector('#attendanceEntryModal') || document.getElementById('attendanceEntryModal');
    const modal=modalEl&&window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const modalFields={};
    let activeCard=null;
    if(modalEl){
      const modalError=modalEl.querySelector('[data-att-modal-error]');
      const modalReasonLabel=modalEl.querySelector('[data-att-reason-label]');
      modalEl.querySelectorAll('[data-att-modal-field]').forEach(field=>{ modalFields[field.dataset.attModalField]=field; });
      modalEl.querySelector('[data-att-save-details]')?.addEventListener('click',()=>{
        if(!activeCard) return;
        const status=modalFields.status?.value||'present';
        const notes=(modalFields.notes?.value||'').trim();
        if(status==='absent' && !notes){
          if(modalError){ modalError.textContent='Please enter the reason for absence.'; modalError.classList.remove('d-none'); }
          modalFields.notes?.focus();
          return;
        }
        if(modalError){ modalError.textContent=''; modalError.classList.add('d-none'); }
        Object.entries(modalFields).forEach(([key,field])=>{
          const value=(key==='time_in' || key==='time_out') ? to24HourValue(field.value) : field.value;
          setCardValue(activeCard,key,value);
        });
        paintCard(activeCard);
        modal?.hide();
      });
      modalEl.addEventListener('hidden.bs.modal',()=>{ if(modalError){ modalError.textContent=''; modalError.classList.add('d-none'); } activeCard=null; });
      modalFields.status?.addEventListener('change',()=>{
        const status=modalFields.status?.value||'present';
        if(modalReasonLabel) modalReasonLabel.textContent=status==='late' ? 'Reason For Late' : (status==='absent' ? 'Reason For Absence' : 'Reason / Notes');
      });
    }
    function pad(n){ return String(n).padStart(2,'0'); }
    function parseTime(time){
      const normalized=to24HourValue(time);
      if(!normalized) return null;
      const [h,m]=normalized.split(':').map(Number);
      return h*60+m;
    }
    function formatMinutes(total){
      total=Math.max(0, Math.round(Number(total)||0));
      const h=Math.floor(total/60);
      const m=total%60;
      return `${pad(h)}:${pad(m)}`;
    }
    function currentMinutes(){
      const now=new Date();
      return now.getHours()*60+now.getMinutes();
    }
    function addMinutes(time, mins){
      const normalized=to24HourValue(time);
      if(!normalized) return time;
      const [h,m]=normalized.split(':').map(Number);
      let total=h*60+m+mins;
      total=((total%(24*60))+(24*60))%(24*60);
      return `${pad(Math.floor(total/60))}:${pad(total%60)}`;
    }
    function computeLateMinutes(){
      const startMinutes=parseTime(start);
      if(startMinutes===null) return 0;
      const threshold=startMinutes+grace;
      const now=currentMinutes();
      return Math.max(0, now-threshold);
    }
    function rowInputs(card){
      return {
        status: card.querySelector('[data-att-input="status"]'),
        time_in: card.querySelector('[data-att-input="time_in"]'),
        time_out: card.querySelector('[data-att-input="time_out"]'),
        late_minutes: card.querySelector('[data-att-input="late_minutes"]'),
        overtime_hours: card.querySelector('[data-att-input="overtime_hours"]'),
        notes: card.querySelector('[data-att-input="notes"]')
      };
    }
    function setCardValue(card,key,value){
      const input=rowInputs(card)[key];
      if(input) input.value=value;
    }
    function statusLabel(value){ return String(value||'present').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); }
    function summaryText(card){
      const inputs=rowInputs(card);
      const tin=to12HourDisplay(inputs.time_in?.value)||'--:--';
      const tout=to12HourDisplay(inputs.time_out?.value)||'--:--';
      const late=inputs.late_minutes?.value||'0';
      const ot=inputs.overtime_hours?.value||'0';
      return `In ${tin} | Out ${tout} | Late ${late} | OT ${ot}`;
    }
    function paintCard(card){
      const inputs=rowInputs(card);
      const status=inputs.status?.value||'present';
      card.dataset.status=status;
      card.querySelector('[data-att-status-pill]')?.replaceChildren(document.createTextNode(statusLabel(status)));
      const inEl=card.querySelector('[data-att-display="time_in"]');
      const outEl=card.querySelector('[data-att-display="time_out"]');
      const lateEl=card.querySelector('[data-att-display="late_minutes"]');
      const otEl=card.querySelector('[data-att-display="overtime_hours"]');
      if(inEl) inEl.textContent=to12HourDisplay(inputs.time_in?.value)||'-';
      if(outEl) outEl.textContent=to12HourDisplay(inputs.time_out?.value)||'-';
      if(lateEl) lateEl.textContent=`${inputs.late_minutes?.value||0} min`;
      if(otEl) otEl.textContent=Number(inputs.overtime_hours?.value||0).toFixed(2);
      card.querySelectorAll('[data-att-quick]').forEach(btn=>btn.classList.toggle('active',btn.dataset.attQuick===status));
      const pill=card.querySelector('[data-att-status-pill]');
      if(pill){
        pill.className='attendance-status-pill';
        if(status==='present') pill.classList.add('present');
        else if(status==='late') pill.classList.add('late');
        else if(status==='absent') pill.classList.add('absent');
        else if(status==='rest_day') pill.classList.add('rest');
        else if(status==='leave') pill.classList.add('leave');
        else if(status==='half_day') pill.classList.add('half');
      }
      updateSummary();
    }
    function applyQuick(card,status){
      const lateMinutes=computeLateMinutes();
      const isLate=lateMinutes>0;
      setCardValue(card,'status',status==='late' || (status==='present' && isLate) ? 'late' : status);
      if(status==='present'){
        setCardValue(card,'time_in',isLate ? addMinutes(start, grace + lateMinutes) : start);
        setCardValue(card,'time_out',end);
        setCardValue(card,'late_minutes',String(isLate ? lateMinutes : 0));
        if(!(rowInputs(card).overtime_hours?.value)) setCardValue(card,'overtime_hours','0');
      } else if(status==='late'){
        const computedLate=Math.max(1, lateMinutes || grace || 15);
        setCardValue(card,'time_in',addMinutes(start, grace + computedLate));
        setCardValue(card,'time_out',end);
        setCardValue(card,'late_minutes',String(computedLate));
      } else if(status==='absent' || status==='rest_day' || status==='leave'){
        setCardValue(card,'time_in','');
        setCardValue(card,'time_out','');
        setCardValue(card,'late_minutes','0');
        setCardValue(card,'overtime_hours','0');
      } else if(status==='half_day'){
        setCardValue(card,'time_in',start);
        setCardValue(card,'time_out',addMinutes(start,240));
      }
      paintCard(card);
      if(status==='absent'){
        openDetails(card);
      }
    }
    function openDetails(card){
      activeCard=card;
      const inputs=rowInputs(card);
      Object.entries(modalFields).forEach(([key,field])=>{ field.value=(key==='time_in' || key==='time_out') ? to12HourDisplay(inputs[key]?.value||'') : (inputs[key]?.value||''); });
      const title=modalEl?.querySelector('#attendanceEntryTitle');
      const meta=modalEl?.querySelector('#attendanceEntryMeta');
      const modalReasonLabel=modalEl?.querySelector('[data-att-reason-label]');
      const modalError=modalEl?.querySelector('[data-att-modal-error]');
      if(title) title.textContent=card.dataset.employeeName||'Attendance Details';
      if(meta) meta.textContent=card.dataset.employeeMeta||'';
      if(modalReasonLabel){
        const status=inputs.status?.value||'present';
        modalReasonLabel.textContent=status==='late' ? 'Reason For Late' : (status==='absent' ? 'Reason For Absence' : 'Reason / Notes');
      }
      if(modalError){ modalError.textContent=''; modalError.classList.add('d-none'); }
      modal?.show();
    }
    function updateSummary(){
      let present=0,late=0,absent=0,half=0;
      form.querySelectorAll('[data-att-card]').forEach(card=>{
        const status=rowInputs(card).status?.value||'present';
        if(status==='present') present++;
        if(status==='late') late++;
        if(status==='absent') absent++;
        if(status==='half_day') half++;
      });
      const numbers=shell.querySelectorAll('.attendance-summary-card b');
      if(numbers[0]) numbers[0].textContent=String(present);
      if(numbers[1]) numbers[1].textContent=String(late);
      if(numbers[2]) numbers[2].textContent=String(absent);
      if(numbers[3]) numbers[3].textContent=String(half);
    }
    function filterRows(){
      const category=(shell.querySelector('[data-att-category-tabs] .active')?.dataset.attCategory||'office').toLowerCase();
      const department=(shell.querySelector('[data-att-filter="department"]')?.value||'').toLowerCase();
      const site=(shell.querySelector('[data-att-filter="site"]')?.value||'').toLowerCase();
      const search=(shell.querySelector('[data-att-search]')?.value||'').trim().toLowerCase();
      form.querySelectorAll('[data-att-row]').forEach(row=>{
        const matchCategory=(row.dataset.category||'').toLowerCase()===category;
        const matchDepartment=!department || (row.dataset.department||'').toLowerCase()===department;
        const matchSite=!site || (row.dataset.site||'').toLowerCase()===site;
        const matchSearch=!search || (row.dataset.search||'').includes(search);
        row.classList.toggle('d-none', !(matchCategory && matchDepartment && matchSite && matchSearch));
      });
    }
    form.querySelectorAll('[data-att-card]').forEach(card=>{
      paintCard(card);
      card.querySelectorAll('[data-att-quick]').forEach(btn=>{
        btn.addEventListener('click',()=>applyQuick(card,btn.dataset.attQuick||'present'));
      });
      card.querySelector('[data-att-open-details]')?.addEventListener('click',()=>{
        openDetails(card);
      });
    });
    shell.querySelectorAll('[data-att-category]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        shell.querySelectorAll('[data-att-category]').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        filterRows();
      });
    });
    shell.querySelectorAll('[data-att-filter]').forEach(sel=>sel.addEventListener('change',filterRows));
    shell.querySelector('[data-att-search]')?.addEventListener('input',filterRows);
    shell.querySelector('[data-att-today]')?.addEventListener('click',()=>{
      const inp=shell.querySelector('[data-attendance-date]');
      if(!inp) return;
      const d=new Date();
      const today=`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
      inp.value=today;
      refreshAttendanceDate(today, shell);
    });
    form.querySelectorAll('[data-att-bulk]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const mode=btn.dataset.attBulk||'present';
        form.querySelectorAll('[data-att-card]').forEach(card=>{
          if(mode==='clear'){
            setCardValue(card,'status','present');
            setCardValue(card,'time_in','');
            setCardValue(card,'time_out','');
            setCardValue(card,'late_minutes','0');
            setCardValue(card,'overtime_hours','0');
            setCardValue(card,'notes','');
            paintCard(card);
          } else {
            applyQuick(card,mode);
          }
        });
      });
    });
    updateSummary();
    filterRows();
  }
  document.querySelectorAll('[data-module]').forEach(el=>el.addEventListener('click',()=>load(el.dataset.module)));
  document.getElementById('workspaceRefresh')?.addEventListener('click',()=>load(active));
  let t=null; search?.addEventListener('input',()=>{clearTimeout(t); t=setTimeout(()=>{query=search.value.trim(); load(active);},300);});
  window.addEventListener('hashchange',()=>{const m=location.hash.replace('#','')||'overview'; if(m!==active) load(m);});
  load(active);
})();
