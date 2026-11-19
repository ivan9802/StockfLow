// Tableau de bord : graphiques, sélection de la période, actualisation sans rechargement.
//
// La page arrive complète depuis le serveur. Ce module :
//  - dessine les graphiques avec Chart.js, chargé à la demande (fichier séparé) ;
//  - interroge /internal/dashboard quand la période change, puis met à jour les cartes,
//    les évolutions et les graphiques ;
//  - relit les données toutes les N secondes tant que la période inclut aujourd'hui.
// Toute la validation de la période est refaite par le serveur.

import { HttpError, escapeHtml, formatMoney, formatNumber, getJson } from '../lib/http';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

// ---------------------------------------------------------------- Formats

const formatPercent = (value) => {
    const abs = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(Math.abs(value));
    return `${value > 0 ? '+' : value < 0 ? '-' : ''}${abs} %`;
};

const deltaHtml = (kpi, money) => {
    if (kpi.previous === null) {
        return '<span class="text-secondary">Sans comparaison</span>';
    }
    const icon = { up: 'arrow-up-right', down: 'arrow-down-right', flat: 'arrow-right' }[kpi.trend];
    const change = kpi.change === null ? (kpi.value > 0 ? 'nouveau' : '0 %') : formatPercent(kpi.change);
    const ref = money ? formatMoney(kpi.previous) : formatNumber(kpi.previous);
    const trendText = { up: 'En hausse', down: 'En baisse', flat: 'Stable' }[kpi.trend];
    const toneText = { good: 'évolution favorable', bad: 'évolution défavorable', neutral: 'évolution neutre' }[kpi.tone];

    return `<span class="dash-delta-badge"><i class="bi bi-${icon}" aria-hidden="true"></i> ${escapeHtml(change)}</span>`
        + `<span class="dash-delta-ref">contre ${escapeHtml(ref)}</span>`
        + `<span class="visually-hidden">${trendText} par rapport à la période précédente, ${toneText}.</span>`;
};

// ---------------------------------------------------------------- Valeurs animées

function animateValue(element, to, money) {
    const from = Number(element.dataset.raw || 0);
    element.dataset.raw = to;
    const render = (v) => {
        element.textContent = money ? formatMoney(Math.round(v)) : formatNumber(Math.round(v));
    };

    if (from === to) {
        render(to);
        return;
    }

    element.classList.remove('value-changed');
    // Relance l'animation de mise en évidence.
    void element.offsetWidth;
    element.classList.add('value-changed');

    if (reducedMotion) {
        render(to);
        return;
    }

    const duration = 650;
    const start = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - (1 - t) ** 3;
        render(from + (to - from) * eased);
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

// ---------------------------------------------------------------- Graphiques

let ChartLib = null;
const charts = {};

async function loadChartJs() {
    if (ChartLib) return ChartLib;

    const lib = await import('chart.js');
    lib.Chart.register(
        lib.LineController, lib.BarController, lib.DoughnutController,
        lib.LineElement, lib.PointElement, lib.BarElement, lib.ArcElement,
        lib.CategoryScale, lib.LinearScale, lib.Tooltip, lib.Legend, lib.Filler,
    );

    const { defaults } = lib.Chart;
    defaults.font.family = '"Public Sans Variable", "Public Sans", system-ui, sans-serif';
    defaults.font.size = 12;
    defaults.color = css('--sf-text-muted') || '#545b57';
    defaults.borderColor = css('--sf-border') || '#d6d1c7';
    defaults.maintainAspectRatio = false;
    defaults.plugins.legend.labels.usePointStyle = true;
    defaults.plugins.legend.labels.boxHeight = 8;
    defaults.plugins.tooltip.backgroundColor = '#1c2b30';
    defaults.plugins.tooltip.padding = 10;
    defaults.plugins.tooltip.cornerRadius = 8;
    defaults.plugins.tooltip.titleFont = { weight: '600' };
    defaults.animation = reducedMotion ? false : { duration: 600, easing: 'easeOutCubic' };

    ChartLib = lib.Chart;
    return ChartLib;
}

const colors = () => ({
    accent: css('--sf-chart-1') || '#0b5c6e',
    second: css('--sf-chart-2') || '#c2672e',
    third: css('--sf-chart-3') || '#3d5368',
    good: '#256b3c',
    warn: '#9a6700',
    bad: '#a32a1f',
    grid: 'rgba(30, 36, 34, 0.07)',
});

const moneyAxis = (v) => {
    if (v >= 1_000_000) return `${formatNumber(v / 1_000_000)} M`;
    if (v >= 1_000) return `${formatNumber(v / 1_000)} k`;
    return formatNumber(v);
};

function setEmpty(id, empty) {
    const box = document.querySelector(`[data-chart-empty="${id}"]`);
    if (box) box.hidden = !empty;
}

/** Crée le graphique, ou met à jour ses données s'il existe déjà (transition animée). */
function upsert(Chart, id, build) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const config = build();

    if (charts[id]) {
        charts[id].data = config.data;
        charts[id].options = config.options;
        charts[id].update();
    } else {
        charts[id] = new Chart(canvas, config);
    }
}

