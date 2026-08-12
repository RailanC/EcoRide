function togglePw(inputId, button) {
    const input = document.getElementById(inputId);
    if (!input) {
        return;
    }

    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';

    button.innerHTML = isText
        ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
        : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
}

function handleRoleChange() {
    const selectedRole = document.querySelector('input[name="role"]:checked')?.value;
    const driverSection = document.getElementById('driverSection');
    const passengerSection = document.getElementById('passengerSection');
    const passengerBadge = document.getElementById('badgePassenger');
    const driverBadge = document.getElementById('badgeDriver');

    if (!driverSection || !passengerSection || !passengerBadge || !driverBadge) {
        return;
    }

    const isDriver = selectedRole === 'driver' || selectedRole === 'both';
    const isPassenger = selectedRole === 'passenger' || selectedRole === 'both';

    driverSection.style.display = isDriver ? 'block' : 'none';
    passengerSection.style.display = !isDriver && isPassenger ? 'block' : 'none';
    passengerBadge.style.display = isPassenger ? 'inline-flex' : 'none';
    driverBadge.style.display = isDriver ? 'inline-flex' : 'none';
}

function getDefaultPreferences() {
    return { smoking: 0, animals: 0, custom: [] };
}

function getPreferencesInput(vehicleBlock) {
    return vehicleBlock.querySelector('input[name$="[preferences]"]');
}

function parsePreferences(value) {
    if (!value || typeof value !== 'string') {
        return null;
    }

    try {
        const parsed = JSON.parse(value);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

function updateToggleButton(toggle, value) {
    toggle.querySelectorAll('.pref-btn').forEach(button => button.classList.remove('active'));
    toggle.querySelector(`.pref-btn[data-pref-value="${value}"]`)?.classList.add('active');
}

function syncVehiclePreferences(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return;
    }

    const preferences = parsePreferences(input.value) || getDefaultPreferences();
    vehicleBlock.querySelectorAll('.pref-toggle').forEach(toggle => {
        const preferenceName = toggle.dataset.prefName;
        updateToggleButton(toggle, Number(preferences[preferenceName] ?? 0));
    });

    input.value = JSON.stringify({
        smoking: Number(preferences.smoking ?? 0),
        animals: Number(preferences.animals ?? 0),
        custom: Array.isArray(preferences.custom) ? preferences.custom : [],
    });
}

function updatePreferencesJson(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return;
    }

    const preferences = getDefaultPreferences();
    vehicleBlock.querySelectorAll('.pref-toggle').forEach(toggle => {
        const preferenceName = toggle.dataset.prefName;
        const activeButton = toggle.querySelector('.pref-btn.active');
        if (preferenceName && activeButton) {
            preferences[preferenceName] = Number(activeButton.dataset.prefValue);
        }
    });

    input.value = JSON.stringify(preferences);
}

function initializeVehicleBlock(vehicleBlock) {
    const input = getPreferencesInput(vehicleBlock);
    if (!input) {
        return;
    }

    if (!parsePreferences(input.value)) {
        input.value = JSON.stringify(getDefaultPreferences());
    }

    syncVehiclePreferences(vehicleBlock);
}

