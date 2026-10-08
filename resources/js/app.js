import './blotter-search';

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById(
                'sidebar'
            );

        const backdrop =
            document.getElementById(
                'sidebarBackdrop'
            );

        const menuButton =
            document.getElementById(
                'menuButton'
            );

        const progress =
            document.getElementById(
                'pageProgress'
            );


        function openSidebar() {

            sidebar?.classList.add(
                'mobile-open'
            );

            backdrop?.classList.add(
                'show'
            );

            document.body.style.overflow =
                'hidden';
        }


        function closeSidebar() {

            sidebar?.classList.remove(
                'mobile-open'
            );

            backdrop?.classList.remove(
                'show'
            );

            document.body.style.overflow =
                '';
        }


        function startProgress() {

            if (!progress) {
                return;
            }

            progress.classList.remove(
                'complete'
            );

            progress.classList.add(
                'active'
            );
        }


        function finishProgress() {

            if (!progress) {
                return;
            }

            progress.classList.remove(
                'active'
            );

            progress.classList.add(
                'complete'
            );

            setTimeout(
                function () {
                    progress.classList.remove(
                        'complete'
                    );
                },
                280
            );
        }


        menuButton?.addEventListener(
            'click',
            openSidebar
        );


        backdrop?.addEventListener(
            'click',
            closeSidebar
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Escape'
                ) {
                    closeSidebar();
                }
            }
        );


        document
            .querySelectorAll(
                'a[href]'
            )
            .forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        function (event) {

                            const href =
                                link.getAttribute(
                                    'href'
                                );

                            if (
                                !href
                                ||
                                href === '#'
                                ||
                                href.startsWith(
                                    '#'
                                )
                                ||
                                href.startsWith(
                                    'javascript:'
                                )
                                ||
                                link.target ===
                                    '_blank'
                                ||
                                event.ctrlKey
                                ||
                                event.metaKey
                                ||
                                event.shiftKey
                                ||
                                event.altKey
                            ) {
                                return;
                            }

                            startProgress();

                            if (
                                window.innerWidth
                                <=
                                991
                            ) {
                                closeSidebar();
                            }
                        }
                    );
                }
            );


        document
            .querySelectorAll(
                'form'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function () {

                            if (
                                form.dataset
                                    .noLoading
                                ===
                                'true'
                            ) {
                                return;
                            }

                            startProgress();
                        }
                    );
                }
            );


        window.addEventListener(
            'pageshow',
            finishProgress
        );

    }
);

