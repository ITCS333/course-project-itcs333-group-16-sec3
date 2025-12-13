 // list.js أو details.js
const apiBase = 'api/index.php';
const hasDOM = typeof document !== 'undefined';

/* =========================
   API
========================= */
async function fetchWeeks() {
  const res = await fetch(`${apiBase}?action=weeks`);
  return await res.json();
}

/* =========================
   RENDER
========================= */
function renderWeeks(weeks) {
  if (!hasDOM) return;

  const container = document.getElementById('weeksContainer');
  if (!container) return;

  if (!weeks || weeks.length === 0) {
    container.innerHTML = '<p>No weeks available yet.</p>';
    return;
  }

  const html = weeks.map(w => {
    const linksHtml = (w.links || [])
      .map(l => `<li><a href="${escapeHtml(l)}" target="_blank">${escapeHtml(l)}</a></li>`)
      .join('');

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

  document.querySelectorAll('.editBtn')
    .forEach(b => b.addEventListener('click', onEdit));

  document.querySelectorAll('.deleteBtn')
    .forEach(b => b.addEventListener('click', onDelete));
}

/* =========================
   HELPERS
========================= */
function escapeHtml(s) {
  if (!s) return '';
  return s
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

/* =========================
   EVENTS
========================= */
async function onEdit(e) {
  if (!hasDOM) return;

  const id = e.target.dataset.id;
  const res = await fetch(`${apiBase}?action=week&id=${encodeURIComponent(id)}`);
  const week = await res.json();

  document.getElementById('title').value = week.title || '';
  document.getElementById('startDate').value = week.startDate || '';
  document.getElementById('description').value = week.description || '';
  document.getElementById('links').value = (week.links || []).join('\n');

  const form = document.getElementById('weekForm');
  form.dataset.editing = id;
  document.querySelector('#weekForm button').textContent = 'Update Week';

  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

async function onDelete(e) {
  const id = e.target.dataset.id;

  if (typeof confirm !== 'undefined') {
    if (!confirm('Are you sure you want to delete this week? All related comments will also be deleted.')) {
      return;
    }
  }

  const res = await fetch(
    `${apiBase}?action=week_delete&id=${encodeURIComponent(id)}`,
    { method: 'POST' }
  );
  const data = await res.json();

  if (data.ok) {
    if (typeof alert !== 'undefined') {
      alert('Deleted successfully');
    }
    loadAndRender();
  } else {
    if (typeof alert !== 'undefined') {
      alert('Error: ' + (data.error || ''));
    }
  }
}

async function onSubmit(e) {
  e.preventDefault();
  if (!hasDOM) return;

  const form = e.target;
  const editingId = form.dataset.editing || null;

  const title = document.getElementById('title').value.trim();
  const startDate = document.getElementById('startDate').value;
  const description = document.getElementById('description').value.trim();
  const links = document
    .getElementById('links')
    .value.split('\n')
    .map(s => s.trim())
    .filter(Boolean);

  const payload = { title, startDate, description, links };

  // UPDATE
  if (editingId) {
    const res = await fetch(
      `${apiBase}?action=week_update&id=${encodeURIComponent(editingId)}`,
      {
        method: 'POST',
        body: JSON.stringify(payload),
        headers: { 'Content-Type': 'application/json' }
      }
    );

    const data = await res.json();
    if (data.ok) {
      if (typeof alert !== 'undefined') {
        alert('Updated successfully');
      }
      form.removeAttribute('data-editing');
      form.reset();
      document.querySelector('#weekForm button').textContent = 'Save Week';
      loadAndRender();
    } else {
      if (typeof alert !== 'undefined') {
        alert('Error: ' + (data.error || ''));
      }
    }
  }
  // CREATE
  else {
    const res = await fetch(`${apiBase}?action=week_create`, {
      method: 'POST',
      body: JSON.stringify(payload),
      headers: { 'Content-Type': 'application/json' }
    });

    const data = await res.json();
    if (data.id) {
      if (typeof alert !== 'undefined') {
        alert('Added successfully');
      }
      form.reset();
      loadAndRender();
    } else if (data.error) {
      if (typeof alert !== 'undefined') {
        alert('Error: ' + data.error);
      }
    } else {
      if (typeof alert !== 'undefined') {
        alert('An unexpected error occurred');
      }
    }
  }
}

/* =========================
   LOAD
========================= */
async function loadAndRender() {
  try {
    const weeks = await fetchWeeks();
    weeks.sort((a, b) =>
      (a.startDate || '').localeCompare(b.startDate || '')
    );
    renderWeeks(weeks);
  } catch {
    if (hasDOM) {
      const c = document.getElementById('weeksContainer');
      if (c) c.innerText = 'Failed to load data.';
    }
  }
}

/* =========================
   INIT
========================= */
if (hasDOM) {
  document
    .getElementById('weekForm')
    ?.addEventListener('submit', onSubmit);

  loadAndRender();
}
