// assets/js/public.js
// Public site enhancements (no dependencies)

(() => {
  function initBeforeAfter(root) {
    const beforeWrap = root.querySelector('[data-ba-before]');
    const range = root.querySelector('[data-ba-range]');
    const line = root.querySelector('[data-ba-line]');
    if (!beforeWrap || !range) return;

    const set = (v) => {
      const pct = Math.max(0, Math.min(100, Number(v) || 50));
      beforeWrap.style.width = pct + '%';
      if (line) line.style.left = pct + '%';
    };

    // initial
    set(range.value);

    range.addEventListener('input', () => set(range.value));

    // Support click-to-set on the container
    root.addEventListener('pointerdown', (e) => {
      // avoid dragging the range itself twice
      if (e.target === range) return;
      const rect = root.getBoundingClientRect();
      const pct = ((e.clientX - rect.left) / rect.width) * 100;
      range.value = String(Math.round(pct));
      set(range.value);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-beforeafter]').forEach(initBeforeAfter);
  });

  async function initEstimator(){
    const form = document.getElementById('estForm');
    const box = document.getElementById('estResult');
    if (!form || !box) return;

    const costEl = document.getElementById('estCost');
    const timeEl = document.getElementById('estTime');
    const incEl  = document.getElementById('estIncluded');
    const refEl  = document.getElementById('estRef');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);

      const btn = form.querySelector('button[type="submit"]');
      const old = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Estimating…'; }

      try{
        const res = await fetch('estimator_submit.php', { method:'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Estimate failed');

        const min = Number(data.min || 0);
        const max = Number(data.max || 0);

        const fmt = (n)=> '₱' + Math.round(n).toLocaleString();
        costEl.textContent = `${fmt(min)} – ${fmt(max)}`;
        timeEl.textContent = data.timeline || '—';
        refEl.textContent  = data.ref || '—';

        incEl.innerHTML = '';
        (data.included || []).forEach((x)=>{
          const li = document.createElement('li');
          li.textContent = x;
          incEl.appendChild(li);
        });

        box.classList.remove('d-none');
        box.scrollIntoView({behavior:'smooth', block:'start'});
      }catch(err){
        alert(err?.message || 'Estimate failed');
      }finally{
        if (btn) { btn.disabled = false; btn.textContent = old; }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initEstimator();
  });
})();
