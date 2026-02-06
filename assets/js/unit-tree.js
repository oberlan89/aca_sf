// assets/js/unit-tree.js

function getDirectShownCollapseInCard(cardEl) {
    // only the direct collapse under THIS card (not nested ones)
    for (const child of Array.from(cardEl.children)) {
        if (
            child instanceof HTMLElement &&
            child.classList.contains('collapse') &&
            child.classList.contains('show')
        ) {
            return child;
        }
    }
    return null;
}

function closeSiblingCollapses(collapseEl) {
    const Collapse = window.bootstrap?.Collapse;
    if (!Collapse) return; // don't crash if bootstrap isn't loaded

    const card = collapseEl.closest('.aca-card');
    if (!card) return;

    const col = card.parentElement;              // column that contains the card
    const row = col?.closest('.row.g-3');        // the row for this LEVEL
    if (!row) return;

    // Only look at direct children columns of THIS row level
    for (const siblingCol of Array.from(row.children)) {
        if (siblingCol === col) continue;

        const topCard = siblingCol.firstElementChild;
        if (!(topCard instanceof HTMLElement)) continue;
        if (!topCard.classList.contains('aca-card')) continue;

        const shown = getDirectShownCollapseInCard(topCard);
        if (!shown) continue;

        const inst = Collapse.getOrCreateInstance(shown, { toggle: false });
        inst.hide();
    }
}

async function loadChildrenOnce(collapseEl) {
    const container = collapseEl.querySelector('.js-children-container');
    if (!container) return;

    if (container.dataset.loaded === '1') return;

    // Find the button that targets this collapse
    const btn = document.querySelector(
        `.js-unit-toggle[data-bs-target="#${collapseEl.id}"]`
    );
    const url = btn?.dataset.url;
    if (!url) return;

    container.innerHTML = '<div class="aca-label">Cargando...</div>';

    try {
        const resp = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!resp.ok) throw new Error('Bad response');

        container.innerHTML = await resp.text();
        container.dataset.loaded = '1';
    } catch (err) {
        container.innerHTML =
            '<div class="text-danger small">Error al cargar subramas.</div>';
    }
}

// Accordion + lazy-load happens when Bootstrap is about to show a collapse
document.addEventListener('show.bs.collapse', (e) => {
    const collapseEl = e.target;
    if (!(collapseEl instanceof HTMLElement)) return;

    closeSiblingCollapses(collapseEl);
    loadChildrenOnce(collapseEl);
});

// Optional: label toggle (safe)
document.addEventListener('shown.bs.collapse', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLElement)) return;
    const btn = document.querySelector(`.js-unit-toggle[data-bs-target="#${el.id}"]`);
    if (btn) btn.textContent = 'Ocultar';
});

document.addEventListener('hidden.bs.collapse', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLElement)) return;
    const btn = document.querySelector(`.js-unit-toggle[data-bs-target="#${el.id}"]`);
    if (btn) btn.textContent = 'Subramas';
});
