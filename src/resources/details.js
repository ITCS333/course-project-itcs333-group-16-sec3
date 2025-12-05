/*
  Requirement: Populate the resource detail page and discussion forum.

  Instructions:
  1. Link this file to `details.html` using:
     <script src="details.js" defer></script>

  2. In `details.html`, add the following IDs:
     - To the <h1>: `id="resource-title"`
     - To the description <p>: `id="resource-description"`
     - To the "Access Resource Material" <a> tag: `id="resource-link"`
     - To the <div> for comments: `id="comment-list"`
     - To the "Leave a Comment" <form>: `id="comment-form"`
     - To the <textarea>: `id="new-comment"`

  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// These will hold the data related to *this* resource.
let currentResourceId = null;
let currentComments = [];

const resourceTitle = document.getElementById("resource-title");
const resourceDescription = document.getElementById("resource-description");
const resourceLink = document.getElementById("resource-link");
const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const newComment = document.getElementById("new-comment");

function getResourceIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get("id");
}

function renderResourceDetails(resource) {
    resourceTitle.textContent = resource.title;
    resourceDescription.textContent = resource.description;
    resourceLink.href = resource.link;
}

function createCommentArticle(comment) {
    const article = document.createElement("article");

    const p = document.createElement("p");
    p.textContent = comment.text;

    const footer = document.createElement("footer");
    footer.textContent = "Posted by: " + comment.author;

    article.appendChild(p);
    article.appendChild(footer);

    return article;
}

function renderComments() {
    commentList.innerHTML = "";
    currentComments.forEach(comment => {
        commentList.appendChild(createCommentArticle(comment));
    });
}

function handleAddComment(event) {
    event.preventDefault();

    const text = newComment.value.trim();
    if (!text) return;

    const comment = {
        author: "Student",
        text: text
    };

    currentComments.push(comment);
    renderComments();

    newComment.value = "";
}

async function initializePage() {
    currentResourceId = getResourceIdFromURL();
    if (!currentResourceId) {
        resourceTitle.textContent = "Resource not found.";
        return;
    }

    try {
        const [resourcesRes, commentsRes] = await Promise.all([
            fetch("resources.json"),
            fetch("resource-comments.json")
        ]);

        const resourcesData = await resourcesRes.json();
        const commentsData = await commentsRes.json();

        const resource = resourcesData.find(r => r.id === currentResourceId);
        currentComments = commentsData[currentResourceId] || [];

        if (!resource) {
            resourceTitle.textContent = "Resource not found.";
            return;
        }

        renderResourceDetails(resource);
        renderComments();

        commentForm.addEventListener("submit", handleAddComment);

    } catch (e) {
        resourceTitle.textContent = "Error loading data.";
        console.error(e);
    }
}

initializePage();