document.addEventListener('DOMContentLoaded', function () {
    const pickers = Array.from(document.querySelectorAll('[data-person-picker]'));

    function debounce(callback, delay = 250) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => callback.apply(this, args), delay);
        };
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    }

    function setupPicker(picker) {
        const searchUrl = picker.dataset.searchUrl;
        const hiddenInput = picker.querySelector('[data-person-id]');
        const searchInput = picker.querySelector('[data-person-search]');
        const resultsBox = picker.querySelector('[data-person-results]');
        const selectedBox = picker.querySelector('[data-person-selected]');
        const selectedName = picker.querySelector('[data-person-name]');
        const selectedMeta = picker.querySelector('[data-person-meta]');
        const clearButton = picker.querySelector('[data-person-clear]');
        let controller = null;

        function otherSelectedId() {
            return pickers
                .filter(item => item !== picker)
                .map(item => item.querySelector('[data-person-id]')?.value)
                .find(Boolean) || '';
        }

        function hideResults() {
            resultsBox.classList.add('d-none');
            resultsBox.innerHTML = '';
        }

        function showSelected(person) {
            hiddenInput.value = person.id;
            searchInput.value = '';
            searchInput.classList.add('d-none');
            selectedName.textContent = person.name;
            selectedMeta.textContent = `${person.code} â€¢ ${person.classification}`;
            selectedBox.classList.remove('d-none');
            hideResults();
        }

        function clearSelected() {
            hiddenInput.value = '';
            selectedName.textContent = '';
            selectedMeta.textContent = '';
            selectedBox.classList.add('d-none');
            searchInput.classList.remove('d-none');
            searchInput.value = '';
            searchInput.focus();
        }

        function renderResults(people) {
            resultsBox.innerHTML = '';

            if (! people.length) {
                resultsBox.innerHTML = `
                    <div class="list-group-item text-muted small">
                        No matching person found. Add them to the People Directory first.
                    </div>
                `;
                resultsBox.classList.remove('d-none');
                return;
            }

            const unavailableId = otherSelectedId();

            people.forEach(person => {
                const button = document.createElement('button');
                const isUnavailable = String(person.id) === String(unavailableId);

                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.disabled = isUnavailable;
                button.innerHTML = `
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">${escapeHtml(person.name)}</div>
                            <div class="small text-muted">${escapeHtml(person.code)}</div>
                        </div>
                        <span class="badge text-bg-light align-self-start">
                            ${escapeHtml(person.classification)}
                        </span>
                    </div>
                    ${isUnavailable ? '<div class="small text-danger mt-1">Already selected as the other party.</div>' : ''}
                `;

                if (! isUnavailable) {
                    button.addEventListener('click', () => showSelected(person));
                }

                resultsBox.appendChild(button);
            });

            resultsBox.classList.remove('d-none');
        }

        async function fetchPeople(params) {
            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            try {
                const response = await fetch(`${searchUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });

                if (! response.ok) {
                    throw new Error('Search request failed.');
                }

                return await response.json();
            } catch (error) {
                if (error.name === 'AbortError') {
                    return null;
                }

                console.error(error);
                return { data: [] };
            }
        }

        const runSearch = debounce(async function () {
    const term = searchInput.value.trim();

    const payload = await fetchPeople(
        new URLSearchParams({
            q: term,
        })
    );

    if (payload) {
        renderResults(
            payload.data || []
        );
    }
});

        searchInput.addEventListener('input', runSearch);
        searchInput.addEventListener('focus', runSearch);
        clearButton.addEventListener('click', clearSelected);

        document.addEventListener('click', function (event) {
            if (! picker.contains(event.target)) {
                hideResults();
            }
        });

        if (hiddenInput.value) {
            fetchPeople(new URLSearchParams({ id: hiddenInput.value }))
                .then(payload => {
                    const person = payload?.data?.[0];
                    if (person) {
                        showSelected(person);
                    } else {
                        clearSelected();
                    }
                });
        }
    }

    pickers.forEach(setupPicker);
});

document.addEventListener('DOMContentLoaded', function () {
    const directorySearch = document.querySelector('[data-directory-search]');

    if (! directorySearch) {
        return;
    }

    const searchUrl = directorySearch.dataset.searchUrl;
    const searchInput = directorySearch.querySelector('[data-directory-search-input]');
    const resultsBox = directorySearch.querySelector('[data-directory-search-results]');
    const classificationSelect = document.querySelector('select[name="classification"]');
    let people = [];
    let loaded = false;
    let loading = false;

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    }

    function hideResults() {
        resultsBox.classList.add('d-none');
    }

    function matchesSearch(person, term) {
        if (! term) {
            return true;
        }

        const haystack = [
            person.name,
            person.code,
            person.contact,
            person.classification,
            person.active ? 'active' : 'inactive',
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(term);
    }

    function renderResults() {
        const term = searchInput.value.trim().toLowerCase();
        const classification = classificationSelect?.value || '';

        const matches = people.filter(person => {
            const classificationMatches =
                ! classification
                || person.classification === classification;

            return classificationMatches && matchesSearch(person, term);
        });

        resultsBox.innerHTML = '';

        if (! matches.length) {
            resultsBox.innerHTML = `
                <div class="list-group-item text-muted small">
                    No matching person found.
                </div>
            `;
            resultsBox.classList.remove('d-none');
            return;
        }

        matches.forEach(person => {
            const button = document.createElement('button');
            const statusLabel = person.active ? 'Active' : 'Inactive';

            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="min-w-0">
                        <div class="fw-semibold">${escapeHtml(person.name)}</div>
                        <div class="small text-muted">
                            ${escapeHtml(person.code)}
                            ${person.contact ? ' • ' + escapeHtml(person.contact) : ''}
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <span class="badge text-bg-primary">
                            ${escapeHtml(person.classification)}
                        </span>
                        <span class="badge ${person.active ? 'text-bg-success' : 'text-bg-warning'}">
                            ${statusLabel}
                        </span>
                    </div>
                </div>
            `;

            button.addEventListener('click', function () {
                searchInput.value = person.code;
                hideResults();

                const form = searchInput.closest('form');

                if (form) {
                    form.requestSubmit();
                }
            });

            resultsBox.appendChild(button);
        });

        resultsBox.classList.remove('d-none');
    }

    async function loadPeople() {
        if (loaded || loading) {
            return;
        }

        loading = true;
        resultsBox.innerHTML = `
            <div class="list-group-item text-muted small">
                Loading people...
            </div>
        `;
        resultsBox.classList.remove('d-none');

        try {
            const response = await fetch(searchUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error('Unable to load People Directory.');
            }

            const payload = await response.json();
            people = payload.data || [];
            loaded = true;
            renderResults();
        } catch (error) {
            console.error(error);
            resultsBox.innerHTML = `
                <div class="list-group-item text-danger small">
                    Unable to load the people list. Please try again.
                </div>
            `;
            resultsBox.classList.remove('d-none');
        } finally {
            loading = false;
        }
    }

    searchInput.addEventListener('focus', async function () {
        await loadPeople();

        if (loaded) {
            renderResults();
        }
    });

    searchInput.addEventListener('click', async function () {
        await loadPeople();

        if (loaded) {
            renderResults();
        }
    });

    searchInput.addEventListener('input', function () {
        if (loaded) {
            renderResults();
        }
    });

    classificationSelect?.addEventListener('change', function () {
        if (loaded && ! resultsBox.classList.contains('d-none')) {
            renderResults();
        }
    });

    document.addEventListener('click', function (event) {
        if (! directorySearch.contains(event.target)) {
            hideResults();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideResults();
        }
    });
});

