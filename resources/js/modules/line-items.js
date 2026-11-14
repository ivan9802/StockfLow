// Saisie de lignes de produits (transfert, commande fournisseur, réception directe).
// Voir resources/views/components/line-items.blade.php.

import { escapeHtml, formatNumber, getJson } from '../lib/http';

function attach(root) {
    const rows = root.querySelector('[data-line-rows]');
    const empty = root.querySelector('[data-line-empty]');
    const search = root.querySelector('[data-product-search]');
    const withCost = root.hasAttribute('data-with-cost');
    const warehouseFieldName = root.dataset.warehouseField;
    const form = root.closest('form');
    const warehouseField = warehouseFieldName ? form?.querySelector(`[name="${warehouseFieldName}"]`) : null;
    let index = rows.querySelectorAll('[data-line]').length + 1000;

    const refreshEmpty = () => {
        empty.hidden = rows.querySelector('[data-line]') !== null;
    };

    // Stock disponible dans l'emplacement choisi, affiché à côté de chaque ligne.
    const refreshAvailability = async () => {
        if (!warehouseField) return;
        if (search) search.dataset.warehouse = warehouseField.value;

        const lines = [...rows.querySelectorAll('[data-line]')];
        await Promise.all(lines.map(async (line) => {
            const cell = line.querySelector('[data-available]');
            if (!cell) return;
            try {
                const params = new URLSearchParams({ q: line.dataset.sku || line.querySelector('.code')?.textContent.trim() || '', warehouse_id: warehouseField.value });
                const payload = await getJson(`${search.dataset.searchUrl}?${params}`);
                const product = payload.data.find((p) => String(p.id) === line.dataset.productId);
                cell.textContent = product ? formatNumber(product.stock) : '?';
            } catch {
                cell.textContent = '?';
            }
        }));
    };

    const addLine = (product) => {
        const existing = rows.querySelector(`[data-line][data-product-id="${product.id}"]`);
        if (existing) {
            const quantity = existing.querySelector('input[name$="[quantity]"]');
            quantity.value = Number(quantity.value || 0) + 1;
            quantity.focus();
            quantity.select();
            return;
        }

        const i = index++;
        const name = escapeHtml(product.name);
        const row = document.createElement('tr');
        row.dataset.line = '';
        row.dataset.productId = product.id;
        row.dataset.sku = product.sku;
        row.innerHTML = `
            <td>
                <input type="hidden" name="lines[${i}][product_id]" value="${product.id}">
                <span class="fw-semibold">${name}</span>
                <span class="row-sub code">${escapeHtml(product.sku)}</span>
            </td>
            ${warehouseField ? `<td class="num col-p2" data-available>${formatNumber(product.stock)}</td>` : ''}
            <td class="num">
                <label class="visually-hidden" for="lines-${i}-quantity">Quantité de ${name}</label>
                <input type="number" min="1" step="1" inputmode="numeric" required id="lines-${i}-quantity"
                    class="form-control input-qty ms-auto" name="lines[${i}][quantity]" value="1">
            </td>
            ${withCost ? `
            <td class="num">
                <label class="visually-hidden" for="lines-${i}-cost">Prix d'achat de ${name}</label>
                <div class="input-group ms-auto input-cost">
                    <input type="number" min="0" step="1" inputmode="numeric" required id="lines-${i}-cost"
                        class="form-control text-end tabular" name="lines[${i}][unit_cost]" value="">
                    <span class="input-group-text">F CFA</span>
                </div>
            </td>` : ''}
            <td class="actions">
                <button type="button" class="btn btn-sm btn-outline-danger" data-line-remove aria-label="Retirer ${name}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </td>`;
        rows.appendChild(row);
        refreshEmpty();
        const quantity = row.querySelector('input[name$="[quantity]"]');
        quantity.focus();
        quantity.select();
    };

    search?.addEventListener('product:picked', (event) => addLine(event.detail));

    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-line-remove]');
        if (!button) return;
        button.closest('[data-line]').remove();
        refreshEmpty();
        search?.focus();
    });

    warehouseField?.addEventListener('change', refreshAvailability);
    refreshEmpty();
    refreshAvailability();
}

export function initLineItems() {
    document.querySelectorAll('[data-line-items]').forEach(attach);
}

// Formulaire à un seul produit (sortie interne, ajustement) : le produit choisi remplit un champ caché.
export function initSingleProduct() {
    document.querySelectorAll('form[data-single-product]').forEach((form) => {
        const search = form.querySelector('[data-product-search]');
        const id = form.querySelector('[data-picked-id]');
        const label = form.querySelector('[data-picked-label]');
        search?.addEventListener('product:picked', (event) => {
            const product = event.detail;
            id.value = product.id;
            label.innerHTML = `${escapeHtml(product.name)} <span class="code text-secondary">${escapeHtml(product.sku)}</span>`
                + ` <span class="text-secondary small">(${formatNumber(product.stock)} en stock au total)</span>`;
        });
    });
}
