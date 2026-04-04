/* ── Role switching ── */
function handleRoleChange() {
    const selected = document.querySelector('input[name="role"]:checked')?.value;
    const driverSection = document.getElementById('driverSection');
    const passengerSection = document.getElementById('passengerSection');
    const badgePassenger = document.getElementById('badgePassenger');
    const badgeDriver = document.getElementById('badgeDriver');

    if (!driverSection || !passengerSection || !badgePassenger || !badgeDriver) {
        return;
    }

    const isDriver = selected === 'driver' || selected === 'both';
    const isPassenger = selected === 'passenger' || selected === 'both';

    driverSection.style.display = isDriver ? 'block' : 'none';
    passengerSection.style.display = (!isDriver && isPassenger) ? 'block' : 'none';

    badgePassenger.style.display = isPassenger ? 'inline-flex' : 'none';
    badgeDriver.style.display = isDriver ? 'inline-flex' : 'none';
}

function setupRoleSelection() {
    const roleRadios = document.querySelectorAll('input[name="role"]');
    roleRadios.forEach(radio => {
        radio.addEventListener('change', handleRoleChange);
    });

    handleRoleChange();
}

/* ── Preferences helpers ── */
function getDefaultPreferences() {
    return {
        smoking: 0,
        animals: 0,
        custom: []
    };
}

function getPreferencesInput(vehicleBlock) {
    return vehicleBlock.querySelector('input[name$="[preferences]"]');
}

function parsePreferencesValue(value) {
    if (!value || typeof value !== 'string') {
        return null;
    }

    try {
        const parsed = JSON.parse(value);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
        return null;
    }
}

function vehicleHasSavedPreferences(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return false;
    }

    const prefs = parsePreferencesValue(input.value);
    if (!prefs) {
        return false;
    }

    return (
        prefs.smoking !== undefined ||
        prefs.animals !== undefined ||
        (Array.isArray(prefs.custom) && prefs.custom.length > 0)
    );
}

function setPreferenceByName(vehicleBlock, prefName, prefValue) {
    const toggle = vehicleBlock.querySelector(`.pref-toggle[data-pref-name="${prefName}"]`);
    if (!toggle) {
        return;
    }

    toggle.querySelectorAll('.pref-btn').forEach(btn => btn.classList.remove('active'));

    const targetBtn = toggle.querySelector(`.pref-btn[data-pref-value="${prefValue}"]`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }
}

function collectVehiclePreferences(vehicleBlock) {
    const prefs = getDefaultPreferences();

    vehicleBlock.querySelectorAll('.pref-toggle').forEach(toggle => {
        const prefName = toggle.dataset.prefName;
        const activeBtn = toggle.querySelector('.pref-btn.active');

        if (!prefName || !activeBtn) {
            return;
        }

        prefs[prefName] = Number(activeBtn.dataset.prefValue);
    });

    return prefs;
}

function updateVehiclePreferencesJson(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return;
    }

    const prefs = collectVehiclePreferences(vehicleBlock);
    input.value = JSON.stringify(prefs);
}

function syncVehicleUIFromHiddenInput(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return;
    }

    const prefs = parsePreferencesValue(input.value) || getDefaultPreferences();

    setPreferenceByName(vehicleBlock, 'smoking', Number(prefs.smoking ?? 0));
    setPreferenceByName(vehicleBlock, 'animals', Number(prefs.animals ?? 0));

    input.value = JSON.stringify({
        smoking: Number(prefs.smoking ?? 0),
        animals: Number(prefs.animals ?? 0),
        custom: Array.isArray(prefs.custom) ? prefs.custom : []
    });
}

function initializeVehiclePreferences(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return;
    }

    if (vehicleHasSavedPreferences(vehicleBlock)) {
        syncVehicleUIFromHiddenInput(vehicleBlock);
        return;
    }

    setPreferenceByName(vehicleBlock, 'smoking', 0);
    setPreferenceByName(vehicleBlock, 'animals', 0);
    updateVehiclePreferencesJson(vehicleBlock);
}

function initializeAllVehiclePreferences() {
    document.querySelectorAll('.vehicle-block').forEach(vehicleBlock => {
        initializeVehiclePreferences(vehicleBlock);
    });
}

