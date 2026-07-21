let distanceInMeters = 0;

function recomendationPrice() {
    if (!priceInput || !seatsInput) {
        return;
    }

    const distanceInKm = distanceInMeters / 1000;
    const consumptionPerKm = 0.06;
    const priceEnergy = 2;
    const pricePerKm = consumptionPerKm * priceEnergy;
    const totalCost = distanceInKm * pricePerKm;
    const seatCount = Number(seatsInput.value || seatsOutput?.textContent || 1);
    const estimationPricePerPassenger = totalCost / seatCount;

    priceInput.value = estimationPricePerPassenger.toFixed(2);
    updateTotalPrice();
}

document.addEventListener("DOMContentLoaded", recomendationPrice);
document.querySelectorAll("[data-trip-rating]").forEach((ratingRoot) => {
    const container =
        ratingRoot.closest(".trip-rating-card") ?? ratingRoot.parentElement;
    const feedbackTitle = container?.querySelector("[data-trip-rating-title]");
    const feedbackNote = container?.querySelector("[data-trip-rating-note]");

    if (!feedbackTitle || !feedbackNote) {
        return;
    }

    const labels = Array.from(
        ratingRoot.querySelectorAll("label[data-rating-value]"),
    );
    const inputs = Array.from(ratingRoot.querySelectorAll("input"));

    const clearFeedback = () => {
        const selectedInput = inputs.find((input) => input.checked);

        if (!selectedInput) {
            feedbackTitle.textContent = "";
            feedbackNote.textContent = "";
            return;
        }

        const selectedLabel = labels.find(
            (label) => label.getAttribute("for") === selectedInput.id,
        );

        if (!selectedLabel) {
            return;
        }

        feedbackTitle.textContent = selectedLabel.dataset.ratingTitle ?? "";
        feedbackNote.textContent = selectedLabel.dataset.ratingNote ?? "";
    };

    labels.forEach((label) => {
        const targetInput = inputs.find(
            (input) => input.id === label.getAttribute("for"),
        );

        label.addEventListener("mouseenter", () => {
            feedbackTitle.textContent = label.dataset.ratingTitle ?? "";
            feedbackNote.textContent = label.dataset.ratingNote ?? "";
        });

        label.addEventListener("focus", () => {
            feedbackTitle.textContent = label.dataset.ratingTitle ?? "";
            feedbackNote.textContent = label.dataset.ratingNote ?? "";
        });

        label.addEventListener("click", () => {
            if (targetInput) {
                targetInput.checked = true;
                targetInput.dispatchEvent(
                    new Event("change", { bubbles: true }),
                );
            }

            feedbackTitle.textContent = label.dataset.ratingTitle ?? "";
            feedbackNote.textContent = label.dataset.ratingNote ?? "";
        });
    });

    ratingRoot.addEventListener("mouseleave", clearFeedback);

    inputs.forEach((input) => {
        input.addEventListener("change", clearFeedback);
    });

    clearFeedback();
});

function formatDistance(distanceMeters) {
    if (!Number.isFinite(distanceMeters)) {
        return "";
    }

    return distanceMeters >= 1000
        ? `${(distanceMeters / 1000).toFixed(1)} km`
        : `${Math.round(distanceMeters)} m`;
}

function formatDuration(durationSeconds) {
    if (!Number.isFinite(durationSeconds)) {
        return "";
    }

    const totalMinutes = Math.round(durationSeconds / 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours > 0 && minutes > 0) {
        return `${hours} h ${minutes} min`;
    }

    if (hours > 0) {
        return `${hours} h`;
    }

    return `${minutes} min`;
}

function debounce(callback, delay) {
    let timeoutId;

    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), delay);
    };
}

