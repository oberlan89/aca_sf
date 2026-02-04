document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-unit-toggle');
    if (!btn) return;

    const unitId = btn.dataset.unitId;
    const url = btn.dataset.url;

    const container = document.querySelector(
        `.js-children-container[data-unit-id="${unitId}"]`
    );
    if (!container) return;

    // If we already loaded once, just toggle visibility
    if (container.dataset.loaded === '1') {
        container.classList.toggle('d-none');
        return;
    }

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
});
