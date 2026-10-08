document.addEventListener('DOMContentLoaded', () => {
    const search = document.querySelector('[data-blotter-search]');
    if (!search) return;

    const input = search.querySelector('[data-blotter-search-input]');
    const results = search.querySelector('[data-blotter-search-results]');
    const form = input.closest('form');
    const incidentType = form.querySelector('[name="incident_type_id"]');
    let timer;
    let controller;
    let requestId = 0;

    function closeResults() {
        clearTimeout(timer);
        controller?.abort();
        requestId++;
        results.classList.add('d-none');
        input.setAttribute('aria-expanded', 'false');
    }

    function showMessage(message) {
        const item = document.createElement('div');
        item.className = 'list-group-item text-muted small';
        item.textContent = message;
        results.replaceChildren(item);
        results.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    }

    async function loadResults() {
        controller?.abort();
        controller = new AbortController();
        const currentId = ++requestId;
        const url = new URL(form.action, window.location.href);
        url.searchParams.set('search', input.value.trim());
        if (incidentType.value) url.searchParams.set('incident_type_id', incidentType.value);
        showMessage('Searching blotter records...');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Search failed');
            const payload = await response.json();
            if (currentId !== requestId) return;
            if (!payload.data.length) {
                showMessage('No matching blotter records found.');
                return;
            }

            results.replaceChildren();
            payload.data.forEach(record => {
                const link = document.createElement('a');
                link.className = 'list-group-item list-group-item-action';
                link.href = record.view_url;
                const reference = document.createElement('div');
                reference.className = 'fw-semibold';
                reference.textContent = record.reference;
                const parties = document.createElement('div');
                parties.className = 'small';
                parties.textContent = `${record.complainants || '—'} • ${record.respondents || '—'}`;
                const detail = document.createElement('div');
                detail.className = 'small text-muted';
                detail.textContent = [record.incident, record.location].filter(Boolean).join(' • ');
                link.append(reference, parties, detail);
                results.appendChild(link);
            });
        } catch (error) {
            if (error.name !== 'AbortError' && currentId === requestId) {
                showMessage('Unable to load suggestions. Use the Search button to try again.');
            }
        }
    }

    function scheduleSearch() {
        clearTimeout(timer);
        controller?.abort();
        requestId++;
        results.replaceChildren();
        timer = setTimeout(loadResults, 200);
    }

    input.addEventListener('input', scheduleSearch);
    input.addEventListener('focus', scheduleSearch);
    input.addEventListener('click', scheduleSearch);
    incidentType.addEventListener('change', closeResults);
    form.addEventListener('submit', closeResults);
    document.addEventListener('click', event => {
        if (!search.contains(event.target)) closeResults();
    });
    search.addEventListener('focusout', event => {
        if (!search.contains(event.relatedTarget)) closeResults();
    });
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeResults();
            input.focus();
            clearTimeout(timer);
            return;
        }
        const links = Array.from(results.querySelectorAll('a'));
        if (results.classList.contains('d-none') || !links.length) return;
        const index = links.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = event.key === 'ArrowDown'
                ? Math.min(index + 1, links.length - 1)
                : index <= 0 ? -1 : index - 1;
            (next < 0 ? input : links[next]).focus();
        }
    });
});