function initTripMap(root) {
    const mapElement = root.querySelector("[data-trip-map]");
    const summaryElement = root.querySelector("[data-trip-map-summary]");
    const statusElement = root.querySelector("[data-trip-map-status]");

    if (!mapElement || typeof window.L === "undefined") {
        return;
    }

    const map = window.L.map(mapElement, {
        scrollWheelZoom: false,
    }).setView([46.603354, 1.888334], 6);

    window.L.tileLayer(
    "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
    {
        attribution: "&copy; OpenStreetMap contributors &copy; CARTO",
    }
    ).addTo(map);

    let routeLayer = null;
    let startMarker = null;
    let endMarker = null;
    let requestNonce = 0;

    const setStatus = (message, isError = false) => {
        if (!statusElement) {
            return;
        }

        statusElement.textContent = message;
        statusElement.classList.toggle("trip-map-status--error", isError);
    };

    const setSummary = (message) => {
        if (root.dataset.disableSummary === "true") {
            return;
        }

        if (summaryElement) {
            summaryElement.textContent = message;
        }
    };
    const clearRoute = () => {
        if (routeLayer) {
            map.removeLayer(routeLayer);
            routeLayer = null;
        }

        if (startMarker) {
            map.removeLayer(startMarker);
            startMarker = null;
        }

        if (endMarker) {
            map.removeLayer(endMarker);
            endMarker = null;
        }
    };

    const drawRoute = (payload) => {
        clearRoute();

        routeLayer = window.L.geoJSON(payload.geometry, {
            style: {
                color: "#A7D930",
                weight: 5,
                opacity: 0.9,
            },
        }).addTo(map);

        const coordinates = payload.geometry?.coordinates ?? [];
        const start = coordinates[0];
        const end = coordinates[coordinates.length - 1];

        if (Array.isArray(start) && start.length >= 2) {
            startMarker = L.marker([start[1], start[0]])
                .addTo(map)
                .bindPopup(`
                    <div class="trip-popup">
                        <strong>Départ</strong><br>
                        ${payload.departureLabel}
                    </div>
                `);
        }

        if (Array.isArray(end) && end.length >= 2) {
            endMarker = L.marker([end[1], end[0]])
                .addTo(map)
                .bindPopup(`
                    <div class="trip-popup">
                        <strong>Destination</strong><br>
                        ${payload.destinationLabel}
                    </div>
                `);
        }

        const bounds = routeLayer.getBounds();

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24] });
        }

        setSummary(
            `${payload.departureLabel} -> ${payload.destinationLabel} • ${formatDistance(payload.distanceMeters)} • ${formatDuration(payload.durationSeconds)}`,
        );
        const distanceElement = document.querySelector("[data-trip-distance]");

        if (distanceElement) {
            distanceElement.textContent = `📍 ${formatDistance(payload.distanceMeters)}`;
        }

        distanceInMeters = payload.distanceMeters;
        
        recomendationPrice();
        setStatus("Itineraire chargé.");
    };

    const loadRoute = async (url) => {
        const nonce = ++requestNonce;

        setStatus("Chargement de l itineraire...");

        try {
            const response = await window.fetch(url, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            const payload = await response.json();

            if (nonce !== requestNonce) {
                return;
            }

            if (!response.ok) {
                clearRoute();
                setSummary("Aucun apercu disponible.");
                setStatus(
                    payload.error ?? "Impossible de charger l itineraire.",
                    true,
                );
                return;
            }

            drawRoute(payload);
        } catch (error) {
            if (nonce !== requestNonce) {
                return;
            }

            clearRoute();
            setSummary("Aucun apercu disponible.");
            setStatus("La previsualisation de la carte a echoue.", true);
        }
    };

    if (root.dataset.mapMode === "detail") {
        loadRoute(root.dataset.endpoint);
        return;
    }

    const departureInput = document.querySelector(
        root.dataset.departureInput ?? "",
    );
    const destinationInput = document.querySelector(
        root.dataset.destinationInput ?? "",
    );

    if (!departureInput || !destinationInput) {
        setStatus("Impossible d initialiser la carte.", true);
        return;
    }

    const refreshPreview = debounce(() => {
        const departure = departureInput.value.trim();
        const destination = destinationInput.value.trim();

        if (departure.length < 2 || destination.length < 2) {
            clearRoute();
            setSummary("Renseignez les villes pour afficher le trajet.");
            setStatus("La carte se mettra a jour automatiquement.");
            return;
        }

        const url = new URL(root.dataset.endpoint, window.location.origin);
        url.searchParams.set("departure", departure);
        url.searchParams.set("destination", destination);
        loadRoute(url.toString());
    }, 450);

    departureInput.addEventListener("input", refreshPreview);
    destinationInput.addEventListener("input", refreshPreview);

    
    refreshPreview();
}

function initTripMaps() {
    document.querySelectorAll("[data-trip-map-root]").forEach(initTripMap);
}

document.addEventListener("DOMContentLoaded", initTripMaps);

