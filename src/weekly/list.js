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

/* =========================
   TASK3202 REQUIRED FUNCTION
   ========================= */
async function loadWeeks() {
  if (!hasFetch) return [];
  try {
    const res = await fetch(`${apiBase}?action=weeks`);
    if (!res || typeof res.json !== 'function') return [];
    return await res.json();
  } catch {
    return [];
  }
}

/* ========================= */

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

async function loadAndRender() {
  const weeks = await loadWeeks();
  weeks.sort((a, b) =>
    (a.startDate || '').localeCompare(b.startDate || '')
  );
  renderWeeks(weeks);
}

/* =========================
   منع التشغيل التلقائي في Jest
   ========================= */
if (hasDOM && hasFetch) {
  document.getElementById('weekForm')?.addEventListener('submit', onSubmit);
  loadAndRender();
}

