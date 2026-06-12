document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('profiles-container');
    const btnAdd = document.getElementById('btn-add-profile');
    let profileCount = 1;

    const MENSURATION_ROLES = window.CHICBOOK_TALENT_PROFESSIONS || ['Mannequin', 'Danseur', 'Comédien'];

    function toggleMensurations(selectElement) {
        const card = selectElement.closest('.profile-card');
        const grid = card.querySelector('.mensurations-grid');
        if (MENSURATION_ROLES.includes(selectElement.value)) {
            grid.style.display = 'block';
        } else {
            grid.style.display = 'none';
        }
        updatePreview();
    }

    // Init listener on first card
    const initialSelect = container.querySelector('.role-selector');
    if (initialSelect) {
        initialSelect.addEventListener('change', function (e) {
            toggleMensurations(e.target);
        });
    }

    // Add profile card
    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            profileCount++;
            const firstCard = container.querySelector('.profile-card');
            const newCard = firstCard.cloneNode(true);

            newCard.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type === 'number') input.value = input.name.includes('quantities') ? 1 : '';
                else if (input.type === 'checkbox' || input.type === 'radio') input.checked = false;
                else input.value = '';
            });

            newCard.querySelector('.mensurations-grid').style.display = 'none';
            const hdr = newCard.querySelector('.profile-card-header');
            hdr.innerHTML = `<h4 class="text-[#d4a5d4] text-sm font-bold m-0">Profil #${profileCount}</h4>
                <button type="button" class="btn-remove-profile text-[#888] text-xs hover:text-red-400 transition-colors">✕ Supprimer</button>`;

            newCard.querySelector('.role-selector').addEventListener('change', function (e) {
                toggleMensurations(e.target);
            });

            container.appendChild(newCard);
        });
    }

    // Remove profile card
    if (container) {
        container.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-remove-profile')) {
                e.target.closest('.profile-card').remove();
                const cards = container.querySelectorAll('.profile-card');
                cards.forEach((card, index) => {
                    card.querySelector('h4').textContent = `Profil #${index + 1}`;
                });
                profileCount = cards.length;
            }
        });
    }

    // ===== LIVE PREVIEW =====
    function updatePreview() {
        const role = document.querySelector('.role-selector')?.value || '...';
        const city = document.getElementById('ville-input')?.value || '...';
        const desc = document.querySelector('[name="description"]')?.value || 'La description apparaîtra ici...';
        const company = document.getElementById('form_company')?.value;
        const dateVal = document.getElementById('form_date')?.value;

        const titleEl = document.getElementById('preview-title');
        const companyEl = document.getElementById('preview-company');
        const descEl = document.getElementById('preview-desc');
        const dateEl = document.getElementById('preview-date');

        if (titleEl) titleEl.textContent = `Recherche ${role} - ${city}`;
        if (companyEl) companyEl.textContent = company || 'Nom / Entreprise';
        if (descEl) descEl.textContent = desc.length > 120 ? desc.substring(0, 120) + '...' : desc;
        if (dateEl && dateVal) {
            const d = new Date(dateVal + 'T00:00:00');
            dateEl.textContent = `Prestation le : ${d.toLocaleDateString('fr-FR')}`;
        } else if (dateEl) {
            dateEl.textContent = 'Prestation le : --/--/----';
        }
    }

    // Preview image
    const imageInput = document.getElementById('casting_image_input');
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const file = this.files[0];
            const wrap = document.getElementById('preview-img-wrap');
            if (!wrap) return;
            if (file) {
                const reader = new FileReader();
                reader.onload = e => {
                    wrap.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                };
                reader.readAsDataURL(file);
            } else {
                wrap.innerHTML = '<span>Image d\'illustration</span>';
            }
        });
    }

    // Listen to all form fields for live preview
    const previewTriggers = ['description', 'company_name', 'performance_date'];
    previewTriggers.forEach(name => {
        const el = document.querySelector(`[name="${name}"]`) || document.getElementById('form_' + name);
        if (el) el.addEventListener('input', updatePreview);
        if (el) el.addEventListener('change', updatePreview);
    });
    const villeInput = document.getElementById('ville-input');
    if (villeInput) villeInput.addEventListener('input', updatePreview);
    if (container) container.addEventListener('change', updatePreview);

    updatePreview();

    // ===== CITY/COUNTRY AUTOCOMPLETE =====
    const paysSelect = document.getElementById('pays-select');
    const suggestionsList = document.getElementById('ville-suggestions');

    if (paysSelect && villeInput && suggestionsList) {
        fetch('https://restcountries.com/v3.1/all?fields=name,translations')
            .then(r => r.json())
            .then(data => {
                const countries = data.map(c =>
                    c.translations?.fra ? c.translations.fra.common : c.name.common
                ).sort();
                countries.forEach(name => {
                    const opt = document.createElement('option');
                    opt.value = name; opt.textContent = name;
                    paysSelect.appendChild(opt);
                });
            })
            .catch(e => console.error("Pays:", e));

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
                                li.style.cssText = 'padding:10px;border-bottom:1px solid #333;cursor:pointer;font-size:13px;';
                                li.addEventListener('click', () => {
                                    villeInput.value = city.name;
                                    const opt = Array.from(paysSelect.options).find(o => o.value === city.country);
                                    if (opt) opt.selected = true;
                                    suggestionsList.style.display = 'none';
                                    updatePreview();
                                });
                                li.addEventListener('mouseover', () => li.style.backgroundColor = '#333');
                                li.addEventListener('mouseout', () => li.style.backgroundColor = 'transparent');
                                suggestionsList.appendChild(li);
                            });
                            suggestionsList.style.display = 'block';
                        } else {
                            suggestionsList.style.display = 'none';
                        }
                    });
            }, 300);
        });

        document.addEventListener('click', e => {
            if (e.target !== villeInput) suggestionsList.style.display = 'none';
        });
    }
});