function renderCharts(Chart, data) {
    const c = colors();
    const series = data.charts;

    if (series.movements) {
        const rows = series.movements;
        setEmpty('chart-movements', rows.every((r) => r.in === 0 && r.out === 0));
        upsert(Chart, 'chart-movements', () => ({
            type: 'line',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [
                    { label: 'Entrées', data: rows.map((r) => r.in), borderColor: c.accent, backgroundColor: 'rgba(11, 92, 110, 0.10)', fill: true, tension: 0.3, pointRadius: rows.length > 31 ? 0 : 2, borderWidth: 2 },
                    { label: 'Sorties', data: rows.map((r) => r.out), borderColor: c.second, backgroundColor: 'rgba(194, 103, 46, 0.08)', fill: true, tension: 0.3, pointRadius: rows.length > 31 ? 0 : 2, borderWidth: 2 },
                ],
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: c.grid }, ticks: { precision: 0, callback: (v) => formatNumber(v) } },
                },
                plugins: { tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label} : ${formatNumber(ctx.parsed.y)} unités` } } },
            },
        }));
    }

    if (series.sales) {
        const rows = series.sales;
        setEmpty('chart-sales', rows.every((r) => r.count === 0));
        upsert(Chart, 'chart-sales', () => ({
            type: 'bar',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{
                    label: 'Chiffre d\'affaires', data: rows.map((r) => r.revenue),
                    backgroundColor: c.accent, hoverBackgroundColor: '#084a59', borderRadius: 4, maxBarThickness: 36,
                }],
            },
            options: {
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: c.grid }, ticks: { callback: moneyAxis } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${formatMoney(ctx.parsed.y)}`,
                            afterLabel: (ctx) => {
                                const n = rows[ctx.dataIndex].count;
                                return ` ${formatNumber(n)} vente${n > 1 ? 's' : ''}`;
                            },
                        },
                    },
                },
            },
        }));
    }

    const top = data.top_products;
    setEmpty('chart-top', top.length === 0);
    upsert(Chart, 'chart-top', () => ({
        type: 'bar',
        data: {
            labels: top.map((p) => (p.name.length > 34 ? `${p.name.slice(0, 33)}…` : p.name)),
            datasets: [{ label: 'Unités vendues', data: top.map((p) => p.units), backgroundColor: c.accent, borderRadius: 4, maxBarThickness: 22 }],
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: { beginAtZero: true, grid: { color: c.grid }, ticks: { precision: 0 } },
                y: { grid: { display: false } },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => top[items[0].dataIndex].name,
                        label: (ctx) => ` ${formatNumber(ctx.parsed.x)} unités vendues`,
                        afterLabel: (ctx) => ` ${formatMoney(top[ctx.dataIndex].revenue)}`,
                    },
                },
            },
            onClick: (_event, elements) => {
                if (elements.length) window.location.href = top[elements[0].index].url;
            },
        },
    }));

    const doughnut = (id, rows, palette, unit) => {
        setEmpty(id, rows.every((r) => r.value === 0));
        upsert(Chart, id, () => ({
            type: 'doughnut',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{ data: rows.map((r) => r.value), backgroundColor: palette, borderColor: '#fbfaf7', borderWidth: 3, hoverOffset: 6 }],
            },
            options: {
                cutout: '64%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                return ` ${formatNumber(ctx.parsed)} ${unit} (${Math.round((ctx.parsed * 100) / total)} %)`;
                            },
                        },
                    },
                },
            },
        }));
    };

    if (series.warehouses) {
        doughnut('chart-warehouses', series.warehouses.map((w) => ({ label: w.name, value: w.units })), [c.accent, c.second, c.third], 'unités');
    }
    if (series.stock_status) {
        doughnut('chart-status', series.stock_status.map((s) => ({ label: s.label, value: s.count })), [c.good, c.warn, c.bad], 'produits');
    }
}

// ---------------------------------------------------------------- Application des données

