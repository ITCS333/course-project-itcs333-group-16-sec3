/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add an `id="resources-tbody"` to the <tbody> element
     inside your `resources-table`.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
let resources = [];

// --- Element Selections ---
const resourceForm = document.querySelector("#resource-form");
const resourcesTableBody = document.querySelector("#resources-tbody");

// --- Functions ---

// Create one table row
function createResourceRow(resource) {
  const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description}</td>
    <td>
      <button class="edit-btn" data-id="${resource.id}">Edit</button>
      <button class="delete-btn" data-id="${resource.id}">Delete</button>
    </td>
  `;

  return tr;
}

// Render the whole table from the array
function renderTable() {
  resourcesTableBody.innerHTML = "";

  resources.forEach((res) => {
    const row = createResourceRow(res);
    resourcesTableBody.appendChild(row);
  });
}

// Add new resource
function handleAddResource(event) {
  event.preventDefault();

  const title = document.querySelector("#resource-title").value.trim();
  const description = document.querySelector("#resource-description").value.trim();
  const link = document.querySelector("#resource-link").value.trim();

  if (!title || !description || !link) return;

  const newResource = {
    id: `res_${Date.now()}`,
    title,
    description,
    link
  };

  resources.push(newResource);

  renderTable();
  resourceForm.reset();
}

// Delete resource (using event delegation)
function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;

    resources = resources.filter((res) => res.id !== id);

    renderTable();
  }
}

// Load initial data
async function loadAndInitialize() {
  try {
    const response = await fetch("resources.json");
    resources = await response.json();
  } catch (error) {
    console.log("resources.json not found — starting empty.");
    resources = [];
  }

  renderTable();

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTableBody.addEventListener("click", handleTableClick);
}

// --- Initialize ---
loadAndInitialize();

