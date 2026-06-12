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
  const villeInput = document.getElementById('ville-input');
  const suggestionsList = document.getElementById('ville-suggestions');
  const paysSelect = document.getElementById('pays-select');

  if (!villeInput || !suggestionsList) return;

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