function setTrip(button, isReturn) {
    const group = button.closest("[data-trip-toggle-group]");
    if (group) {
        group
            .querySelectorAll("[data-trip-toggle-option]")
            .forEach((tripButton) => {
                const isActive = tripButton === button;
                tripButton.classList.toggle("btn-success", isActive);
                tripButton.classList.toggle("btn-dark", !isActive);
                tripButton.setAttribute("aria-pressed", String(isActive));
            });
    }

    const returnField = document.getElementById("return-field");
    if (!returnField) {
        return;
    }

    if (isReturn) {
        returnField.classList.remove("d-none", "opacity-50", "pe-none");
    } else {
        returnField.classList.add("d-none", "opacity-50", "pe-none");
        const returnDateInput = document.getElementById("return-date");
        if (returnDateInput) {
            returnDateInput.value = "";
        }
    }
}

function swapCities(event) {
    const departureInput = document.getElementById("from");
    const arrivalInput = document.getElementById("to");

    if (!departureInput || !arrivalInput) {
        return;
    }

    [departureInput.value, arrivalInput.value] = [
        arrivalInput.value,
        departureInput.value,
    ];

    const button = event?.currentTarget ?? document.querySelector(".swap-btn");
    if (!button) {
        return;
    }

    button.style.transform = "rotate(180deg)";
    setTimeout(() => {
        button.style.transform = "";
    }, 300);
}

function initNavbarTogglerFallback() {
    const navbarToggler = document.querySelector(".navbar-toggler");
    const mainNavbar = document.getElementById("mainNavbar");

    if (
        !navbarToggler ||
        !mainNavbar ||
        (window.bootstrap && window.bootstrap.Collapse)
    ) {
        return;
    }

    navbarToggler.addEventListener("click", (currentEvent) => {
        currentEvent.preventDefault();
        const isOpen = mainNavbar.classList.toggle("show");
        navbarToggler.setAttribute("aria-expanded", String(isOpen));
    });
}

window.setTrip = setTrip;
window.swapCities = swapCities;

document.addEventListener("DOMContentLoaded", initNavbarTogglerFallback);

const radioPills = document.querySelectorAll(".radio-pill");

function updateRadioPills() {
    radioPills.forEach((pill) => {
        const radio = pill.querySelector('input[type="radio"]');

        if (radio.checked) {
            pill.classList.remove("radio-pill--inactive");
        } else {
            pill.classList.add("radio-pill--inactive");
        }
    });
}

radioPills.forEach((pill) => {
    const radio = pill.querySelector('input[type="radio"]');

    radio.addEventListener("change", updateRadioPills);
});

function rangeSliderUpdate(rangeId, symbol) {
    const range = document.getElementById(rangeId);
    if (range) {
        const out = range.parentElement.querySelector("output");
        if (out) {
            const updateOutput = function () {
                out.textContent = range.value + symbol;
            };
            updateOutput();
            range.addEventListener("input", updateOutput);
        }
    }
}

document.addEventListener("DOMContentLoaded", function () {
    rangeSliderUpdate("min_rating", " ★");
    rangeSliderUpdate("max_price", " €");

    document
        .querySelectorAll(".date-picker-trigger")
        .forEach(function (trigger) {
            const label = trigger.closest("label");
            if (!label) {
                return;
            }

            const input = label.querySelector('input[type="date"]');
            if (!input) {
                return;
            }

            const openPicker = function () {
                if (typeof input.showPicker === "function") {
                    input.showPicker();
                } else {
                    input.focus();
                }
            };

            trigger.addEventListener("click", function (event) {
                event.preventDefault();
                openPicker();
            });

            trigger.addEventListener("keydown", function (event) {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    openPicker();
                }
            });
        });
});

const loadMoreBtnElement = document.getElementById("loadMoreBtn");
if (loadMoreBtnElement) {
    loadMoreBtnElement.addEventListener("click", () => {
        const hiddenTrips = document.querySelectorAll(".trip-card--hidden");

        for (let i = 0; i < 5 && i < hiddenTrips.length; i++) {
            hiddenTrips[i].classList.remove("trip-card--hidden");
        }

        if (document.querySelectorAll(".trip-card--hidden").length === 0) {
            loadMoreBtnElement.style.display = "none";
        }
    });
}

