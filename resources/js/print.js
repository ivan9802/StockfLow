// Pages imprimables : bouton « Imprimer », et impression automatique avec ?auto=1
// (utilisé juste après l'enregistrement d'une vente au point de vente).
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    if (new URLSearchParams(window.location.search).get('auto') === '1') {
        window.print();
    }
});
