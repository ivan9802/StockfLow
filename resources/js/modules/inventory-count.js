// Écran de comptage d'inventaire : chaque ligne est enregistrée par Fetch dès que le
// compteur quitte le champ. Le serveur relève alors le stock théorique de la ligne et
// renvoie l'écart, qui s'affiche aussitôt. Sans JavaScript, le bouton d'envoi suffit.

import { formatNumber, putJson } from '../lib/http';

const signed = (n) => (n > 0 ? `+${formatNumber(n)}` : formatNumber(n));

function attach(form) {
    const status = form.querySelector('[data-save-status]');
    const counter = document.querySelector('[data-counted]');

    const save = async (row) => {
        const id = row.dataset.item;
        const count = row.querySelector('[data-count]');
        const why = row.querySelector('[data-why]');
        if (count.value === '' && why.value === '') return;

        status.textContent = 'Enregistrement...';
        try {
            const payload = await putJson(form.action, {
                counts: { [id]: { counted_quantity: count.value === '' ? null : Number(count.value), justification: why.value } },
            });
            const item = payload.items.find((i) => String(i.id) === id);
            if (item && item.counted_quantity !== null) {
                row.querySelector('[data-theoretical]').textContent = formatNumber(item.theoretical_quantity);
                row.querySelector('[data-variance]').textContent = signed(item.variance);
                row.classList.toggle('table-warning', item.variance !== 0);
                why.placeholder = item.variance !== 0 ? 'Obligatoire pour un écart' : '';
                why.classList.toggle('is-invalid', item.variance !== 0 && (item.justification ?? '').trim().length < 5);
            }
            if (counter) counter.textContent = `${payload.counted} / ${payload.total}`;
            status.textContent = `Enregistré à ${new Date().toLocaleTimeString('fr-FR')}.`;
        } catch (error) {
            status.textContent = `Échec de l'enregistrement : ${error.message}`;
        }
    };

    form.addEventListener('change', (event) => {
        const row = event.target.closest('[data-item]');
        if (row && event.target.matches('[data-count], [data-why]')) save(row);
    });

    // Entrée passe à la ligne suivante, comme sur un tableur : pratique au pavé numérique.
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('[data-count]')) return;
        event.preventDefault();
        const inputs = [...form.querySelectorAll('[data-count]')];
        const next = inputs[inputs.indexOf(event.target) + 1];
        if (next) {
            next.focus();
            next.select();
        } else {
            event.target.blur();
        }
    });
}

export function initInventoryCount() {
    document.querySelectorAll('form[data-inventory-count]').forEach(attach);
}
