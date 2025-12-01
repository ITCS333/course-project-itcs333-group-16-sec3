/*
Student Name: [Kawthar Ashoor - 22007918]
Requirement: Make the "Discussion Board" page interactive.
*/
// --- Global Data Store ---
let topics = [];

// --- Element Selections ---
const newTopicForm = document.querySelector('#new-topic-form');
const topicListContainer = document.querySelector('#topic-list-container');

// --- Functions ---

// Create one <article> for a topic
function createTopicArticle(topic) {
    const article = document.createElement('article');

    // Title + link
    const h3 = document.createElement('h3');
    const link = document.createElement('a');
    link.href = `topic.html?id=${topic.id}`;
    link.textContent = topic.subject;

    h3.appendChild(link);

    // Footer
    const footer = document.createElement('footer');
    footer.textContent = `Posted by: ${topic.author} on ${topic.date}`;

    // Actions
    const actions = document.createElement('div');

    const editBtn = document.createElement('button');
    editBtn.textContent = "Edit";

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = "Delete";
    deleteBtn.classList.add("delete-btn");
    deleteBtn.dataset.id = topic.id;

    actions.appendChild(editBtn);
    actions.appendChild(deleteBtn);

    // Attach all elements
    article.appendChild(h3);
    article.appendChild(footer);
    article.appendChild(actions);

    return article;
}

// Display all topics
function renderTopics() {
    topicListContainer.innerHTML = '';

    topics.forEach(topic => {
        const article = createTopicArticle(topic);
        topicListContainer.appendChild(article);
    });
}

// Create a new topic from the form
function handleCreateTopic(event) {
    event.preventDefault();

    const subject = document.querySelector('#topic-subject').value.trim();
    const message = document.querySelector('#topic-message').value.trim();

    const newTopic = {
        id: `topic_${Date.now()}`,
        subject,
        message,
        author: 'Student',
        date: new Date().toISOString().split('T')[0]
    };

    topics.push(newTopic);
    renderTopics();
    newTopicForm.reset();
}

// Handle delete button clicks (event delegation)
function handleTopicListClick(event) {
    if (event.target.classList.contains('delete-btn')) {
        const id = event.target.dataset.id;

        topics = topics.filter(t => t.id !== id);

        renderTopics();
    }
}

// Load topics.json and set up event handlers
async function loadAndInitialize() {
    const response = await fetch('topics.json');
    topics = await response.json();

    renderTopics();

    newTopicForm.addEventListener('submit', handleCreateTopic);
    topicListContainer.addEventListener('click', handleTopicListClick);
}

// INITIAL LOAD
loadAndInitialize();
