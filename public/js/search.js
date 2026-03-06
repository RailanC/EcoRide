function setTrip(btn, isReturn) {
    document.querySelectorAll('.trip-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const returnField = document.getElementById('return-field');
    if (isReturn) {
        returnField.classList.remove('search-field--disabled');
    } else {
        returnField.classList.add('search-field--disabled');
        document.getElementById('return-date').value = '';
    }
}

function swapCities(e) {
    const from = document.getElementById('from');
    const to = document.getElementById('to');
    [from.value, to.value] = [to.value, from.value];
    const btn = e.currentTarget;
    btn.style.transform = 'rotate(180deg)';
    setTimeout(() => btn.style.transform = '', 300);
}

window.setTrip = setTrip;
window.swapCities = swapCities;