function setTrip(button, isReturn) {
    const group = button.closest('[data-trip-toggle-group]');
    if (group) {
        group.querySelectorAll('[data-trip-toggle-option]').forEach(tripButton => {
            const isActive = tripButton === button;
            tripButton.classList.toggle('btn-success', isActive);
            tripButton.classList.toggle('btn-dark', !isActive);
            tripButton.setAttribute('aria-pressed', String(isActive));
        });
    }

    const returnField = document.getElementById('return-field');
    if (!returnField) {
        return;
    }

    if (isReturn) {
        returnField.classList.remove('d-none', 'opacity-50', 'pe-none');
    } else {
        returnField.classList.add('d-none', 'opacity-50', 'pe-none');
        const returnDateInput = document.getElementById('return-date');
        if (returnDateInput) {
            returnDateInput.value = '';
        }
    }
}

function swapCities(event) {
    const departureInput = document.getElementById('from');
    const arrivalInput = document.getElementById('to');

    if (!departureInput || !arrivalInput) {
        return;
    }

    [departureInput.value, arrivalInput.value] = [arrivalInput.value, departureInput.value];

    const button = event?.currentTarget ?? document.querySelector('.swap-btn');
    if (!button) {
        return;
    }

    button.style.transform = 'rotate(180deg)';
    setTimeout(() => {
        button.style.transform = '';
    }, 300);
}

function initNavbarTogglerFallback() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const mainNavbar = document.getElementById('mainNavbar');

    if (!navbarToggler || !mainNavbar || (window.bootstrap && window.bootstrap.Collapse)) {
        return;
    }

    navbarToggler.addEventListener('click', currentEvent => {
        currentEvent.preventDefault();
        const isOpen = mainNavbar.classList.toggle('show');
        navbarToggler.setAttribute('aria-expanded', String(isOpen));
    });
}

window.setTrip = setTrip;
window.swapCities = swapCities;

document.addEventListener('DOMContentLoaded', initNavbarTogglerFallback);
