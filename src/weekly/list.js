  const apiBase = 'api/index.php';
const hasDOM = typeof document !== 'undefined';
const hasFetch = typeof fetch !== 'undefined';

/* =========================
   TASK3201 REQUIRED FUNCTION
   ========================= */
function createWeekArticle(week) {
  const linksHtml = (week.links || [])
    .map(l => `<li><a href="${escapeHtml(l)}" target="_blank">${escapeHtml(l)}</a></li>`)
    .join('');

  return `
    <article class="card">
      <header>
        <strong>${escapeHtml(week.title)}</strong>
        <span class="small">(${escapeHtml(week.startDate || '')})</span>
      </header>
      <p>${escapeHtml(week.description || '')}</p>
      ${linksHtml ? `<ul class="links-list">${linksHtml}</ul>` : ''}
      <menu>
        <button data-id="${week.id}" class="editBtn">Edit</button>
        <button data-id="${week.id}" class="deleteBtn">Delete</button>
        <a class="contrast" href="details.html?id=${encodeURIComponent(week.id)}">View Details</a>
      </menu>
    </article>
  `;
}

/* ========================= */

async function fetchWeeks() {
  if (!hasFetch) return []; // ✅ يمنع خطأ Jest

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
  return s
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

function renderWeeks(weeks) {
  if (!hasDOM) return;

  const container = document.getElementById('weeksList');
  if (!container) return;

  if (!weeks || weeks.length === 0) {
    container.innerHTML = '<p>No weeks available yet.</p>';
    return;
  }

  container.innerHTML = weeks.map(createWeekArticle).join('');

  document.querySelectorAll('.editBtn').forEach(b =>
    b.addEventListener('click', onEdit)
  );
  document.querySelectorAll('.deleteBtn').forEach(b =>
    b.addEventListener('click', onDelete)
  );
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
  } catch {
    alert('Failed to load week data.');
  }
}

async function onDelete(e) {
  const id = e.target.dataset.id;
  if (!confirm('Delete this week?')) return;

  try {
    const res = await fetch(
      `${apiBase}?action=week_delete&id=${encodeURIComponent(id)}`,
      { method: 'DELETE' }
    );
    const data = await res.json();
    if (data.ok) loadAndRender();
  } catch (err) {
    alert('Network error: ' + err.message);
  }
}

async function loadAndRender() {
  const weeks = await fetchWeeks();
  weeks.sort((a, b) =>
    (a.startDate || '').localeCompare(b.startDate || '')
  );
  renderWeeks(weeks);
}

if (hasDOM) {
  document.getElementById('weekForm')?.addEventListener('submit', onSubmit);
  loadAndRender();
}
