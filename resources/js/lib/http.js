// Appels Fetch vers les points JSON internes (/internal/...).
// Ils passent par la session et le jeton CSRF, comme un formulaire.

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class HttpError extends Error {
    constructor(status, payload) {
        super(payload?.message || 'Une erreur est survenue. Réessayez.');
        this.status = status;
        this.payload = payload ?? {};
    }
}

async function request(method, url, body, signal) {
    const response = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    // Session expirée : retour à l'écran de connexion.
    if (response.status === 401 || response.status === 419) {
        window.location.reload();
        throw new HttpError(response.status, { message: 'Votre session a expiré.' });
    }

    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new HttpError(response.status, payload);
    }

    return payload;
}

export const getJson = (url, signal) => request('GET', url, null, signal);
export const postJson = (url, body) => request('POST', url, body);
export const putJson = (url, body) => request('PUT', url, body);

/** 1200000 devient « 1 200 000 F CFA ». */
export const formatMoney = (amount) => `${formatNumber(amount)} F CFA`;

export const formatNumber = (value) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value ?? 0);

/** Échappe un texte avant de l'insérer dans du HTML construit en JavaScript. */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
