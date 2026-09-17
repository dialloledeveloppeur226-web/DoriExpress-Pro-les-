/**
 * =============================================
 * JAVASCRIPT MAPS / GPS - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/maps.js
 * Rôle : Gestion des cartes, géolocalisation et suivi GPS
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Maps chargé');
    initMaps();
});

// =============================================
// 2. INITIALISATION DES CARTES
// =============================================
function initMaps() {
    initGoogleMaps();
    initGeolocation();
    initTracking();
    initSearchAddress();
    initZoneSelection();
}

// =============================================
// 3. GOOGLE MAPS
// =============================================
let mapInstance = null;
let markers = [];
let directionsRenderer = null;

function initGoogleMaps() {
    const mapContainer = document.getElementById('map');
    if (!mapContainer) return;

    // Vérifier si Google Maps est disponible
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        // Fallback: afficher une carte simulée
        showFallbackMap(mapContainer);
        return;
    }

    const defaultLat = parseFloat(mapContainer.dataset.lat) || 14.0330;
    const defaultLng = parseFloat(mapContainer.dataset.lng) || -0.0330;
    const zoom = parseInt(mapContainer.dataset.zoom) || 15;

    try {
        mapInstance = new google.maps.Map(mapContainer, {
            center: { lat: defaultLat, lng: defaultLng },
            zoom: zoom,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            zoomControl: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        });

        // Ajouter les marqueurs initiaux
        const markersData = JSON.parse(mapContainer.dataset.markers || '[]');
        markersData.forEach(data => {
            addMarker(data.lat, data.lng, data.title, data.icon);
        });

        // Ajouter la position utilisateur
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    mapInstance.setCenter(pos);
                    addMarker(pos.lat, pos.lng, 'Vous êtes ici', null, true);
                },
                function() {
                    // Ignorer l'erreur
                }
            );
        }

        // Directions
        const directionsContainer = document.getElementById('directions');
        if (directionsContainer) {
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: mapInstance,
                panel: directionsContainer,
                suppressMarkers: false
            });
        }

    } catch (e) {
        console.warn('Erreur Google Maps:', e);
        showFallbackMap(mapContainer);
    }
}

// =============================================
// 4. FALLBACK MAP (OpenStreetMap/Leaflet)
// =============================================
function showFallbackMap(container) {
    // Vérifier si Leaflet est disponible
    if (typeof L === 'undefined') {
        container.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;background:#f3f4f6;color:#6b7280;padding:20px;text-align:center;">
                <i class="fas fa-map" style="font-size:48px;color:#d1d5db;margin-bottom:15px;"></i>
                <p style="font-weight:600;font-size:16px;">Carte non disponible</p>
                <p style="font-size:14px;">Veuillez vérifier votre connexion internet.</p>
            </div>
        `;
        return;
    }

    const defaultLat = parseFloat(container.dataset.lat) || 14.0330;
    const defaultLng = parseFloat(container.dataset.lng) || -0.0330;

    const map = L.map(container).setView([defaultLat, defaultLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Ajouter les marqueurs
    const markersData = JSON.parse(container.dataset.markers || '[]');
    markersData.forEach(data => {
        L.marker([data.lat, data.lng])
            .addTo(map)
            .bindPopup(data.title || 'Point');
    });

    // Position utilisateur
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const pos = [position.coords.latitude, position.coords.longitude];
                map.setView(pos, 15);
                L.marker(pos)
                    .addTo(map)
                    .bindPopup('Vous êtes ici');
            },
            function() {
                // Ignorer
            }
        );
    }

    // Redimensionner la carte
    setTimeout(() => {
        map.invalidateSize();
    }, 500);
}

// =============================================
// 5. AJOUTER UN MARQUEUR
// =============================================
function addMarker(lat, lng, title, iconUrl, isUser = false) {
    if (!mapInstance) return;

    const position = { lat: parseFloat(lat), lng: parseFloat(lng) };
    let icon = null;

    if (iconUrl) {
        icon = {
            url: iconUrl,
            scaledSize: new google.maps.Size(32, 32)
        };
    } else if (isUser) {
        icon = {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 12,
            fillColor: '#00A651',
            fillOpacity: 1,
            strokeColor: 'white',
            strokeWeight: 3
        };
    }

    const marker = new google.maps.Marker({
        position: position,
        map: mapInstance,
        title: title || '',
        icon: icon,
        animation: isUser ? google.maps.Animation.BOUNCE : null
    });

    markers.push(marker);

    if (title) {
        const infoWindow = new google.maps.InfoWindow({
            content: `<div style="padding:8px;font-family:'Inter',sans-serif;">
                        <strong>${title}</strong>
                     </div>`
        });
        marker.addListener('click', function() {
            infoWindow.open(mapInstance, marker);
        });
    }

    return marker;
}

// =============================================
// 6. CALCULER UN ITINÉRAIRE
// =============================================
function calculateRoute(origin, destination) {
    if (!mapInstance || !directionsRenderer) return;

    const directionsService = new google.maps.DirectionsService();

    directionsService.route({
        origin: origin,
        destination: destination,
        travelMode: google.maps.TravelMode.DRIVING
    }, function(result, status) {
        if (status === 'OK') {
            directionsRenderer.setDirections(result);
        } else {
            showNotification('❌ Itinéraire non disponible', 'error');
        }
    });
}

// =============================================
// 7. GÉOLOCALISATION
// =============================================
function initGeolocation() {
    const geoBtn = document.getElementById('geo-locate');
    if (!geoBtn) return;

    geoBtn.addEventListener('click', function() {
        if (!navigator.geolocation) {
            showNotification('❌ Géolocalisation non supportée', 'error');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (mapInstance) {
                    mapInstance.setCenter({ lat, lng });
                    mapInstance.setZoom(16);
                    addMarker(lat, lng, 'Vous êtes ici', null, true);
                }

                // Mettre à jour les champs de formulaire
                const latInput = document.getElementById('latitude');
                const lngInput = document.getElementById('longitude');
                if (latInput) latInput.value = lat;
                if (lngInput) lngInput.value = lng;

                showNotification('📍 Position obtenue', 'success');
                geoBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                geoBtn.disabled = false;
            },
            function(error) {
                showNotification('❌ Erreur GPS: ' + error.message, 'error');
                geoBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                geoBtn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

// =============================================
// 8. SUIVI EN TEMPS RÉEL (LIVREUR)
// =============================================
let watchId = null;
let trackingActive = false;

function initTracking() {
    const trackBtn = document.getElementById('track-start');
    if (!trackBtn) return;

    trackBtn.addEventListener('click', function() {
        if (trackingActive) {
            stopTracking();
            return;
        }
        startTracking();
    });
}

function startTracking() {
    if (!navigator.geolocation) {
        showNotification('❌ GPS non supporté', 'error');
        return;
    }

    const btn = document.getElementById('track-start');
    if (btn) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Arrêt...';
        btn.classList.add('active');
    }

    watchId = navigator.geolocation.watchPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Envoyer au serveur
            sendPosition(lat, lng);

            // Mettre à jour la carte
            if (mapInstance) {
                const pos = { lat, lng };
                mapInstance.setCenter(pos);
                // Ajouter ou mettre à jour le marqueur
                if (window.userMarker) {
                    window.userMarker.setPosition(pos);
                } else {
                    window.userMarker = addMarker(lat, lng, 'Livreur', null, true);
                }
            }

            // Mettre à jour l'affichage
            const latDisplay = document.getElementById('current-lat');
            const lngDisplay = document.getElementById('current-lng');
            if (latDisplay) latDisplay.textContent = lat.toFixed(6);
            if (lngDisplay) lngDisplay.textContent = lng.toFixed(6);
        },
        function(error) {
            showNotification('❌ Erreur GPS: ' + error.message, 'error');
            stopTracking();
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
    );

    trackingActive = true;
    showNotification('📍 Suivi GPS activé', 'success');
}

function stopTracking() {
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }

    trackingActive = false;

    const btn = document.getElementById('track-start');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-play"></i> Démarrer le suivi';
        btn.classList.remove('active');
    }

    showNotification('⏸️ Suivi GPS arrêté', 'info');
}

// =============================================
// 9. ENVOYER LA POSITION AU SERVEUR
// =============================================
function sendPosition(lat, lng) {
    const commandeId = document.getElementById('commande-id')?.value || 0;

    fetch('/api/gps/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            latitude: lat,
            longitude: lng,
            commande_id: commandeId,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.warn('Erreur envoi position:', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

// =============================================
// 10. RECHERCHE D'ADRESSE
// =============================================
function initSearchAddress() {
    const searchInput = document.getElementById('address-search');
    const resultsContainer = document.getElementById('address-results');

    if (!searchInput || !resultsContainer) return;

    let debounceTimer;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        if (query.length < 3) {
            resultsContainer.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            searchAddress(query);
        }, 500);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.address-search-container')) {
            resultsContainer.style.display = 'none';
        }
    });
}

function searchAddress(query) {
    const resultsContainer = document.getElementById('address-results');
    if (!resultsContainer) return;

    // Simuler une recherche (à remplacer par Google Places API)
    const mockResults = [
        { description: query + ', Dori, Burkina Faso' },
        { description: query + ', Centre-ville, Dori' },
        { description: query + ', Quartier, Dori' }
    ];

    resultsContainer.innerHTML = mockResults.map(result =>
        `<div class="address-result" data-address="${result.description}">
            <i class="fas fa-map-marker-alt"></i> ${result.description}
         </div>`
    ).join('');

    resultsContainer.style.display = 'block';

    resultsContainer.querySelectorAll('.address-result').forEach(el => {
        el.addEventListener('click', function() {
            const address = this.dataset.address;
            document.getElementById('address-search').value = address;
            resultsContainer.style.display = 'none';

            // Géocoder l'adresse (simulé)
            if (mapInstance) {
                mapInstance.setCenter({ lat: 14.0330, lng: -0.0330 });
                mapInstance.setZoom(16);
                addMarker(14.0330, -0.0330, address);
            }
        });
    });
}

// =============================================
// 11. SÉLECTION DE ZONE
// =============================================
function initZoneSelection() {
    document.querySelectorAll('.zone-select').forEach(select => {
        select.addEventListener('change', function() {
            const zoneId = this.value;
            const zoneData = JSON.parse(this.dataset.zones || '{}');

            if (zoneData[zoneId]) {
                const zone = zoneData[zoneId];
                if (mapInstance) {
                    mapInstance.setCenter({ lat: zone.lat, lng: zone.lng });
                    mapInstance.setZoom(zone.zoom || 14);
                }
                showNotification('📍 Zone sélectionnée: ' + zone.name, 'info');
            }
        });
    });
}

// =============================================
// 12. NOTIFICATION
// =============================================
function showNotification(message, type = 'info') {
    const colors = {
        success: '#22c55e',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };

    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${colors[type] || colors.info};
        color: white;
        border-radius: 12px;
        font-weight: 600;
        z-index: 9999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideInRight 0.5s ease;
        max-width: 400px;
        font-family: inherit;
    `;
    div.textContent = message;
    document.body.appendChild(div);

    setTimeout(() => {
        div.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

// =============================================
// 13. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.addMarker = addMarker;
window.calculateRoute = calculateRoute;
window.startTracking = startTracking;
window.stopTracking = stopTracking;

// =============================================
// FIN DU FICHIER MAPS.JS
// =============================================