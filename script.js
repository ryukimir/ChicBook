const header = document.getElementById("main-header");
const navicon = document.getElementById("navicon");
const navCenter = document.querySelector(".nav-center");
let lastScrollY = window.scrollY;

window.addEventListener("scroll", () => {
  if (lastScrollY < window.scrollY && window.scrollY > 80) {
    header.classList.add("nav-hidden");
  } else {
    header.classList.remove("nav-hidden");
  }
  lastScrollY = window.scrollY;
});

navicon.addEventListener("click", () => {
  navCenter.classList.toggle("mobile-active");
});

const interactiveElements = document.querySelectorAll(
  ".tag, .btn-post, .btn-auth, .btn-small, .btn-follow, .btn-contact",
);

interactiveElements.forEach((item) => {
  item.addEventListener("click", (e) => {
    console.log("Action");
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const metierSelect = document.getElementById('metier-select');
  const mensurationsBloc = document.getElementById('mensurations-bloc');
  const hasMeasurementsInput = document.getElementById('has_measurements');

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

              li.addEventListener('click', () => {

                villeInput.value = city.name;

                const options = Array.from(paysSelect.options);
                const countryOption = options.find(opt => opt.value === city.country);
                if (countryOption) {
                  countryOption.selected = true;
                }

                suggestionsList.style.display = 'none';

              });

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
});

function showTags(id) {
  document.querySelectorAll('.tags-grid').forEach(el => el.style.display = 'none');
  if (document.getElementById(id)) {
    document.getElementById(id).style.display = 'grid';
  }
}

window.onload = function () {
  const checkedRadio = document.querySelector('input[name="profession"]:checked');
  if (checkedRadio) { checkedRadio.click(); }
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

      setTimeout(() => {
        isScrolling = false;
      }, 350);
    });
  }

  initInfiniteCarousel('talent-track', 'btn-prev', 'btn-next');

  initInfiniteCarousel('image-track', 'btn-prev-image', 'btn-next-image');

});