function applyData(root, data) {
    const values = { ...data.snapshot };
    Object.entries(data.kpis).forEach(([key, kpi]) => {
        values[key] = kpi.value;
    });

    Object.entries(values).forEach(([key, value]) => {
        const card = root.querySelector(`[data-kpi-card="${key}"]`);
        if (!card) return;
        const money = card.hasAttribute('data-money');
        animateValue(card.querySelector('[data-kpi]'), value, money);

        const delta = card.querySelector('[data-delta]');
        const kpi = data.kpis[key];
        if (delta && kpi) {
            delta.className = `dash-delta is-${kpi.tone}`;
            delta.innerHTML = deltaHtml(kpi, money);
        }
    });

    document.querySelector('[data-period-label]').textContent = data.period.label;
    const previous = document.querySelector('[data-previous-label]');
    if (previous) {
        previous.hidden = !data.previous;
        if (data.previous) previous.textContent = `· comparé ${data.previous.label.charAt(0).toLowerCase()}${data.previous.label.slice(1)}`;
    }

    const salesLink = document.querySelector('[data-period-link="sales"]');
    if (salesLink) {
        const url = new URL(salesLink.href);
        url.searchParams.set('from', data.period.from);
        url.searchParams.set('to', data.period.to);
        salesLink.href = url.toString();
    }

    // Repli texte des produits les plus vendus (lecteurs d'écran, absence de JavaScript).
    const topList = document.querySelector('[data-top-list]');
    if (topList) {
        topList.innerHTML = data.top_products.length
            ? data.top_products.map((p) => `<li><a href="${escapeHtml(p.url)}">${escapeHtml(p.name)}</a> <span class="tabular">${formatNumber(p.units)} u. · ${formatMoney(p.revenue)}</span></li>`).join('')
            : '<li class="text-secondary">Aucune vente sur cette période.</li>';
    }

    const badge = document.querySelector('[data-alert-count]');
    if (badge && typeof data.alerts === 'number') badge.textContent = data.alerts > 99 ? '99+' : data.alerts;
}

// ---------------------------------------------------------------- Formulaire de période

function initPeriodForm(root, onData) {
    const form = document.querySelector('[data-period-form]');
    if (!form) return;

    const select = form.querySelector('[data-period-select]');
    const custom = form.querySelector('[data-period-custom]');
    const from = form.querySelector('#from');
    const to = form.querySelector('#to');
    const apply = form.querySelector('[data-period-apply]');
    const error = form.querySelector('[data-period-error]');
    let controller = null;
    let current = null;

    const showError = (message) => {
        error.textContent = message;
        error.hidden = !message;
    };

    const query = () => {
        const params = new URLSearchParams();
        params.set('period', select.value);
        if (select.value === 'custom') {
            params.set('from', from.value);
            params.set('to', to.value);
        }
        params.set('compare', form.querySelector('#compare').checked ? '1' : '0');
        return params;
    };

    const load = async ({ silent = false } = {}) => {
        // Contrôle de confort ; le serveur refait toute la validation.
        if (select.value === 'custom') {
            if (!from.value || !to.value) {
                showError('Indiquez une date de début et une date de fin.');
                return;
            }
            if (from.value > to.value) {
                showError('La date de début doit précéder la date de fin.');
                return;
            }
        }
        showError('');

        controller?.abort();
        controller = new AbortController();
        const params = query();

        if (!silent) {
            root.setAttribute('aria-busy', 'true');
            apply.classList.add('is-loading');
        }

        try {
            const data = await getJson(`${form.dataset.endpoint}?${params}`, controller.signal);
            current = data;
            await onData(data);
            if (!silent) window.history.replaceState({}, '', `${form.action}?${params}`);
        } catch (e) {
            if (e.name === 'AbortError') return;
            if (e instanceof HttpError && e.payload?.errors) {
                showError(Object.values(e.payload.errors).flat()[0]);
            } else if (!silent) {
                showError('Les indicateurs n\'ont pas pu être actualisés. Vérifiez la connexion et réessayez.');
            }
        } finally {
            if (!silent) {
                root.removeAttribute('aria-busy');
                apply.classList.remove('is-loading');
            }
        }
    };

    select.addEventListener('change', () => {
        custom.hidden = select.value !== 'custom';
        if (select.value === 'custom') {
            from.focus();
        } else {
            load();
        }
    });
    form.querySelector('#compare').addEventListener('change', () => load());
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load();
    });
    form.querySelector('[data-period-reset]').addEventListener('click', (event) => {
        event.preventDefault();
        select.value = 'last_30_days';
        custom.hidden = true;
        form.querySelector('#compare').checked = true;
        load();
    });

    // Rafraîchissement automatique, seulement si la période inclut aujourd'hui.
    const seconds = Math.max(15, Number(form.dataset.refresh) || 30);
    setInterval(() => {
        const today = new Date().toISOString().slice(0, 10);
        const to = current?.period.to ?? JSON.parse(document.getElementById('dashboard-data').textContent).period.to;
        if (!document.hidden && to >= today) load({ silent: true });
    }, seconds * 1000);
}

export async function initDashboard() {
    const root = document.querySelector('[data-dashboard]');
    const source = document.getElementById('dashboard-data');
    if (!root || !source) return;

    const initial = JSON.parse(source.textContent);
    let Chart = null;

    try {
        Chart = await loadChartJs();
        // Les conteneurs sont rendus visibles avant le dessin, pour que Chart.js en mesure la taille.
        root.setAttribute('data-charts-ready', '');
        renderCharts(Chart, initial);
    } catch {
        // Sans graphiques, les replis texte restent affichés : rien n'est perdu.
        root.removeAttribute('data-charts-ready');
        Chart = null;
    }

    initPeriodForm(root, async (data) => {
        applyData(root, data);
        if (Chart) renderCharts(Chart, data);
    });
}
