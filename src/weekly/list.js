 // --- Element Selection ---
const listSection = document.querySelector('#weekly-list');

/**
 * Create an <article> for a week entry
 * @param {Object} week - {id, title, startDate, description}
 * @returns {HTMLElement}
 */
function createWeekArticle(week) {
    const article = document.createElement('article');

    const h2 = document.createElement('h2');
    h2.textContent = week.title;
    article.appendChild(h2);

    const pDate = document.createElement('p');
    pDate.textContent = `Starts on: ${week.startDate}`;
    article.appendChild(pDate);

    const pDesc = document.createElement('p');
    pDesc.textContent = week.description;
    article.appendChild(pDesc);

    const link = document.createElement('a');
    link.href = `details.html?id=${week.id}`;
    link.textContent = 'View Details & Discussion';
    article.appendChild(link);

    return article;
}

/**
 * Load weeks from JSON and populate the page
 */
async function loadWeeks() {
    try {
        const res = await fetch('weeks.json');
        if (!res.ok) throw new Error('Failed to load weeks.json');
        const weeks = await res.json();

        listSection.innerHTML = '';
        weeks.forEach(week => {
            const article = createWeekArticle(week);
            listSection.appendChild(article);
        });

    } catch (error) {
        console.error('Error loading weeks:', error);
        listSection.innerHTML = '<p>Failed to load weekly breakdown. Please try again later.</p>';
    }
}

// --- Initial Load ---
loadWeeks();



