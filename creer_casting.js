document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('profiles-container');
    const btnAdd = document.getElementById('btn-add-profile');
    let profileCount = 1;

    function toggleMensurations(selectElement) {
        const card = selectElement.closest('.profile-card');
        const grid = card.querySelector('.mensurations-grid');
        const value = selectElement.value;

        if (['Mannequin', 'Danseur', 'Comédien'].includes(value)) {
            grid.style.display = 'grid';

        } else {
            grid.style.display = 'none';
        }
    }

    const initialSelect = container.querySelector('.role-selector');
    if (initialSelect) {
        initialSelect.addEventListener('change', function (e) {
            toggleMensurations(e.target);
        });
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            profileCount++;

            const firstCard = container.querySelector('.profile-card');
            const newCard = firstCard.cloneNode(true);

            newCard.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type === 'number') {
                    input.value = 1;

                } else if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else {
                    input.value = '';

                }
            });

            newCard.querySelector('.mensurations-grid').style.display = 'none';

            const header = newCard.querySelector('.profile-card-header');
            header.innerHTML = `<h4>Profil #${profileCount}</h4> <button type="button" class="btn-remove-profile">X Supprimer</button>`;

            newCard.querySelector('.role-selector').addEventListener('change', function (e) {
                toggleMensurations(e.target);
            });

            container.appendChild(newCard);
        });
    }

    if (container) {
        container.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-remove-profile')) {

                e.target.closest('.profile-card').remove();

                const cards = container.querySelectorAll('.profile-card');
                cards.forEach((card, index) => {
                    const h4 = card.querySelector('h4');
                    h4.textContent = `Profil #${index + 1}`;
                });

                profileCount = cards.length;
            }
        });
    }

    const paysSelect = document.getElementById('pays-select');
    const villeInput = document.getElementById('ville-input');
    const suggestionsList = document.getElementById('ville-suggestions');

    if (paysSelect && villeInput && suggestionsList) {
        fetch('https://restcountries.com/v3.1/all?fields=name,translations')
            .then(response => response.json())
            .then(data => {
                const countries = data.map(country => {
                    return country.translations && country.translations.fra
                        ? country.translations.fra.common
                        : country.name.common;
                }).sort();

                countries.forEach(countryName => {
                    const option = document.createElement('option');
                    option.value = countryName;
                    option.textContent = countryName;
                    paysSelect.appendChild(option);
                });
            })
            .catch(error => console.error("Erreur lors du chargement des pays:", error));

        let timeoutId;

        villeInput.addEventListener('input', function () {
            clearTimeout(timeoutId);
            const query = this.value;

            if (query.length < 3) {
                suggestionsList.style.display = 'none';
                return;
            }

            timeoutId = setTimeout(() => {
                fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${query}&count=5&language=fr`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsList.innerHTML = '';

                        if (data.results && data.results.length > 0) {
                            data.results.forEach(city => {
                                const li = document.createElement('li');
                                const region = city.admin1 ? `${city.admin1}, ` : '';
                                li.textContent = `${city.name} (${region}${city.country})`;

                                li.style.padding = "10px";
                                li.style.borderBottom = "1px solid #333";
                                li.style.cursor = "pointer";

                                li.addEventListener('click', () => {
                                    villeInput.value = city.name;

                                    const options = Array.from(paysSelect.options);
                                    const countryOption = options.find(opt => opt.value === city.country);
                                    if (countryOption) {
                                        countryOption.selected = true;
                                    }

                                    suggestionsList.style.display = 'none';
                                });

                                li.addEventListener('mouseover', () => li.style.backgroundColor = '#333');
                                li.addEventListener('mouseout', () => li.style.backgroundColor = 'transparent');

                                suggestionsList.appendChild(li);
                            });
                            suggestionsList.style.display = 'block';
                        } else {
                            suggestionsList.style.display = 'none';
                        }
                    })
                    .catch(error => console.error("Erreur Geocoding:", error));
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (e.target !== villeInput) {
                suggestionsList.style.display = 'none';
            }
        });
    }
});