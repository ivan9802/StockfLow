// Point de vente : panier, disponibilité en direct, total, envoi par Fetch.
//
// Le navigateur ne fait qu'aider : le serveur relit les prix, revérifie le stock sous
// verrou et refuse toute la vente si une ligne manque de stock.

import { HttpError, escapeHtml, formatMoney, formatNumber, postJson } from '../lib/http';

function attach(form) {
    const rows = form.querySelector('[data-cart-rows]');
    const empty = form.querySelector('[data-cart-empty]');
    const totalEl = form.querySelector('[data-cart-total]');
    const unitsEl = form.querySelector('[data-cart-units]');
    const submit = form.querySelector('[data-cart-submit]');
    const feedback = form.querySelector('[data-cart-feedback]');
    const search = form.querySelector('[data-product-search]');
    const printAfter = form.querySelector('[data-print-after]');

    /** @type {Map<number, {id:number, sku:string, name:string, selling_price:number, stock:number, quantity:number}>} */
    const cart = new Map();

    const showError = (message, list = []) => {
        const items = list.length ? `<ul>${list.map((l) => `<li>${escapeHtml(l)}</li>`).join('')}</ul>` : '';
        feedback.innerHTML = `
            <div class="alert alert-danger d-flex gap-2" role="alert" tabindex="-1">
                <i class="bi bi-x-octagon flex-shrink-0" aria-hidden="true"></i>
                <div><div class="fw-semibold">${escapeHtml(message)}</div>${items}</div>
            </div>`;
        feedback.querySelector('.alert').focus();
    };

    const clearError = () => {
        feedback.innerHTML = '';
    };

    const render = () => {
        rows.innerHTML = [...cart.values()].map((line) => {
            const over = line.quantity > line.stock;
            const name = escapeHtml(line.name);
            return `
                <tr data-id="${line.id}" class="${over ? 'table-danger' : ''}">
                    <td>
                        <span class="fw-semibold">${name}</span>
                        <span class="row-sub code">${escapeHtml(line.sku)}</span>
                        ${over ? `<span class="row-sub text-danger">Seulement ${formatNumber(line.stock)} en stock</span>` : ''}
                    </td>
                    <td class="num col-p2">${formatNumber(line.stock)}</td>
                    <td class="num">
                        <label class="visually-hidden" for="qty-${line.id}">Quantité de ${name}</label>
                        <input type="number" class="form-control input-qty ms-auto ${over ? 'is-invalid' : ''}" id="qty-${line.id}"
                            min="1" step="1" inputmode="numeric" value="${line.quantity}" data-qty>
                    </td>
                    <td class="num col-p2">${formatMoney(line.selling_price)}</td>
                    <td class="num fw-semibold">${formatMoney(line.selling_price * line.quantity)}</td>
                    <td class="actions">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove aria-label="Retirer ${name}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                    </td>
                </tr>`;
        }).join('');

        const lines = [...cart.values()];
        const total = lines.reduce((sum, l) => sum + l.selling_price * l.quantity, 0);
        const units = lines.reduce((sum, l) => sum + l.quantity, 0);
        totalEl.textContent = formatMoney(total);
        unitsEl.textContent = formatNumber(units);
        empty.hidden = lines.length > 0;
        // Bouton actif seulement si le panier est cohérent avec le stock affiché.
        submit.disabled = lines.length === 0 || lines.some((l) => l.quantity < 1 || l.quantity > l.stock);
    };

    search.addEventListener('product:picked', (event) => {
        const product = event.detail;
        clearError();
        const line = cart.get(product.id);
        if (line) {
            line.quantity += 1;
            line.stock = product.stock;
        } else {
            if (product.stock === 0) {
                showError(`${product.name} est en rupture dans ce point de vente.`);
                return;
            }
            cart.set(product.id, { ...product, quantity: 1 });
        }
        render();
    });

    rows.addEventListener('input', (event) => {
        if (!event.target.matches('[data-qty]')) return;
        const id = Number(event.target.closest('tr').dataset.id);
        const line = cart.get(id);
        line.quantity = Math.max(0, parseInt(event.target.value, 10) || 0);
        // Mise à jour des montants sans reconstruire la ligne en cours de saisie.
        const total = [...cart.values()].reduce((sum, l) => sum + l.selling_price * l.quantity, 0);
        totalEl.textContent = formatMoney(total);
        unitsEl.textContent = formatNumber([...cart.values()].reduce((s, l) => s + l.quantity, 0));
        submit.disabled = [...cart.values()].some((l) => l.quantity < 1 || l.quantity > l.stock);
    });

    rows.addEventListener('change', (event) => {
        if (event.target.matches('[data-qty]')) render();
    });

    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove]');
        if (!button) return;
        cart.delete(Number(button.closest('tr').dataset.id));
        render();
        search.focus();
    });

    form.querySelector('[data-cart-clear]').addEventListener('click', () => {
        cart.clear();
        clearError();
        render();
        search.focus();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        clearError();
        submit.disabled = true;
        const original = submit.innerHTML;
        submit.textContent = 'Enregistrement...';

        const body = {
            warehouse_id: Number(form.querySelector('[name="warehouse_id"]').value),
            form_token: form.querySelector('[name="form_token"]').value,
            customer_name: form.querySelector('[name="customer_name"]').value,
            lines: [...cart.values()].map((l) => ({ product_id: l.id, quantity: l.quantity })),
        };

        try {
            const result = await postJson(form.action, body);
            window.location.href = printAfter?.checked ? `${result.print_url}?auto=1` : result.url;
        } catch (error) {
            submit.innerHTML = original;
            if (error instanceof HttpError && error.payload?.shortages) {
                // Stock modifié depuis l'affichage (vente simultanée) : on met le panier à jour.
                error.payload.shortages.forEach((s) => {
                    const line = cart.get(s.product_id);
                    if (line) line.stock = s.available;
                });
                render();
                showError('Stock insuffisant : la vente n\'a pas été enregistrée.',
                    error.payload.shortages.map((s) => `${s.product_name} : ${s.available} disponible(s), ${s.requested} demandé(s)`));
            } else if (error instanceof HttpError && error.payload?.errors) {
                showError('Le panier contient une erreur.', Object.values(error.payload.errors).flat());
                submit.disabled = false;
            } else {
                showError(error.message);
                submit.disabled = false;
            }
        }
    });

    render();
    search.focus();
}

export function initSaleCart() {
    document.querySelectorAll('form[data-sale-cart]').forEach(attach);
}
