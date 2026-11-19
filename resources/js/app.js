// Point d'entrée JavaScript de StockFlow : JavaScript natif, aucun script en ligne dans les vues.
import { Dropdown, Modal, Toast } from 'bootstrap';

import { initProductSearch } from './modules/product-search';
import { initSaleCart } from './modules/sale-cart';
import { initLineItems, initSingleProduct } from './modules/line-items';
import { initInventoryCount } from './modules/inventory-count';
import { initDashboard } from './modules/dashboard';
import { initLiveList } from './modules/live-list';

// ---------------------------------------------------------------- Barre latérale (tablette et mobile)

function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!sidebar || !toggle) return;

    const setOpen = (open) => {
        sidebar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
    };

    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
    backdrop?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
}

// ---------------------------------------------------------------- Mot de passe : bouton Afficher

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;

        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Masquer' : 'Afficher';
            button.setAttribute('aria-pressed', String(reveal));
        });
    });
}

// ---------------------------------------------------------------- Toasts et bandeaux

function initFeedback() {
    document.querySelectorAll('.toast[data-autoshow]').forEach((element) => new Toast(element).show());

    // Après un refus, le focus va sur le bandeau d'erreur pour qu'il soit lu en premier.
    document.querySelector('[data-autofocus]')?.focus();
}

// ---------------------------------------------------------------- Confirmation avant une action sensible

function initConfirm() {
    const element = document.getElementById('confirmModal');
    if (!element) return;

    const modal = new Modal(element);
    const message = element.querySelector('[data-confirm-message]');
    const title = element.querySelector('.modal-title');
    const accept = element.querySelector('[data-confirm-accept]');
    const reasonGroup = element.querySelector('[data-confirm-reason-group]');
    const reason = element.querySelector('#confirmReason');
    let pending = null;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm]');
        if (!trigger || trigger.dataset.confirmed === '1') return;

        event.preventDefault();
        pending = trigger;
        message.textContent = trigger.dataset.confirm;
        title.textContent = trigger.dataset.confirmTitle || 'Confirmer';
        accept.textContent = trigger.dataset.confirmLabel || 'Confirmer';
        accept.className = 'btn ' + (trigger.dataset.confirmTone === 'primary' ? 'btn-primary' : 'btn-danger');
        reasonGroup.hidden = trigger.dataset.confirmReason !== '1';
        reason.value = '';
        reason.classList.remove('is-invalid');
        modal.show();
    });

    accept.addEventListener('click', () => {
        if (!pending) return;
        const form = pending.form || pending.closest('form');

        if (pending.dataset.confirmReason === '1') {
            if (reason.value.trim().length < 5) {
                reason.classList.add('is-invalid');
                reason.focus();
                return;
            }
            let field = form.querySelector('input[name="reason"]');
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'reason';
                form.appendChild(field);
            }
            field.value = reason.value.trim();
        }

        pending.dataset.confirmed = '1';
        modal.hide();
        if (form) {
            form.requestSubmit(pending.type === 'submit' ? pending : undefined);
        } else {
            pending.click();
        }
    });
}

// ---------------------------------------------------------------- Divers

function initMisc() {
    // Soumission automatique des filtres de liste quand une liste déroulante change.
    document.querySelectorAll('[data-autosubmit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });

    // Un formulaire ne part qu'une fois : le bouton se désactive à l'envoi.
    document.querySelectorAll('form[data-once]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.classList.add('is-loading');
            });
        });
    });

    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    // Menus déroulants de Bootstrap.
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((element) => new Dropdown(element));
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initPasswordToggles();
    initFeedback();
    initConfirm();
    initMisc();
    initProductSearch();
    initLiveList();
    initLineItems();
    initSingleProduct();
    initSaleCart();
    initInventoryCount();
    initDashboard();
});
