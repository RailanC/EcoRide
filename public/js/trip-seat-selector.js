const controls = document.querySelectorAll('[data-trip-seat-control]');

controls.forEach((control) => {
    const vehicleSelect = document.getElementById(control.dataset.vehicleSelectId);
    const seatInput = document.getElementById(control.dataset.seatInputId);
    const seatDisplay = control.querySelector('[data-seat-display]');
    const seatHint = control.querySelector('[data-seat-hint]');
    const toggleButton = control.querySelector('[data-seat-toggle]');
    const resetButton = control.querySelector('[data-seat-reset]');
    const dropdown = control.querySelector('[data-seat-dropdown]');
    const seatSelect = control.querySelector('[data-seat-select]');
    const seatHelp = control.querySelector('[data-seat-help]');

    if (!vehicleSelect || !seatInput || !seatDisplay || !seatHint || !toggleButton || !resetButton || !dropdown || !seatSelect || !seatHelp) {
        return;
    }

    const buildOptions = (maxCustomSeats) => {
        seatSelect.innerHTML = '<option value="">Utiliser toutes les places disponibles</option>';

        for (let value = 1; value <= maxCustomSeats; value += 1) {
            const option = document.createElement('option');
            option.value = String(value);
            option.textContent = `${value} place${value > 1 ? 's' : ''}`;
            seatSelect.appendChild(option);
        }
    };

    const getSelectedCapacity = () => {
        const option = vehicleSelect.options[vehicleSelect.selectedIndex];

        if (!option) {
            return 0;
        }

        return Number.parseInt(option.dataset.capacity ?? '0', 10) || 0;
    };

    const renderState = () => {
        const capacity = getSelectedCapacity();

        if (capacity <= 1) {
            seatInput.value = '';
            seatSelect.value = '';
            seatDisplay.textContent = vehicleSelect.value ? 'Vehicule non compatible' : 'Selectionnez une voiture';
            seatHint.textContent = vehicleSelect.value
                ? 'Ce vehicule ne permet pas de proposer des places passagers.'
                : 'Par defaut, toutes les places passagers sont disponibles.';
            seatHelp.textContent = 'Aucune reduction n est disponible pour ce vehicule.';
            toggleButton.disabled = true;
            resetButton.disabled = true;
            dropdown.classList.add('d-none');
            buildOptions(0);

            return;
        }

        const defaultSeats = capacity - 1;
        const maxCustomSeats = Math.max(0, capacity - 2);
        const selectedSeats = Number.parseInt(seatSelect.value || seatInput.value || String(defaultSeats), 10);
        const currentSeats = Number.isNaN(selectedSeats) ? defaultSeats : selectedSeats;

        buildOptions(maxCustomSeats);

        if (currentSeats !== defaultSeats && currentSeats <= maxCustomSeats) {
            seatSelect.value = String(currentSeats);
            seatInput.value = String(currentSeats);
            seatDisplay.textContent = `${currentSeats} place${currentSeats > 1 ? 's' : ''} disponible${currentSeats > 1 ? 's' : ''}`;
            seatHint.textContent = 'Vous avez reserve quelques places pour vos amis ou votre famille.';
            resetButton.disabled = false;
        } else {
            seatSelect.value = '';
            seatInput.value = String(defaultSeats);
            seatDisplay.textContent = `${defaultSeats} place${defaultSeats > 1 ? 's' : ''} disponible${defaultSeats > 1 ? 's' : ''}`;
            seatHint.textContent = 'Par defaut, toutes les places passagers sont disponibles.';
            resetButton.disabled = true;
        }

        if (maxCustomSeats >= 1) {
            seatHelp.textContent = `Vous pouvez reduire les places de ${maxCustomSeats === 1 ? '1 place' : `1 a ${maxCustomSeats} places`}.`;
            toggleButton.disabled = false;
        } else {
            seatHelp.textContent = 'Aucune reduction n est disponible pour ce vehicule.';
            toggleButton.disabled = true;
            resetButton.disabled = true;
            dropdown.classList.add('d-none');
        }
    };

    vehicleSelect.addEventListener('change', () => {
        seatSelect.value = '';
        seatInput.value = '';
        renderState();
    });

    seatSelect.addEventListener('change', renderState);

    toggleButton.addEventListener('click', () => {
        if (toggleButton.disabled) {
            return;
        }

        dropdown.classList.toggle('d-none');
    });

    resetButton.addEventListener('click', () => {
        seatSelect.value = '';
        seatInput.value = '';
        dropdown.classList.add('d-none');
        renderState();
    });

    renderState();
});
