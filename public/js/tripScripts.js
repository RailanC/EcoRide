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

    window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);

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
            startMarker = window.L.marker([start[1], start[0]])
                .addTo(map)
                .bindPopup(payload.departureLabel);
        }

        if (Array.isArray(end) && end.length >= 2) {
            endMarker = window.L.marker([end[1], end[0]])
                .addTo(map)
                .bindPopup(payload.destinationLabel);
        }

        const bounds = routeLayer.getBounds();

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24] });
        }

        setSummary(
            `${payload.departureLabel} -> ${payload.destinationLabel} • ${formatDistance(payload.distanceMeters)} • ${formatDuration(payload.durationSeconds)}`,
        );
        setStatus("Itineraire charge.");
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

const controls = document.querySelectorAll("[data-trip-seat-control]");

controls.forEach((control) => {
    const vehicleSelect = document.getElementById(
        control.dataset.vehicleSelectId,
    );
    const seatInput = document.getElementById(control.dataset.seatInputId);
    const seatDisplay = control.querySelector("[data-seat-display]");
    const seatHint = control.querySelector("[data-seat-hint]");
    const toggleButton = control.querySelector("[data-seat-toggle]");
    const resetButton = control.querySelector("[data-seat-reset]");
    const dropdown = control.querySelector("[data-seat-dropdown]");
    const seatSelect = control.querySelector("[data-seat-select]");
    const seatHelp = control.querySelector("[data-seat-help]");

    if (
        !vehicleSelect ||
        !seatInput ||
        !seatDisplay ||
        !seatHint ||
        !toggleButton ||
        !resetButton ||
        !dropdown ||
        !seatSelect ||
        !seatHelp
    ) {
        return;
    }

    const buildOptions = (maxCustomSeats) => {
        seatSelect.innerHTML =
            '<option value="">Utiliser toutes les places disponibles</option>';

        for (let value = 1; value <= maxCustomSeats; value += 1) {
            const option = document.createElement("option");
            option.value = String(value);
            option.textContent = `${value} place${value > 1 ? "s" : ""}`;
            seatSelect.appendChild(option);
        }
    };

    const getSelectedCapacity = () => {
        const option = vehicleSelect.options[vehicleSelect.selectedIndex];

        if (!option) {
            return 0;
        }

        return Number.parseInt(option.dataset.capacity ?? "0", 10) || 0;
    };

    const renderState = () => {
        const capacity = getSelectedCapacity();

        if (capacity <= 1) {
            seatInput.value = "";
            seatSelect.value = "";
            seatDisplay.textContent = vehicleSelect.value
                ? "Vehicule non compatible"
                : "Selectionnez une voiture";
            seatHint.textContent = vehicleSelect.value
                ? "Ce vehicule ne permet pas de proposer des places passagers."
                : "Par defaut, toutes les places passagers sont disponibles.";
            seatHelp.textContent =
                "Aucune reduction n est disponible pour ce vehicule.";
            toggleButton.disabled = true;
            resetButton.disabled = true;
            dropdown.classList.add("d-none");
            buildOptions(0);

            return;
        }

        const defaultSeats = capacity - 1;
        const maxCustomSeats = Math.max(0, capacity - 2);
        const selectedSeats = Number.parseInt(
            seatSelect.value || seatInput.value || String(defaultSeats),
            10,
        );
        const currentSeats = Number.isNaN(selectedSeats)
            ? defaultSeats
            : selectedSeats;

        buildOptions(maxCustomSeats);

        if (currentSeats !== defaultSeats && currentSeats <= maxCustomSeats) {
            seatSelect.value = String(currentSeats);
            seatInput.value = String(currentSeats);
            seatDisplay.textContent = `${currentSeats} place${currentSeats > 1 ? "s" : ""} disponible${currentSeats > 1 ? "s" : ""}`;
            seatHint.textContent =
                "Vous avez reserve quelques places pour vos amis ou votre famille.";
            resetButton.disabled = false;
        } else {
            seatSelect.value = "";
            seatInput.value = String(defaultSeats);
            seatDisplay.textContent = `${defaultSeats} place${defaultSeats > 1 ? "s" : ""} disponible${defaultSeats > 1 ? "s" : ""}`;
            seatHint.textContent =
                "Par defaut, toutes les places passagers sont disponibles.";
            resetButton.disabled = true;
        }

        if (maxCustomSeats >= 1) {
            seatHelp.textContent = `Vous pouvez reduire les places de ${maxCustomSeats === 1 ? "1 place" : `1 a ${maxCustomSeats} places`}.`;
            toggleButton.disabled = false;
        } else {
            seatHelp.textContent =
                "Aucune reduction n est disponible pour ce vehicule.";
            toggleButton.disabled = true;
            resetButton.disabled = true;
            dropdown.classList.add("d-none");
        }
    };

    vehicleSelect.addEventListener("change", () => {
        seatSelect.value = "";
        seatInput.value = "";
        renderState();
    });

    seatSelect.addEventListener("change", renderState);

    toggleButton.addEventListener("click", () => {
        if (toggleButton.disabled) {
            return;
        }

        dropdown.classList.toggle("d-none");
    });

    resetButton.addEventListener("click", () => {
        seatSelect.value = "";
        seatInput.value = "";
        dropdown.classList.add("d-none");
        renderState();
    });

    renderState();
});

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

updateRadioPills();

function updateReturnFieldVisibility(show) {
    const returnSection = document.getElementById("return-date-section");
    if (!returnSection) {
        return;
    }
    returnSection.classList.toggle("d-none", !show);
}

function setTripType(btn, isReturn) {
    const tripTypeField = document.getElementById("trip_type");
    if (tripTypeField) {
        tripTypeField.value = isReturn ? "return" : "";
    }

    // Find all toggle buttons in the same group
    const group = btn.closest(".trip-search-type-group");
    const toggles = group
        ? group.querySelectorAll("[data-trip-toggle-option]")
        : document.querySelectorAll("[data-trip-toggle-option]");

    toggles.forEach(function (toggle) {
        const isActive = toggle === btn;
        toggle.classList.toggle("active", isActive);
    });

    updateReturnFieldVisibility(isReturn);
}

// Expose to global scope for inline onclick handlers
window.setTripType = setTripType;

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

// Range slider live update
document.addEventListener("DOMContentLoaded", function () {
    rangeSliderUpdate("min_rating", " ★");
    rangeSliderUpdate("max_price", " €");

    const tripTypeField = document.getElementById("trip_type");
    if (tripTypeField) {
        updateReturnFieldVisibility(tripTypeField.value === "return");
    }

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
