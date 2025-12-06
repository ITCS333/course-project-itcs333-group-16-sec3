 // details.js
// list.js أو details.js
const apiBase = 'api/index.php';
const params = new URLSearchParams(location.search);
const weekId = params.get('id');

function escapeHtml(s){ 
  if(!s) return ''; 
  return s.replaceAll('&','&amp;')
          .replaceAll('<','&lt;')
          .replaceAll('>','&gt;'); 
}

async function loadWeek() {
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
      li.innerHTML = `<a href="${escapeHtml(l)}" target="_blank">${escapeHtml(l)}</a>`;
      ul.appendChild(li);
    });
    linksEl.appendChild(ul);
  }
}

async function loadComments() {
  const res = await fetch(`${apiBase}?action=comments&week_id=${encodeURIComponent(weekId)}`);
  const comments = await res.json();
  const container = document.getElementById('commentsList');

  if (!comments || comments.length === 0) { 
    container.innerHTML = '<p>No comments yet.</p>'; 
    return; 
  }

  container.innerHTML = comments.map(c => `
    <article class="card">
      <header>
        <strong>${escapeHtml(c.author)}</strong> 
        <span class="small">${escapeHtml(new Date(c.created_at||'').toLocaleString())}</span>
      </header>
      <p>${escapeHtml(c.text)}</p>
      ${c.edited_at ? `<p class="small">Edited: ${escapeHtml(new Date(c.edited_at).toLocaleString())}</p>` : ''}
      <menu>
        <button data-id="${c.id}" class="deleteComment">Delete</button>
      </menu>
    </article>
  `).join('');

  document.querySelectorAll('.deleteComment')
    .forEach(b => b.addEventListener('click', onDeleteComment));
}

async function onDeleteComment(e) {
  const commentId = e.target.dataset.id;
  if (!confirm('Do you want to delete this comment?')) return;

  const res = await fetch(`${apiBase}?action=comment_delete`, {
    method:'POST',
    body: JSON.stringify({ week_id: weekId, comment_id: commentId }),
    headers: {'Content-Type':'application/json'}
  });

  const data = await res.json();
  if (data.ok) {
    loadComments();
  } else {
    alert('Error: ' + (data.error || ''));
  }
}

document.getElementById('commentForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const text = document.getElementById('commentText').value.trim();
  if (!text) return;

  const res = await fetch(`${apiBase}?action=comment_add`, {
    method:'POST',
    body: JSON.stringify({ week_id: weekId, text }),
    headers: {'Content-Type':'application/json'}
  });

  if (res.status === 401) {
    alert('You must be logged in to add a comment. Make sure the login system is enabled.');
    return;
  }

  const data = await res.json();
  if (data.id) {
    document.getElementById('commentText').value = '';
    loadComments();
  } else if (data.error) {
    alert('Error: ' + data.error);
  } else {
    loadComments();
  }
});

(async function init(){
  if (!weekId) {
    document.getElementById('title').innerText = 'No week selected';
    return;
  }
  await loadWeek();
  await loadComments();
})();
