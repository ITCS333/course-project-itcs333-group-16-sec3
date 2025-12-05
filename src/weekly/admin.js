 // --- Global Data Store ---
let weeks = [];
let editMode = false;

// --- Element Selections ---
const weekForm = document.querySelector('#week-form');
const weeksTableBody = document.querySelector('#weeks-tbody');
const inputWeekId = document.querySelector('#week-id');
const inputTitle = document.querySelector('#week-title');
const inputStartDate = document.querySelector('#week-start-date');
const inputDescription = document.querySelector('#week-description');
const inputLinks = document.querySelector('#week-links');
const cancelEditBtn = document.querySelector('#cancel-edit');

// --- Functions ---

function createWeekRow(week) {
    const tr = document.createElement('tr');

    const titleTd = document.createElement('td');
    titleTd.textContent = week.title;
    tr.appendChild(titleTd);

    const descTd = document.createElement('td');
    descTd.textContent = week.description;
    tr.appendChild(descTd);

    const actionsTd = document.createElement('td');

    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.classList.add('edit-btn');
    editBtn.setAttribute('data-id', week.id);

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Delete';
    deleteBtn.classList.add('delete-btn');
    deleteBtn.setAttribute('data-id', week.id);

    actionsTd.appendChild(editBtn);
    actionsTd.appendChild(deleteBtn);
    tr.appendChild(actionsTd);

    return tr;
}

function renderTable() {
    weeksTableBody.innerHTML = '';
    weeks.forEach(week => {
        const row = createWeekRow(week);
        weeksTableBody.appendChild(row);
    });
}

function resetForm() {
    editMode = false;
    inputWeekId.value = '';
    inputTitle.value = '';
    inputStartDate.value = '';
    inputDescription.value = '';
    inputLinks.value = '';
}

function handleAddOrEditWeek(event) {
    event.preventDefault();

    const title = inputTitle.value.trim();
    const startDate = inputStartDate.value;
    const description = inputDescription.value.trim();
    const links = inputLinks.value
        ? inputLinks.value.split('\n').map(l => l.trim()).filter(l => l)
        : [];

    if (editMode) {
        // Update existing week
        const weekId = inputWeekId.value;
        const index = weeks.findIndex(w => w.id === weekId);
        if (index !== -1) {
            weeks[index] = { id: weekId, title, startDate, description, links };
        }
    } else {
        // Add new week
        const newWeek = { id: `week_${Date.now()}`, title, startDate, description, links };
        weeks.push(newWeek);
    }

    renderTable();
    resetForm();
}

function handleTableClick(event) {
    const target = event.target;
    const weekId = target.getAttribute('data-id');

    if (target.classList.contains('delete-btn')) {
        weeks = weeks.filter(w => w.id !== weekId);
        renderTable();
        if (editMode && inputWeekId.value === weekId) resetForm();
    }

    if (target.classList.contains('edit-btn')) {
        const week = weeks.find(w => w.id === weekId);
        if (!week) return;
        editMode = true;
        inputWeekId.value = week.id;
        inputTitle.value = week.title;
        inputStartDate.value = week.startDate;
        inputDescription.value = week.description;
        inputLinks.value = week.links.join('\n');
        inputTitle.focus();
    }
}

function handleCancelEdit() {
    resetForm();
}

async function loadWeeksData() {
    try {
        const response = await fetch('weeks.json');
        if (!response.ok) throw new Error('Failed to load weeks.json');
        const data = await response.json();
        weeks = data;
        renderTable();
    } catch (error) {
        console.error(error);
        // Fallback: empty array
        weeks = [];
        renderTable();
    }
}

// --- Event Listeners ---
weekForm.addEventListener('submit', handleAddOrEditWeek);
weeksTableBody.addEventListener('click', handleTableClick);
cancelEditBtn.addEventListener('click', handleCancelEdit);

// --- Initial Load ---
loadWeeksData();

