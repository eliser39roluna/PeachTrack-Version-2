(function(){
  const sidebar = document.querySelector('.sidebar');
  const btn = document.querySelector('[data-toggle-sidebar]');
  if(btn && sidebar){
    btn.addEventListener('click', ()=> sidebar.classList.toggle('open'));
  }

  // live duration timers
  function fmtDuration(sec){
    sec = Math.max(0, Math.floor(sec));
    const h = Math.floor(sec/3600);
    const m = Math.floor((sec%3600)/60);
    const s = sec%60;
    const pad = (n)=> String(n).padStart(2,'0');
    return `${pad(h)}:${pad(m)}:${pad(s)}`;
  }
  function tickDurations(){
    document.querySelectorAll('[data-start-iso]').forEach(el=>{
      const iso = el.getAttribute('data-start-iso');
      if(!iso) return;
      const t0 = new Date(iso).getTime();
      if(Number.isNaN(t0)) return;
      const sec = (Date.now() - t0)/1000;
      el.textContent = fmtDuration(sec);
    });
  }
  setInterval(tickDurations, 1000);
  tickDurations();

  // Admin polling: active shifts table
  async function pollActiveShifts(){
    const tableBody = document.querySelector('[data-active-shifts-body]');
    if(!tableBody) return;
    try{
      const res = await fetch('api/active_shifts.php', {cache:'no-store'});
      if(!res.ok) return;
      const data = await res.json();
      if(!data || !Array.isArray(data.items)) return;
      tableBody.innerHTML = data.items.map(item=>{
        const safe = (s)=> String(s ?? '').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
        return `
          <tr>
            <td>${safe(item.employee_name)} <span class="muted">(#${safe(item.employee_id)})</span></td>
            <td>${safe(item.user_name)}</td>
            <td>${safe(item.start_time)}</td>
            <td><span data-start-iso="${safe(item.start_iso)}">00:00:00</span></td>
            <td class="muted">#${safe(item.shift_id)}</td>
          </tr>`;
      }).join('') || '<tr><td colspan="5" class="muted">No active shifts.</td></tr>';
      tickDurations();
      const kpi = document.querySelector('[data-kpi-active-shifts]');
      if(kpi) kpi.textContent = String(data.items.length);
    } catch(e){
      // ignore
    }
  }
  setInterval(pollActiveShifts, 5000);
  pollActiveShifts();
})();
