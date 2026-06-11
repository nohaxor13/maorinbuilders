
(function(){
  const shell=document.querySelector('.mb-shell');
  const key='maorin.workspace.collapsed';
  if(!shell)return;
  if(localStorage.getItem(key)==='1') shell.classList.add('is-collapsed');
  document.querySelectorAll('[data-mb-toggle-sidebar]').forEach(btn=>btn.addEventListener('click',()=>{ if(window.innerWidth<900){shell.classList.toggle('is-mobile-open');return;} shell.classList.toggle('is-collapsed'); localStorage.setItem(key,shell.classList.contains('is-collapsed')?'1':'0'); }));
  document.querySelectorAll('[data-mb-open-modal]').forEach(btn=>btn.addEventListener('click',()=>{ const id=btn.getAttribute('data-mb-open-modal'); const el=document.getElementById(id); if(el&&window.bootstrap){ new bootstrap.Modal(el).show(); }}));
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{ if(!confirm(el.getAttribute('data-confirm')||'Continue?')) e.preventDefault(); }));
  document.querySelectorAll('[data-plan-takeoff]').forEach(form=>{
    const scaleInput=form.querySelector('[data-plan-scale]');
    const counters={legend:form.querySelectorAll('[data-plan-row="legend"]').length,materials:form.querySelectorAll('[data-plan-row="materials"]').length};
    const num=v=>Number.parseFloat(v||'0')||0;
    const peso=v=>'PHP '+v.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    const factor=()=>{ const raw=(scaleInput?.value||'1:100').split(':')[1]; return Number.parseFloat(raw)||100; };
    const setTotal=(key,value)=>{ const el=form.querySelector(`[data-plan-total="${key}"]`); if(el) el.textContent=value; };
    const template=(type,idx)=>{
      if(type==='legend') return `<div class="plan-legend-row" data-plan-row="legend"><input type="color" name="legend[${idx}][color]" value="#2563eb"><input class="form-control form-control-sm" name="legend[${idx}][label]" placeholder="Label"><input class="form-control form-control-sm" name="legend[${idx}][material]" placeholder="Material"><input class="form-control form-control-sm" name="legend[${idx}][unit]" placeholder="Unit"><button class="btn btn-sm btn-outline-danger" type="button" data-plan-remove>Remove</button></div>`;
      return `<div class="plan-material-row" data-plan-row="materials"><input type="color" name="materials[${idx}][color]" value="#2563eb"><input class="form-control form-control-sm" name="materials[${idx}][material]" placeholder="Material"><input class="form-control form-control-sm" name="materials[${idx}][unit]" value="pcs" placeholder="Unit"><input class="form-control form-control-sm" type="number" step="0.001" name="materials[${idx}][length]" value="0" placeholder="Plan length"><input class="form-control form-control-sm" type="number" step="0.001" name="materials[${idx}][width]" value="0" placeholder="Plan width"><input class="form-control form-control-sm" type="number" step="0.001" name="materials[${idx}][height]" value="0" placeholder="Height/thick"><input class="form-control form-control-sm" type="number" step="0.001" name="materials[${idx}][area]" value="0" placeholder="Area sqm"><input class="form-control form-control-sm" type="number" step="0.001" name="materials[${idx}][qty]" value="0" placeholder="Qty"><input class="form-control form-control-sm" type="number" step="0.01" name="materials[${idx}][unit_cost]" value="0" placeholder="Unit cost"><input class="form-control form-control-sm" type="number" step="0.01" name="materials[${idx}][waste_percent]" value="5" placeholder="Waste %"><div class="plan-row-total" data-plan-line-total>PHP 0.00</div><button class="btn btn-sm btn-outline-danger" type="button" data-plan-remove>Remove</button></div>`;
    };
    const recalc=()=>{
      const scale=factor();
      let qtySum=0,areaSum=0,costSum=0;
      form.querySelectorAll('[data-plan-row="materials"]').forEach(row=>{
        const length=num(row.querySelector('[name*="[length]"]')?.value);
        const width=num(row.querySelector('[name*="[width]"]')?.value);
        const height=num(row.querySelector('[name*="[height]"]')?.value);
        const areaInput=row.querySelector('[name*="[area]"]');
        const qtyInput=row.querySelector('[name*="[qty]"]');
        const calculatedArea=length>0&&width>0 ? length*scale*width*scale : num(areaInput?.value);
        const calculatedQty=length>0&&width>0&&height>0 ? calculatedArea*height : num(qtyInput?.value);
        if(length>0&&width>0&&areaInput) areaInput.value=calculatedArea.toFixed(3);
        if(length>0&&width>0&&height>0&&qtyInput) qtyInput.value=calculatedQty.toFixed(3);
        const qty=num(qtyInput?.value), area=num(areaInput?.value), cost=qty*num(row.querySelector('[name*="[unit_cost]"]')?.value)*(1+num(row.querySelector('[name*="[waste_percent]"]')?.value)/100);
        qtySum+=qty; areaSum+=area; costSum+=cost;
        const total=row.querySelector('[data-plan-line-total]'); if(total) total.textContent=peso(cost);
      });
      form.querySelector('[data-plan-scale-label]')?.replaceChildren(document.createTextNode(scaleInput?.value||'1:100'));
      setTotal('qty',qtySum.toFixed(3)); setTotal('area',areaSum.toFixed(3)+' sqm'); setTotal('cost',peso(costSum));
    };
    form.addEventListener('input',recalc);
    form.addEventListener('click',e=>{
      const add=e.target.closest('[data-plan-add]');
      if(add){ const type=add.getAttribute('data-plan-add'); const holder=form.querySelector(`[data-plan-rows="${type}"]`); if(holder){ holder.insertAdjacentHTML('beforeend',template(type,counters[type]++)); recalc(); } }
      const remove=e.target.closest('[data-plan-remove]');
      if(remove){ remove.closest('[data-plan-row]')?.remove(); recalc(); }
    });
    recalc();
  });
})();
