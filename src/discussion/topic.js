/*
Student Name: [Kawthar Ashoor - 202007918]
Requirement: Populate the single topic page and manage replies.
*/
// --- Global Data Store ---
let currentTopicId = null;
let currentReplies = [];

// --- Element Selections ---
const topicSubject = document.querySelector('#topic-subject');
const opMessage = document.querySelector('#op-message');
const opFooter = document.querySelector('#op-footer');
const replyListContainer = document.querySelector('#reply-list-container');
const replyForm = document.querySelector('#reply-form');
const newReplyText = document.querySelector('#new-reply');

// --- Functions ---

function getTopicIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function renderOriginalPost(topic) {
    topicSubject.textContent = topic.subject;
    opMessage.textContent = topic.message;
    opFooter.textContent = `Posted by: ${topic.author} on ${topic.date}`;
}

function createReplyArticle(reply) {
    const article = document.createElement('article');
    article.classList.add('reply');

    const p = document.createElement('p');
    p.textContent = reply.text;

    const footer = document.createElement('footer');
    footer.textContent = `Posted by: ${reply.author} on ${reply.date}`;

    const actions = document.createElement('div');
    actions.classList.add('actions');

    const deleteBtn = document.createElement('button');
    deleteBtn.classList.add('delete-reply-btn');
    deleteBtn.dataset.id = reply.id;
    deleteBtn.textContent = "Delete";

    actions.appendChild(deleteBtn);

    article.appendChild(p);
    article.appendChild(footer);
    article.appendChild(actions);

    return article;
}

function renderReplies() {
    replyListContainer.innerHTML = '';

    currentReplies.forEach(reply => {
        const article = createReplyArticle(reply);
        replyListContainer.appendChild(article);
    });
}

function handleAddReply(event) {
    event.preventDefault();

    const text = newReplyText.value.trim();
    if (!text) return;

    const newReply = {
        id: `reply_${Date.now()}`,
        author: 'Student',
        date: new Date().toISOString().split('T')[0],
        text
    };

    currentReplies.push(newReply);
    renderReplies();

    newReplyText.value = '';
}

function handleReplyListClick(event) {
    if (event.target.classList.contains('delete-reply-btn')) {
        const id = event.target.dataset.id;
        currentReplies = currentReplies.filter(r => r.id !== id);
        renderReplies();
    }
}

async function initializePage() {
    currentTopicId = getTopicIdFromURL();

    if (!currentTopicId) {
        topicSubject.textContent = "Topic not found.";
        return;
    }

    const [topicsResponse, repliesResponse] = await Promise.all([
        fetch('topics.json'),
        fetch('replies.json')
    ]);

    const topics = await topicsResponse.json();
    const repliesData = await repliesResponse.json();

    const topic = topics.find(t => t.id === currentTopicId);
    currentReplies = repliesData[currentTopicId] || [];

    if (!topic) {
        topicSubject.textContent = "Topic not found.";
        return;
    }

    renderOriginalPost(topic);
    renderReplies();

    replyForm.addEventListener('submit', handleAddReply);
    replyListContainer.addEventListener('click', handleReplyListClick);
}

// INITIAL PAGE LOAD
initializePage();