const seatsOutput = document.getElementById("seats_count");
const seatsInput = document.getElementById("new_trip_form_seats");

const increaseBtn = document.getElementById("increaseSeats");
const decreaseBtn = document.getElementById("decreaseSeats");

const seatsProgressBar = document.getElementById("seats_progress");

const minSeats = 1;

function getSelectedCar() {
    return (
        document.querySelector(".vehicle-selector input[type='radio']:checked") ??
        document.querySelector(".vehicle-selector input[type='radio']")
    );
}

function updateSeats(value) {
    if (!seatsOutput || !seatsInput || !seatsProgressBar) {
        return;
    }

    const selectedCar = getSelectedCar();
    const maxSeats = Number(selectedCar?.dataset.capacity || 8);
    const nextValue = Math.max(minSeats, Math.min(maxSeats, value));

    seatsOutput.textContent = nextValue;
    seatsInput.value = nextValue;
    seatsProgressBar.style.setProperty("--seats-value", nextValue);
    recomendationPrice();
}

if (increaseBtn) {
    increaseBtn.addEventListener("click", () => {
        const currentValue = parseInt(seatsOutput?.textContent || seatsInput?.value || minSeats, 10) || minSeats;
        updateSeats(currentValue + 1);
    });
}

if (decreaseBtn) {
    decreaseBtn.addEventListener("click", () => {
        const currentValue = parseInt(seatsOutput?.textContent || seatsInput?.value || minSeats, 10) || minSeats;
        updateSeats(currentValue - 1);
    });
}

const priceInput = document.getElementById("new_trip_form_price_per_passenger");
const totalPrice = document.getElementById("total-price");
const passengerContribution = document.getElementById("passenger-contribution");

function updateTotalPrice() {
    const price = parseFloat(priceInput.value) || 0;
    const total = price + 2;
    passengerContribution.textContent = `${price.toFixed(2)} C`;
    totalPrice.textContent = `${total.toFixed(2)} C`;
}

if (priceInput) {
    priceInput.addEventListener("input", updateTotalPrice);
}

const radioCarCard = document.querySelectorAll(".vehicle-card");

function syncSeatCapacity() {
    if (!seatsProgressBar || !seatsOutput || !seatsInput) {
        return;
    }

    const currentSeats = Number(seatsInput.value || 3);
    const selectedCar = getSelectedCar();
    const maxSeats = Number(selectedCar?.dataset.capacity || 8);
    const clampedSeats = Math.min(Math.max(currentSeats, 1), maxSeats);

    seatsProgressBar.setAttribute("aria-valuemax", maxSeats);
    seatsProgressBar.setAttribute("aria-valuenow", clampedSeats);
    seatsProgressBar.style.setProperty("--seats-max", maxSeats);
    seatsProgressBar.style.setProperty("--seats-value", clampedSeats);

    seatsOutput.textContent = clampedSeats;
    seatsInput.value = clampedSeats;
}

function updateRadioCarCard() {
    radioCarCard.forEach((card) => {
        const radio = card.querySelector('input[type="radio"]');

        if (radio.checked) {
            card.classList.add("vehicle-card--selected");
        } else {
            card.classList.remove("vehicle-card--selected");
        }
    });
}

function initializeVehicleSelection() {
    const firstRadio = document.querySelector(".vehicle-selector input[type='radio']");
    const checkedRadio = document.querySelector(
        ".vehicle-selector input[type='radio']:checked",
    );

    if (!checkedRadio && firstRadio) {
        firstRadio.checked = true;
    }

    updateRadioCarCard();
    syncSeatCapacity();
}

document.addEventListener("DOMContentLoaded", initializeVehicleSelection);

radioCarCard.forEach((card) => {
    const radio = card.querySelector('input[type="radio"]');

    if (!radio) {
        return;
    }

    radio.addEventListener("change", () => {
        updateRadioCarCard();
        syncSeatCapacity();
    });
});


const checkRatingCheckbox = document.getElementById("check_rating");
function toggleMinRatingContainer() {
    const minRatingContainer = document.getElementById("min_rating_container");
    if (minRatingContainer) {
        minRatingContainer.hidden = !checkRatingCheckbox.checked;
    }
}

checkRatingCheckbox.addEventListener("change", toggleMinRatingContainer); 



document.addEventListener("DOMContentLoaded", toggleMinRatingContainer);