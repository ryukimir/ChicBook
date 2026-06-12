const interactiveElements = document.querySelectorAll(
  ".tag, .btn-post, .btn-auth, .btn-small, .btn-follow, .btn-contact",
);
interactiveElements.forEach((item) => {
  item.addEventListener("click", () => console.log("Action"));
});

document.addEventListener('DOMContentLoaded', function () {
  const metierSelect = document.getElementById('metier-select');
  const mensurationsBloc = document.getElementById('mensurations-bloc');
  const hasMeasurementsInput = document.getElementById('has_measurements');

  if (!metierSelect) return;

  function toggleMeasurements() {
    const selectedOption = metierSelect.options[metierSelect.selectedIndex];
    if (selectedOption && selectedOption.dataset.measurements === 'true') {
      mensurationsBloc.style.display = 'block';
      hasMeasurementsInput.value = "1";
    } else {
      mensurationsBloc.style.display = 'none';
      hasMeasurementsInput.value = "0";
    }
  }

  metierSelect.addEventListener('change', toggleMeasurements);
  toggleMeasurements();
});

document.addEventListener('DOMContentLoaded', function () {
  const paysSelect = document.getElementById('pays-select');
  const villeInput = document.getElementById('ville-input');
  const suggestionsList = document.getElementById('ville-suggestions');

  if (!paysSelect || !villeInput || !suggestionsList) return;

  const PAYS = ["Afghanistan","Afrique du Sud","Albanie","Algérie","Allemagne","Andorre","Angola","Antigua-et-Barbuda","Arabie saoudite","Argentine","Arménie","Australie","Autriche","Azerbaïdjan","Bahamas","Bahreïn","Bangladesh","Barbade","Belgique","Belize","Bénin","Bhoutan","Biélorussie","Birmanie","Bolivie","Bosnie-Herzégovine","Botswana","Brésil","Brunei","Bulgarie","Burkina Faso","Burundi","Cambodge","Cameroun","Canada","Cap-Vert","Chili","Chine","Chypre","Colombie","Comores","Congo","Corée du Nord","Corée du Sud","Costa Rica","Côte d'Ivoire","Croatie","Cuba","Danemark","Djibouti","Dominique","Égypte","Émirats arabes unis","Équateur","Érythrée","Espagne","Eswatini","Estonie","Éthiopie","Fidji","Finlande","France","Gabon","Gambie","Géorgie","Ghana","Grèce","Grenade","Guatemala","Guinée","Guinée-Bissau","Guinée équatoriale","Guyana","Haïti","Honduras","Hongrie","Inde","Indonésie","Irak","Iran","Irlande","Islande","Israël","Italie","Jamaïque","Japon","Jordanie","Kazakhstan","Kenya","Kirghizistan","Kiribati","Koweït","Laos","Lesotho","Lettonie","Liban","Liberia","Libye","Liechtenstein","Lituanie","Luxembourg","Macédoine du Nord","Madagascar","Malaisie","Malawi","Maldives","Mali","Malte","Maroc","Marshall","Maurice","Mauritanie","Mexique","Micronésie","Moldavie","Monaco","Mongolie","Monténégro","Mozambique","Namibie","Nauru","Népal","Nicaragua","Niger","Nigeria","Norvège","Nouvelle-Zélande","Oman","Ouganda","Ouzbékistan","Pakistan","Palaos","Palestine","Panama","Papouasie-Nouvelle-Guinée","Paraguay","Pays-Bas","Pérou","Philippines","Pologne","Portugal","Qatar","République centrafricaine","République démocratique du Congo","République dominicaine","République tchèque","Roumanie","Royaume-Uni","Russie","Rwanda","Saint-Kitts-et-Nevis","Saint-Marin","Saint-Vincent-et-les-Grenadines","Sainte-Lucie","Salvador","Samoa","São Tomé-et-Príncipe","Sénégal","Serbie","Seychelles","Sierra Leone","Singapour","Slovaquie","Slovénie","Somalie","Soudan","Soudan du Sud","Sri Lanka","Suède","Suisse","Suriname","Syrie","Tadjikistan","Tanzanie","Tchad","Thaïlande","Timor oriental","Togo","Tonga","Trinité-et-Tobago","Tunisie","Turkménistan","Turquie","Tuvalu","Ukraine","Uruguay","Vanuatu","Vatican","Venezuela","Vietnam","Yémen","Zambie","Zimbabwe"];
  const saved = paysSelect.dataset.saved || '';
  PAYS.forEach(name => {
    const opt = document.createElement('option');
    opt.value = name; opt.textContent = name;
    if (name === saved) opt.selected = true;
    paysSelect.appendChild(opt);
  });

  let timeoutId;
  villeInput.addEventListener('input', function () {
    clearTimeout(timeoutId);
    const query = this.value;
    if (query.length < 3) { suggestionsList.style.display = 'none'; return; }
    timeoutId = setTimeout(() => {
      fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5&language=fr`)
        .then(r => r.json())
        .then(data => {
          suggestionsList.innerHTML = '';
          if (data.results?.length > 0) {
            data.results.forEach(city => {
              const li = document.createElement('li');
              li.textContent = `${city.name} (${city.admin1 ? city.admin1 + ', ' : ''}${city.country})`;
              li.addEventListener('click', () => {
                villeInput.value = city.name;
                const opt = Array.from(paysSelect.options).find(o => o.value === city.country);
                if (opt) opt.selected = true;
                suggestionsList.style.display = 'none';
              });
              suggestionsList.appendChild(li);
            });
            suggestionsList.style.display = 'block';
          } else {
            suggestionsList.style.display = 'none';
          }
        })
        .catch(e => console.error("Geocoding:", e));
    }, 300);
  });

  document.addEventListener('click', e => {
    if (e.target !== villeInput) suggestionsList.style.display = 'none';
  });
});

function showTags(id) {
  document.querySelectorAll('.tags-grid').forEach(el => el.style.display = 'none');
  if (document.getElementById(id)) document.getElementById(id).style.display = 'grid';
}

window.onload = function () {
  const checkedRadio = document.querySelector('input[name="profession"]:checked');
  if (checkedRadio) checkedRadio.click();
}

document.addEventListener("DOMContentLoaded", function () {
  function initInfiniteCarousel(trackId, btnPrevId, btnNextId) {
    const track = document.getElementById(trackId);
    const btnPrev = document.getElementById(btnPrevId);
    const btnNext = document.getElementById(btnNextId);
    if (!track || !btnPrev || !btnNext) return;
    const scrollAmount = 340;
    let isScrolling = false;

    btnNext.addEventListener('click', () => {
      if (isScrolling) return;
      isScrolling = true;
      track.style.scrollBehavior = 'smooth';
      track.scrollBy({ left: scrollAmount });
      setTimeout(() => {
        track.style.scrollBehavior = 'auto';
        track.appendChild(track.firstElementChild);
        track.scrollLeft -= scrollAmount;
        isScrolling = false;
      }, 350);
    });

    btnPrev.addEventListener('click', () => {
      if (isScrolling) return;
      isScrolling = true;
      track.style.scrollBehavior = 'auto';
      track.prepend(track.lastElementChild);
      track.scrollLeft += scrollAmount;
      setTimeout(() => {
        track.style.scrollBehavior = 'smooth';
        track.scrollBy({ left: -scrollAmount });
      }, 10);
      setTimeout(() => { isScrolling = false; }, 350);
    });
  }

  initInfiniteCarousel('talent-track', 'btn-prev', 'btn-next');
  initInfiniteCarousel('image-track', 'btn-prev-image', 'btn-next-image');
});
