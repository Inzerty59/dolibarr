document.addEventListener('DOMContentLoaded', function () {
    const items = document.querySelectorAll('.workspace-item');
    const content = document.getElementById('main-content');

    items.forEach(item => {
        item.addEventListener('click', () => {
            const label = item.textContent;
            const id = item.dataset.id;

            content.innerHTML = `<h2>${label}</h2><p>Contenu de l’espace ID: ${id}</p>`;
        });
    });
});