function buildVehicleBlock(index) {
    const brands = Array.isArray(window.AVAILABLE_BRANDS) ? window.AVAILABLE_BRANDS : [];
    const brandOptions = brands.map(brand => `<option value="${brand}">${brand}</option>`).join('');

    const block = document.createElement('div');
    block.className = 'vehicle-block mb-4';
    block.dataset.index = String(index);
    block.style.border = '1px solid rgba(255,255,255,.07)';
    block.style.borderRadius = 'var(--radius-sm)';
    block.style.padding = '1.25rem';
    block.style.background = 'rgba(15,17,23,.5)';

    block.innerHTML = `
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <label class="contact-label" for="profile_form_vehicles_${index}_registrationNumber">Plaque d'immatriculation</label>
                <input type="text" id="profile_form_vehicles_${index}_registrationNumber" name="profile_form[vehicles][${index}][registrationNumber]" class="form-control contact-input" placeholder="AB-123-CD" required>
            </div>
            <div class="col-12 col-sm-6">
                <label class="contact-label" for="profile_form_vehicles_${index}_firstRegistrationDate">1ere mise en circulation</label>
                <input type="date" id="profile_form_vehicles_${index}_firstRegistrationDate" name="profile_form[vehicles][${index}][firstRegistrationDate]" class="form-control contact-input" style="color-scheme:dark;">
            </div>
            <div class="col-12 col-sm-4">
                <label class="contact-label" for="profile_form_vehicles_${index}_brand">Marque</label>
                <select id="profile_form_vehicles_${index}_brand" name="profile_form[vehicles][${index}][brand]" class="form-select contact-input" required>
                    <option value="">Choisir une marque</option>
                    ${brandOptions}
                </select>
            </div>
            <div class="col-12 col-sm-4">
                <label class="contact-label" for="profile_form_vehicles_${index}_model">Modele</label>
                <input type="text" id="profile_form_vehicles_${index}_model" name="profile_form[vehicles][${index}][model]" class="form-control contact-input" placeholder="Zoe" required>
            </div>
            <div class="col-12 col-sm-4">
                <label class="contact-label" for="profile_form_vehicles_${index}_color">Couleur</label>
                <input type="text" id="profile_form_vehicles_${index}_color" name="profile_form[vehicles][${index}][color]" class="form-control contact-input" placeholder="Blanche">
            </div>
            <div class="col-12 col-sm-4">
                <label class="contact-label" for="profile_form_vehicles_${index}_energyType">Type d'energie</label>
                <select id="profile_form_vehicles_${index}_energyType" name="profile_form[vehicles][${index}][energyType]" class="form-select contact-input" required>
                    <option value="">Choisir</option>
                    <option value="Essence">Essence</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Electrique">Electrique</option>
                    <option value="Hybride">Hybride</option>
                    <option value="Hybride rechargeable">Hybride rechargeable</option>
                    <option value="GNV">GNV</option>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="contact-label" for="profile_form_vehicles_${index}_places">Places disponibles</label>
                <select id="profile_form_vehicles_${index}_places" name="profile_form[vehicles][${index}][places]" class="form-select contact-input">
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
                <p class="contact-label mb-0" style="font-size:.78rem;">Preferences a bord</p>
                <span style="font-size:.7rem;color:var(--text-muted);">Configurables</span>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-12 col-sm-6">
                    <div class="pref-toggle" data-pref-name="smoking">
                        <button type="button" class="pref-btn active" data-pref-value="0">Pas de tabac</button>
                        <button type="button" class="pref-btn" data-pref-value="1">Fumeurs OK</button>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="pref-toggle" data-pref-name="animals">
                        <button type="button" class="pref-btn active" data-pref-value="0">Pas d'animaux</button>
                        <button type="button" class="pref-btn" data-pref-value="1">Animaux OK</button>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" name="profile_form[vehicles][${index}][preferences]" value='{"smoking":0,"animals":0,"custom":[]}' data-preferences-hidden>
        <button type="button" class="btn btn-sm btn-outline-danger mt-3 remove-vehicle-btn" data-confirm-message="Voulez-vous vraiment supprimer ce véhicule de votre profil ?" data-confirm-title="Supprimer ce véhicule" data-confirm-button="Oui, supprimer" data-confirm-action="remove-closest" data-confirm-target=".vehicle-block">Supprimer ce véhicule</button>
    `;

    return block;
}

function addVehicleBlock() {
    const vehicleList = document.getElementById('vehicleList');
    if (!vehicleList) {
        return;
    }

    const newBlock = buildVehicleBlock(vehicleList.querySelectorAll('.vehicle-block').length);
    vehicleList.appendChild(newBlock);
    initializeVehicleBlock(newBlock);
}

function initProfilePage() {
    document.querySelectorAll('input[name="role"]').forEach(radio => {
        radio.removeEventListener('change', handleRoleChange);
        radio.addEventListener('change', handleRoleChange);
    });

    document.querySelectorAll('.vehicle-block').forEach(initializeVehicleBlock);

    const addVehicleButton = document.getElementById('addVehicleBtn');
    if (addVehicleButton && !addVehicleButton.dataset.bound) {
        addVehicleButton.dataset.bound = 'true';
        addVehicleButton.addEventListener('click', event => {
            event.preventDefault();
            addVehicleBlock();
        });
    }

    handleRoleChange();
}

document.addEventListener('click', event => {
    const preferenceButton = event.target.closest('.pref-btn');
    if (preferenceButton) {
        const toggle = preferenceButton.closest('.pref-toggle');
        const vehicleBlock = preferenceButton.closest('.vehicle-block');
        if (toggle && vehicleBlock) {
            updateToggleButton(toggle, preferenceButton.dataset.prefValue);
            updatePreferencesJson(vehicleBlock);
        }
    }
});

document.addEventListener('submit', event => {
    if (event.target instanceof HTMLFormElement) {
        event.target.querySelectorAll('.vehicle-block').forEach(updatePreferencesJson);
    }
});

window.togglePw = togglePw;
document.addEventListener('DOMContentLoaded', initProfilePage);
window.addEventListener('pageshow', initProfilePage);
