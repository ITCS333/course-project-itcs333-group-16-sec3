/*
Student name: [Kawthar Ashoor - 202007918]
Requirement: Populate the "Course Assignments" list page.
*/
// --- Element Selections ---
const listSection = document.querySelector('#assignment-list-section');

// --- Functions ---

// Create one <article> for an assignment
function createAssignmentArticle(assignment) {
    const article = document.createElement('article');

    const title = document.createElement('h2');
    title.textContent = assignment.title;

    const due = document.createElement('p');
    due.textContent = "Due: " + assignment.dueDate;

    const desc = document.createElement('p');
    desc.textContent = assignment.description;

    const link = document.createElement('a');
    link.href = `details.html?id=${assignment.id}`;
    link.textContent = "View Details & Discussion";

    article.appendChild(title);
    article.appendChild(due);
    article.appendChild(desc);
    article.appendChild(link);

    return article;
}

// Load assignments.json and populate the page
async function loadAssignments() {
    try {
        const response = await fetch("assignments.json");
        const assignments = await response.json();

        listSection.innerHTML = ""; // Clear existing content

        assignments.forEach(assignment => {
            const article = createAssignmentArticle(assignment);
            listSection.appendChild(article);
        });

    } catch (error) {
        listSection.innerHTML = "<p>Error loading assignments.</p>";
        console.error(error);
    }
}

// --- Initial Page Load ---
loadAssignments();
