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