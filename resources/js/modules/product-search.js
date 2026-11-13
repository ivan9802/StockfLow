// Recherche instantanée de produit.
//
// Un champ portant data-product-search interroge /internal/products/search pendant la frappe.
// Deux usages :
//   - mode "global" (barre supérieure) : chaque résultat est un lien vers la fiche produit ;
//   - mode "pick" (point de vente, lignes de document) : choisir un résultat émet
//     l'événement "product:picked" sur le champ, avec le produit en détail.

import { escapeHtml, formatMoney, formatNumber, getJson } from '../lib/http';

const DELAY = 250;
const MIN_LENGTH = 2;

function attach(input) {
    const box = input.parentElement.querySelector('[data-search-results]');
    const url = input.dataset.searchUrl;
    const mode = input.dataset.productSearch;
    let timer = null;
    let controller = null;
    let results = [];
    let active = -1;

    const close = () => {
        box.hidden = true;
        box.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    };

    const note = (text) => {
        box.innerHTML = `<div class="result-note">${escapeHtml(text)}</div>`;
        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const render = () => {
        if (results.length === 0) {
            note(`Aucun produit ne correspond à « ${input.value.trim()} ».`);
            return;
        }

        box.innerHTML = results.map((product, index) => {
            const stock = product.stock === 0
                ? '<span class="sf-badge is-danger">Rupture</span>'
                : `<span class="tabular">${formatNumber(product.stock)} en stock</span>`;
            const body = `
                <span>
                    <span class="fw-semibold">${escapeHtml(product.name)}</span>
                    <span class="d-block small text-secondary"><span class="code">${escapeHtml(product.sku)}</span> · ${formatMoney(product.selling_price)}</span>
                </span>
                <span class="small text-nowrap">${stock}</span>`;

            return mode === 'global'
                ? `<a class="result" role="option" id="${input.id}-opt-${index}" href="${escapeHtml(product.url)}">${body}</a>`
                : `<button type="button" class="result" role="option" id="${input.id}-opt-${index}" data-index="${index}">${body}</button>`;
        }).join('');

        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const highlight = (index) => {
        const options = box.querySelectorAll('.result');
        options.forEach((option, i) => option.classList.toggle('is-active', i === index));
        active = index;
        if (index >= 0) {
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const pick = (index) => {
        const product = results[index];
        if (!product) return;

        if (mode === 'global') {
            window.location.href = product.url;
            return;
        }

        input.dispatchEvent(new CustomEvent('product:picked', { bubbles: true, detail: product }));
        input.value = '';
        close();
        input.focus();
    };

    const search = async () => {
        const term = input.value.trim();
        if (term.length < MIN_LENGTH) {
            close();
            return;
        }

        controller?.abort();
        controller = new AbortController();

        try {
            const query = new URLSearchParams({ q: term });
            if (input.dataset.warehouse) query.set('warehouse_id', input.dataset.warehouse);
            const payload = await getJson(`${url}?${query}`, controller.signal);
            results = payload.data;
            render();
        } catch (error) {
            if (error.name !== 'AbortError') {
                note('La recherche a échoué. Vérifiez la connexion et réessayez.');
            }
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, DELAY);
    });

    input.addEventListener('keydown', (event) => {
        const count = box.querySelectorAll('.result').length;

        if (event.key === 'ArrowDown' && count > 0) {
            event.preventDefault();
            highlight((active + 1) % count);
        } else if (event.key === 'ArrowUp' && count > 0) {
            event.preventDefault();
            highlight((active - 1 + count) % count);
        } else if (event.key === 'Enter') {
            // Entrée choisit le résultat surligné, ou le seul résultat (lecture d'un code-barres).
            if (!box.hidden && (active >= 0 || count === 1)) {
                event.preventDefault();
                pick(active >= 0 ? active : 0);
            } else if (mode !== 'global') {
                event.preventDefault();
            }
        } else if (event.key === 'Escape') {
            close();
        }
    });

    box.addEventListener('click', (event) => {
        const button = event.target.closest('button.result');
        if (button) pick(Number(button.dataset.index));
    });

    document.addEventListener('click', (event) => {
        if (!input.parentElement.contains(event.target)) close();
    });
}

export function initProductSearch() {
    document.querySelectorAll('[data-product-search]').forEach(attach);

    // Raccourci clavier « / » : place le curseur dans la recherche de la barre supérieure.
    document.addEventListener('keydown', (event) => {
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
        if (event.key === '/' && !typing) {
            const global = document.querySelector('[data-product-search="global"]');
            if (global) {
                event.preventDefault();
                global.focus();
            }
        }
    });
}
