 const apiBase = 'api/index.php';
const hasDOM = typeof document !== 'undefined';

async function fetchWeeks() {
  try {
    const res = await fetch(`${apiBase}?action=weeks`);
    return await res.json();
  } catch (err) {
    console.error(err);
    return [];
  }
}

function escapeHtml(s) {
  if (!s) return '';
  return s.replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;');
}

function renderWeeks(weeks) {
  if (!hasDOM) return;
  const container = document.getElementById('weeksContainer');
  if (!container) return;

  if (!weeks || weeks.length === 0) {
    container.innerHTML = '<p>No weeks available yet.</p>';
    return;
  }

  container.innerHTML = weeks.map(w => {
    const linksHtml = (w.links || []).map(l => `<li><a href="${escapeHtml(l)}" target="_blank">${escapeHtml(l)}</a></li>`).join('');
    return `
      <article class="card">
        <header>
          <strong>${escapeHtml(w.title)}</strong>
          <span class="small">(${escapeHtml(w.startDate)})</span>
        </header>
        <p>${escapeHtml(w.description || '')}</p>
        ${linksHtml ? `<ul class="links-list">${linksHtml}</ul>` : ''}
        <menu>
          <button data-id="${w.id}" class="editBtn">Edit</button>
          <button data-id="${w.id}" class="deleteBtn">Delete</button>
          <a class="contrast" href="details.html?id=${encodeURIComponent(w.id)}">View Details</a>
        </menu>
      </article>
    `;
  }).join('');

  document.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', onEdit));
  document.querySelectorAll('.deleteBtn').forEach(b => b.addEventListener('click', onDelete));
}

async function onEdit(e) {
  const id = e.target.dataset.id;
  try {
    const res = await fetch(`${apiBase}?action=week&id=${encodeURIComponent(id)}`);
    const week = await res.json();
    document.getElementById('title').value = week.title || '';
    document.getElementById('startDate').value = week.startDate || '';
    document.getElementById('description').value = week.description || '';
    document.getElementById('links').value = (week.links || []).join('\n');

    const form = document.getElementById('weekForm');
    form.dataset.editing = id;
    document.querySelector('#weekForm button').textContent = 'Update Week';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch(err) {
    alert('Failed to load week data.');
  }
}

async function onDelete(e) {
  const id = e.target.dataset.id;
  if (!confirm('Delete this week? All comments will also be deleted.')) return;
  try {
    const res = await fetch(`${apiBase}?action=week_delete&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
    const data = await res.json();
    if (data.ok) loadAndRender();
    else alert('Error: ' + (data.error || ''));
  } catch(err) {
    alert('Network error: ' + err.message);
  }
}

async function onSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const editingId = form.dataset.editing || null;
  const title = document.getElementById('title').value.trim();
  const startDate = document.getElementById('startDate').value;
  const description = document.getElementById('description').value.trim();
  const links = document.getElementById('links').value.split('\n').map(s => s.trim()).filter(Boolean);
  const payload = { title, startDate, description, links };

  try {
    if (editingId) {
      const res = await fetch(`${apiBase}?action=week_update&id=${encodeURIComponent(editingId)}`, {
        method: 'POST',
        body: JSON.stringify(payload),
        headers: { 'Content-Type': 'application/json' }
      });
      const data = await res.json();
      if (data.ok) {
        form.removeAttribute('data-editing');
        form.reset();
        document.querySelector('#weekForm button').textContent = 'Save Week';
        loadAndRender();
      } else alert('Error: ' + (data.error || ''));
    } else {
      const res = await fetch(`${apiBase}?action=week_create`, {
        method: 'POST',
        body: JSON.stringify(payload),
        headers: { 'Content-Type': 'application/json' }
      });
      const data = await res.json();
      if (data.id || data.ok) {
        form.reset();
        loadAndRender();
      } else alert('Error: ' + (data.error || ''));
    }
  } catch(err) {
    alert('Network error: ' + err.message);
  }
}

async function loadAndRender() {
  const weeks = await fetchWeeks();
  weeks.sort((a, b) => (a.startDate || '').localeCompare(b.startDate || ''));
  renderWeeks(weeks);
}

if (hasDOM) {
  document.getElementById('weekForm')?.addEventListener('submit', onSubmit);
  loadAndRender();
}







