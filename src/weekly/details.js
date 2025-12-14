 const apiBase = 'api/index.php';
const params = new URLSearchParams(location.search);
const weekId = params.get('id');

function escapeHtml(s) {
  if (!s) return '';
  return s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}

async function loadWeek() {
  try {
    const res = await fetch(`${apiBase}?action=week&id=${encodeURIComponent(weekId)}`);
    if (!res.ok) {
      document.getElementById('title').innerText = 'Week not found';
      return;
    }
    const w = await res.json();
    document.getElementById('title').innerText = w.title;
    document.getElementById('startDate').innerText = w.startDate ? `Start Date: ${w.startDate}` : '';
    document.getElementById('description').innerText = w.description || '';

    const linksEl = document.getElementById('links');
    linksEl.innerHTML = '';
    if (w.links && w.links.length) {
      const ul = document.createElement('ul');
      w.links.forEach(l => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = l; a.target = '_blank'; a.textContent = l;
        li.appendChild(a);
        ul.appendChild(li);
      });
      linksEl.appendChild(ul);
    }
  } catch(err) { alert('Failed to load week data'); }
}

async function loadComments() {
  try {
    const res = await fetch(`${apiBase}?action=comments&week_id=${encodeURIComponent(weekId)}`);
    const comments = await res.json();
    const container = document.getElementById('commentsList');
    if (!comments || comments.length === 0) { container.innerHTML = '<p>No comments yet.</p>'; return; }

    container.innerHTML = comments.map(c => `
      <article class="card">
        <header>
          <strong>${escapeHtml(c.author)}</strong>
          <span class="small">${c.created_at ? escapeHtml(new Date(c.created_at).toLocaleString()) : ''}</span>
        </header>
        <p>${escapeHtml(c.text)}</p>
        ${c.edited_at ? `<p class="small">Edited: ${escapeHtml(new Date(c.edited_at).toLocaleString())}</p>` : ''}
        <menu>
          <button data-id="${c.id}" class="deleteComment">Delete</button>
        </menu>
      </article>
    `).join('');

    document.querySelectorAll('.deleteComment').forEach(b => b.addEventListener('click', onDeleteComment));
  } catch(err) { alert('Failed to load comments'); }
}

async function onDeleteComment(e) {
  const commentId = e.target.dataset.id;
  if (!confirm('Do you want to delete this comment?')) return;
  try {
    const res = await fetch(`${apiBase}?action=comment_delete`, {
      method:'POST',
      body: JSON.stringify({ week_id: String(weekId), comment_id: String(commentId) }),
      headers: {'Content-Type':'application/json'}
    });
    const data = await res.json();
    if (data.ok) loadComments(); else alert('Error: ' + (data.error || ''));
  } catch(err) { alert('Network error: ' + err.message); }
}

document.getElementById('commentForm')?.addEventListener('submit', async function(e){
  e.preventDefault();
  const text = document.getElementById('commentText').value.trim();
  if (!text) return;
  try {
    const res = await fetch(`${apiBase}?action=comment_add`, {
      method:'POST',
      body: JSON.stringify({ week_id: String(weekId), text }),
      headers: {'Content-Type':'application/json'}
    });
    if (res.status === 401) { alert('You must be logged in.'); return; }
    const data = await res.json();
    if (data.id || data.ok) {
      document.getElementById('commentText').value = '';
      loadComments();
    } else if (data.error) alert('Error: ' + data.error);
  } catch(err) { alert('Network error: ' + err.message); }
});

(async function init(){
  if (!weekId) { document.getElementById('title').innerText = 'No week selected'; return; }
  await loadWeek();
  await loadComments();
})();