/* ── Add vehicle from CollectionType ── */
function addVehicleBlock() {
    const vehicleList = document.getElementById('vehicleList');
    if (!vehicleList) return;

    const vehicleBlocks = vehicleList.querySelectorAll('.vehicle-block');
    const newIndex = vehicleBlocks.length;
    const formName = 'profile_form';
    const brands = getBrandOptions();

    const brandOptions = brands.map(brand => `<option value="${brand}">${brand}</option>`).join('');

    const newBlock = document.createElement('div');
    newBlock.className = 'vehicle-block mb-4';
    newBlock.setAttribute('data-index', newIndex);
    newBlock.style.border = '1px solid rgba(255,255,255,.07)';
    newBlock.style.borderRadius = 'var(--radius-sm)';
    newBlock.style.padding = '1.25rem';
    newBlock.style.background = 'rgba(15,17,23,.5)';

    newBlock.innerHTML = `
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_immatriculation">Plaque d'immatriculation</label>
                <input type="text" id="${formName}_voitures_${newIndex}_immatriculation" name="${formName}[voitures][${newIndex}][immatriculation]" class="form-control contact-input" placeholder="AB-123-CD" required>
            </div>

            <div class="col-12 col-sm-6">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_date_premiere_immatriculation">1ère mise en circulation</label>
                <input type="date" id="${formName}_voitures_${newIndex}_date_premiere_immatriculation" name="${formName}[voitures][${newIndex}][date_premiere_immatriculation]" class="form-control contact-input" style="color-scheme:dark;">
            </div>

            <div class="col-12 col-sm-4">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_marque">Marque</label>
                <select id="${formName}_voitures_${newIndex}_marque" name="${formName}[voitures][${newIndex}][marque]" class="form-select contact-input" required>
                    <option value="">Choisir une marque</option>
                    ${brandOptions}
                </select>
            </div>

            <div class="col-12 col-sm-4">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_modele">Modèle</label>
                <input type="text" id="${formName}_voitures_${newIndex}_modele" name="${formName}[voitures][${newIndex}][modele]" class="form-control contact-input" placeholder="Zoé" required>
            </div>

            <div class="col-12 col-sm-4">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_couleur">Couleur</label>
                <input type="text" id="${formName}_voitures_${newIndex}_couleur" name="${formName}[voitures][${newIndex}][couleur]" class="form-control contact-input" placeholder="Blanche">
            </div>

            <div class="col-12 col-sm-4">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_energie">Type d'énergie</label>
                <select id="${formName}_voitures_${newIndex}_energie" name="${formName}[voitures][${newIndex}][energie]" class="form-select contact-input" required>
                    <option value="">Choisir</option>
                    <option value="Essence">Essence</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Électrique">Électrique</option>
                    <option value="Hybride">Hybride</option>
                    <option value="Hybride rechargeable">Hybride rechargeable</option>
                    <option value="GNV">GNV</option>
                </select>
            </div>

            <div class="col-12 col-sm-6">
                <label class="contact-label" for="${formName}_voitures_${newIndex}_places">Places disponibles</label>
                <select id="${formName}_voitures_${newIndex}_places" name="${formName}[voitures][${newIndex}][places]" class="form-select contact-input">
                    <option value="">Choisir</option>
                    <option value="1">1 place</option>
                    <option value="2">2 places</option>
                    <option value="3">3 places</option>
                    <option value="4">4 places</option>
                    <option value="5">5 places</option>
                    <option value="6">6 places</option>
                    <option value="7">7 places</option>
                </select>
            </div>
        </div>

        <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.07);">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <p class="contact-label mb-0" style="font-size:.78rem;">Préférences à bord</p>
                <span style="font-size:.7rem;color:var(--text-muted);">Configurables</span>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-12 col-sm-6">
                    <div class="pref-toggle" data-pref-name="smoking">
                        <button type="button" class="pref-btn active" data-pref-value="0">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="1" y1="1" x2="23" y2="23"/><path d="M20.84 14.12a4 4 0 0 0-4.99-4.12"/>
                            </svg>
                            Pas de tabac
                        </button>
                        <button type="button" class="pref-btn" data-pref-value="1">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 12h2a2 2 0 0 1 2 2v0a2 2 0 0 1-2 2h-2"/><path d="M2 16h16"/><path d="M22 8c0-2.2-1.8-4-4-4-1.4 0-2.6.7-3.4 1.8"/>
                            </svg>
                            Fumeurs OK
                        </button>
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <div class="pref-toggle" data-pref-name="animals">
                        <button type="button" class="pref-btn active" data-pref-value="0">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                            Pas d'animaux
                        </button>
                        <button type="button" class="pref-btn" data-pref-value="1">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 5.172C10 3.374 8.24 2 6 2c-2.24 0-4 1.374-4 3.172 0 .356.077.7.215 1.018M14 5.172C14 3.374 15.76 2 18 2c2.24 0 4 1.374 4 3.172 0 .356-.077.7-.215 1.018M6 10c-2 0-4 1.5-4 4s2 4 4 4h12c2 0 4-1.5 4-4s-2-4-4-4H6z"/>
                            </svg>
                            Animaux OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden"
               name="${formName}[voitures][${newIndex}][preferences]"
               data-preferences-hidden
               value='{"smoking":0,"animals":0,"custom":[]}'>
               
        <button type="button" class="btn btn-sm btn-outline-danger mt-3" onclick="this.closest('.vehicle-block').remove()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Supprimer ce véhicule
        </button>
    `;

    vehicleList.appendChild(newBlock);
    initializeVehiclePreferences(newBlock);
}

/* ── Preference toggles ── */
function setupPreferenceToggles() {
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.pref-btn');
        if (!btn) {
            return;
        }

        const vehicleBlock = btn.closest('.vehicle-block');
        const toggle = btn.closest('.pref-toggle');

        if (!vehicleBlock || !toggle) {
            return;
        }

        toggle.querySelectorAll('.pref-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        updateVehiclePreferencesJson(vehicleBlock);
    });
}

/* ── Form submission ── */
function setupFormSubmission() {
    const form = document.querySelector('form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function () {
        document.querySelectorAll('.vehicle-block').forEach(vehicleBlock => {
            updateVehiclePreferencesJson(vehicleBlock);
        });
    });
}

/* ── Initialize on DOM ready ── */
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('addVehicleBtn');

    if (addBtn) {
        const newBtn = addBtn.cloneNode(true);
        addBtn.parentNode.replaceChild(newBtn, addBtn);

        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            addVehicleBlock();
        });
    }

    setupRoleSelection();
    setupPreferenceToggles();
    setupFormSubmission();
    initializeAllVehiclePreferences();
});

document.addEventListener('DOMContentLoaded', initProfilePage);
document.addEventListener('turbo:load', initProfilePage);
window.addEventListener('pageshow', initProfilePage);