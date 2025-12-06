// list.js أو details.js
const apiBase = 'api/index.php';


async function fetchWeeks() {
  const res = await fetch(`${apiBase}?action=weeks`);
  return await res.json();
}

function renderWeeks(weeks) {
  const container = document.getElementById('weeksContainer');
  if (!weeks || weeks.length === 0) {
    container.innerHTML = '<p>No weeks available yet.</p>';
    return;
  }

  const html = weeks.map(w => {
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

  container.innerHTML = html;

  document.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', onEdit));
  document.querySelectorAll('.deleteBtn').forEach(b => b.addEventListener('click', onDelete));
}

function escapeHtml(s) {
  if (!s) return '';
  return s.replaceAll('&', '&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
}

async function onEdit(e) {
  const id = e.target.dataset.id;
  const res = await fetch(`${apiBase}?action=week&id=${encodeURIComponent(id)}`);
  const week = await res.json();
  // fill form with values and change form to update mode
  document.getElementById('title').value = week.title || '';
  document.getElementById('startDate').value = week.startDate || '';
  document.getElementById('description').value = week.description || '';
  document.getElementById('links').value = (week.links || []).join('\n');
  document.getElementById('weekForm').dataset.editing = id;
  document.querySelector('#weekForm button').textContent = 'Update Week';
  window.scrollTo({top:0, behavior:'smooth'});
}

async function onDelete(e) {
  if (!confirm('Are you sure you want to delete this week? All related comments will also be deleted.')) return;
  const id = e.target.dataset.id;
  const res = await fetch(`${apiBase}?action=week_delete&id=${encodeURIComponent(id)}`, {method:'POST'});
  const data = await res.json();
  if (data.ok) { 
    alert('Deleted successfully'); 
    loadAndRender(); 
  } else { 
    alert('Error: ' + (data.error || '')); 
  }
}

async function onSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const editingId = form.dataset.editing || null;
  const title = document.getElementById('title').value.trim();
  const startDate = document.getElementById('startDate').value;
  const description = document.getElementById('description').value.trim();
  const links = document.getElementById('links').value.split('\n').map(s=>s.trim()).filter(Boolean);

  const payload = { title, startDate, description, links };

  if (editingId) {
    // update
    const res = await fetch(`${apiBase}?action=week_update&id=${encodeURIComponent(editingId)}`, {
      method: 'POST',
      body: JSON.stringify(payload),
      headers: {'Content-Type':'application/json'}
    });
    const data = await res.json();
    if (data.ok) {
      alert('Updated successfully');
      form.removeAttribute('data-editing');
      form.reset();
      document.querySelector('#weekForm button').textContent = 'Save Week';
      loadAndRender();
    } else {
      alert('Error: ' + (data.error || ''));
    }
  } else {
    // create
    const res = await fetch(`${apiBase}?action=week_create`, {
      method: 'POST',
      body: JSON.stringify(payload),
      headers: {'Content-Type':'application/json'}
    });
    const data = await res.json();
    if (data.id) {
      alert('Added successfully');
      form.reset();
      loadAndRender();
    } else if (data.error) {
      alert('Error: ' + data.error);
    } else {
      alert('An unexpected error occurred');
    }
  }
}

async function loadAndRender() {
  try {
    const weeks = await fetchWeeks();
    // sort by startDate ascending if available
    weeks.sort((a,b) => (a.startDate||'').localeCompare(b.startDate||''));
    renderWeeks(weeks);
  } catch (err) {
    document.getElementById('weeksContainer').innerText = 'Failed to load data.';
  }
}

document.getElementById('weekForm').addEventListener('submit', onSubmit);
loadAndRender();

