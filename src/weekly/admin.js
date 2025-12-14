const apiBase = 'api/index.php';

async function loadWeeksAdmin() {
  try {
    const res = await fetch(`${apiBase}?action=weeks`);
    const weeks = await res.json();
    const container = document.getElementById('adminWeeks');
    if (!weeks || weeks.length === 0) { container.innerHTML = '<p>No weeks.</p>'; return; }

    container.innerHTML = weeks.map(w => `
      <li>
        ${w.title} (${w.startDate || ''})
        <button data-id="${w.id}" class="editBtn">Edit</button>
        <button data-id="${w.id}" class="deleteBtn">Delete</button>
      </li>
    `).join('');

    document.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', onEdit));
    document.querySelectorAll('.deleteBtn').forEach(b => b.addEventListener('click', onDelete));
  } catch(err) { alert('Failed to load weeks'); }
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
    document.getElementById('weekForm').dataset.editing = id;
  } catch(err) { alert('Failed to load week'); }
}

async function onDelete(e) {
  const id = e.target.dataset.id;
  if (!confirm('Delete this week?')) return;
  try {
    const res = await fetch(`${apiBase}?action=week_delete&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
    const data = await res.json();
    if (data.ok) loadWeeksAdmin(); else alert('Error: ' + (data.error || ''));
  } catch(err) { alert('Network error'); }
}

document.getElementById('weekForm')?.addEventListener('submit', async function(e){
  e.preventDefault();
  const form = e.target;
  const editingId = form.dataset.editing || null;
  const title = document.getElementById('title').value.trim();
  const startDate = document.getElementById('startDate').value;
  const description = document.getElementById('description').value.trim();
  const links = document.getElementById('links').value.split('\n').map(s=>s.trim()).filter(Boolean);
  const payload = { title, startDate, description, links };

  try {
    if (editingId) {
      const res = await fetch(`${apiBase}?action=week_update&id=${encodeURIComponent(editingId)}`, {
        method:'POST', body: JSON.stringify(payload), headers:{'Content-Type':'application/json'}
      });
      const data = await res.json();
      if (data.ok) { form.removeAttribute('data-editing'); form.reset(); loadWeeksAdmin(); }
      else alert('Error: ' + (data.error || ''));
    } else {
      const res = await fetch(`${apiBase}?action=week_create`, {
        method:'POST', body: JSON.stringify(payload), headers:{'Content-Type':'application/json'}
      });
      const data = await res.json();
      if (data.id || data.ok) { form.reset(); loadWeeksAdmin(); }
      else alert('Error: ' + (data.error || ''));
    }
  } catch(err) { alert('Network error'); }
});

loadWeeksAdmin();

