// list.js أو details.js
const apiBase = 'api/index.php';


async function fetchWeeks() { 
  const r = await fetch(`${apiBase}?action=weeks`); 
  return await r.json(); 
}

function escapeHtml(s){ 
  if(!s) return ''; 
  return s.replaceAll('&','&amp;')
          .replaceAll('<','&lt;')
          .replaceAll('>','&gt;'); 
}

async function render() {
  const weeks = await fetchWeeks();
  const container = document.getElementById('weeksList');
  if (!weeks || weeks.length === 0) { 
    container.innerHTML = '<p>No weeks available yet.</p>'; 
    return; 
  }

  const search = (document.getElementById('search').value || '').toLowerCase();
  const filtered = weeks.filter(w => (w.title + ' ' + (w.description||'')).toLowerCase().includes(search));
  filtered.sort((a,b)=> (a.startDate||'').localeCompare(b.startDate||''));

  container.innerHTML = filtered.map(w => `
    <article class="card">
      <header>
        <strong>${escapeHtml(w.title)}</strong> 
        <span class="small">- ${escapeHtml(w.startDate||'')}</span>
      </header>
      <p>${escapeHtml((w.description||'').slice(0,250))}${(w.description||'').length > 250 ? '...' : ''}</p>
      <menu><a href="details.html?id=${encodeURIComponent(w.id)}">Details</a></menu>
    </article>
  `).join('');
}

document.getElementById('search').addEventListener('input', render);
render();






