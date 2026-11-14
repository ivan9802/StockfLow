// Tableau de bord : les indicateurs sont relus à intervalle régulier par Fetch,
// sans recharger la page. Le rafraîchissement s'arrête quand l'onglet est masqué.

import { getJson } from '../lib/http';

export function initDashboard() {
    const marker = document.querySelector('[data-dashboard-refresh]');
    if (!marker) return;

    const url = marker.dataset.dashboardRefresh;
    const interval = Math.max(10, Number(marker.dataset.interval) || 30) * 1000;
    const updatedAt = marker.querySelector('[data-updated-at]');
    let timer = null;

    const refresh = async () => {
        try {
            const payload = await getJson(url);
            Object.entries(payload.formatted).forEach(([key, value]) => {
                document.querySelectorAll(`[data-kpi="${key}"]`).forEach((el) => {
                    el.textContent = key === 'pending_orders' ? payload.kpis[key] : value;
                });
            });
            const badge = document.querySelector('[data-alert-count]');
            if (badge) badge.textContent = payload.alerts > 99 ? '99+' : payload.alerts;
            updatedAt.textContent = payload.updated_at;
        } catch {
            updatedAt.textContent = `${updatedAt.textContent} (actualisation impossible)`;
        }
    };

    const start = () => {
        timer ??= setInterval(refresh, interval);
    };
    const stop = () => {
        clearInterval(timer);
        timer = null;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            refresh();
            start();
        }
    });

    start();
}
