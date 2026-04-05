function formatDistance(distanceMeters) {
    if (!Number.isFinite(distanceMeters)) {
        return '';
    }

    return distanceMeters >= 1000
        ? `${(distanceMeters / 1000).toFixed(1)} km`
        : `${Math.round(distanceMeters)} m`;
}

function formatDuration(durationSeconds) {
    if (!Number.isFinite(durationSeconds)) {
        return '';
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
    const mapElement = root.querySelector('[data-trip-map]');
    const summaryElement = root.querySelector('[data-trip-map-summary]');
    const statusElement = root.querySelector('[data-trip-map-status]');

    if (!mapElement || typeof window.L === 'undefined') {
        return;
    }

    const map = window.L.map(mapElement, {
        scrollWheelZoom: false,
    }).setView([46.603354, 1.888334], 6);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
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
        statusElement.classList.toggle('trip-map-status--error', isError);
    };

    const setSummary = message => {
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

    const drawRoute = payload => {
        clearRoute();

        routeLayer = window.L.geoJSON(payload.geometry, {
            style: {
                color: '#A7D930',
                weight: 5,
                opacity: 0.9,
            },
        }).addTo(map);

        const coordinates = payload.geometry?.coordinates ?? [];
        const start = coordinates[0];
        const end = coordinates[coordinates.length - 1];

        if (Array.isArray(start) && start.length >= 2) {
            startMarker = window.L.marker([start[1], start[0]]).addTo(map).bindPopup(payload.departureLabel);
        }

        if (Array.isArray(end) && end.length >= 2) {
            endMarker = window.L.marker([end[1], end[0]]).addTo(map).bindPopup(payload.destinationLabel);
        }

        const bounds = routeLayer.getBounds();

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24] });
        }

        setSummary(`${payload.departureLabel} -> ${payload.destinationLabel} • ${formatDistance(payload.distanceMeters)} • ${formatDuration(payload.durationSeconds)}`);
        setStatus('Itineraire charge.');
    };

    const loadRoute = async url => {
        const nonce = ++requestNonce;

        setStatus('Chargement de l itineraire...');

        try {
            const response = await window.fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();

            if (nonce !== requestNonce) {
                return;
            }

            if (!response.ok) {
                clearRoute();
                setSummary('Aucun apercu disponible.');
                setStatus(payload.error ?? 'Impossible de charger l itineraire.', true);
                return;
            }

            drawRoute(payload);
        } catch (error) {
            if (nonce !== requestNonce) {
                return;
            }

            clearRoute();
            setSummary('Aucun apercu disponible.');
            setStatus('La previsualisation de la carte a echoue.', true);
        }
    };

    if (root.dataset.mapMode === 'detail') {
        loadRoute(root.dataset.endpoint);
        return;
    }

    const departureInput = document.querySelector(root.dataset.departureInput ?? '');
    const destinationInput = document.querySelector(root.dataset.destinationInput ?? '');

    if (!departureInput || !destinationInput) {
        setStatus('Impossible d initialiser la carte.', true);
        return;
    }

    const refreshPreview = debounce(() => {
        const departure = departureInput.value.trim();
        const destination = destinationInput.value.trim();

        if (departure.length < 2 || destination.length < 2) {
            clearRoute();
            setSummary('Renseignez les villes pour afficher le trajet.');
            setStatus('La carte se mettra a jour automatiquement.');
            return;
        }

        const url = new URL(root.dataset.endpoint, window.location.origin);
        url.searchParams.set('departure', departure);
        url.searchParams.set('destination', destination);

        loadRoute(url.toString());
    }, 450);

    departureInput.addEventListener('input', refreshPreview);
    destinationInput.addEventListener('input', refreshPreview);

    refreshPreview();
}

function initTripMaps() {
    document.querySelectorAll('[data-trip-map-root]').forEach(initTripMap);
}

document.addEventListener('DOMContentLoaded', initTripMaps);
