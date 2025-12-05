/*
  Requirement: Populate the "Course Resources" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add an `id="resource-list-section"` to the
     <section> element that will contain the resource articles.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
const listSection = document.getElementById("resource-list-section");

// --- Functions ---

// Create one <article> for a single resource
function createResourceArticle(resource) {
    const article = document.createElement("article");

    // Title
    const title = document.createElement("h2");
    title.textContent = resource.title;

    // Description
    const desc = document.createElement("p");
    desc.textContent = resource.description;

    // Link
    const link = document.createElement("a");
    link.href = `details.html?id=${resource.id}`;
    link.textContent = "View Resource & Discussion";

    // Build structure
    article.appendChild(title);
    article.appendChild(desc);
    article.appendChild(link);

    return article;
}

// Load all resources from resources.json
async function loadResources() {
    try {
        const response = await fetch("resources.json");
        const resources = await response.json();

        // Clear previous content
        listSection.innerHTML = "";

        // Add each resource
        resources.forEach(resource => {
            const article = createResourceArticle(resource);
            listSection.appendChild(article);
        });

    } catch (error) {
        console.error("Error loading resources:", error);
        listSection.innerHTML = "<p>Failed to load resources.</p>";
    }
}

// Initial page load
loadResources();

