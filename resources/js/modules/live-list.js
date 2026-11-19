// Filtres instantanés d'une liste.
//
// Structure attendue :
//   <div data-live-list>
//     <form data-live-form> ... champs de filtre ... </form>
//     <div data-live-target> ... tableau et pagination ... </div>
//   </div>
//
// À chaque frappe ou changement, le formulaire est envoyé par Fetch avec l'en-tête
// X-Partial : le serveur ne renvoie que le fragment du tableau, qui remplace l'ancien.
// Sans JavaScript, le formulaire reste un GET classique.

const DELAY = 300;

function attach(root) {
    const form = root.querySelector('[data-live-form]');
    const target = root.querySelector('[data-live-target]');
    if (!form || !target) return;

    let timer = null;
    let controller = null;

    const load = async (url, push = true) => {
        controller?.abort();
        controller = new AbortController();
        target.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: { 'X-Partial': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            });

            if (response.status === 401 || response.status === 419) {
                window.location.reload();
                return;
            }
            if (!response.ok) throw new Error(String(response.status));

            target.innerHTML = await response.text();
            // Apparition douce des nouvelles lignes (désactivée si moins d'animations demandées).
            target.classList.remove('is-refreshed');
            void target.offsetWidth;
            target.classList.add('is-refreshed');
            if (push) window.history.replaceState({}, '', url);
        } catch (error) {
            // En cas d'échec, on retombe sur le chargement normal de la page.
            if (error.name !== 'AbortError') window.location.href = url;
        } finally {
            target.removeAttribute('aria-busy');
        }
    };

    const currentUrl = () => {
        const params = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            if (value !== '') params.set(key, value);
        });
        const query = params.toString();
        return form.action + (query ? `?${query}` : '');
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load(currentUrl());
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="search"], input[type="text"]')) {
            clearTimeout(timer);
            timer = setTimeout(() => load(currentUrl()), DELAY);
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches('select, input[type="date"], input[type="checkbox"]')) {
            load(currentUrl());
        }
    });

    // Tri et pagination : les liens du fragment sont chargés de la même façon.
    target.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-live-link], .pagination a');
        if (link) {
            event.preventDefault();
            load(link.href);
        }
    });
}

export function initLiveList() {
    document.querySelectorAll('[data-live-list]').forEach(attach);
}
