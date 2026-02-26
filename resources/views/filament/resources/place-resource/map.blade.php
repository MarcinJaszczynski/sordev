<div id="place-map" style="height: 300px; width: 100%;"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script>
    let placeMapInstance = null;
    let placeMarker = null;

    function getFormLatLon() {
        // Pobierz aktualne wartości z pól formularza
        var latInput = document.querySelector('input[wire\\:model="data.latitude"]') || 
                      document.querySelector('input[name="latitude"]');
        var lonInput = document.querySelector('input[wire\\:model="data.longitude"]') || 
                      document.querySelector('input[name="longitude"]');
        
        var lat = latInput ? parseFloat(latInput.value) : null;
        var lon = lonInput ? parseFloat(lonInput.value) : null;
        
        if (!isNaN(lat) && !isNaN(lon) && lat !== null && lon !== null) {
            return [lat, lon];
        }
        // Domyślne centrum Warszawa
        return [52.2297, 21.0122];
    }

    function initPlaceMap() {
        var mapDiv = document.getElementById('place-map');
        if (!mapDiv) return;

        // Usuń starą mapę jeśli istnieje
        if (placeMapInstance) {
            placeMapInstance.remove();
            placeMapInstance = null;
            placeMarker = null;
        }

        var coords = getFormLatLon();
        var lat = coords[0];
        var lon = coords[1];
        
        placeMapInstance = L.map('place-map').setView([lat, lon], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(placeMapInstance);
        placeMarker = L.marker([lat, lon], {draggable: true}).addTo(placeMapInstance);

        // Kliknięcie na mapę
        placeMapInstance.on('click', function(e) {
            var lat = e.latlng.lat;
            var lon = e.latlng.lng;
            placeMarker.setLatLng([lat, lon]);
            updateFormFields(lat, lon);
        });

        // Przeciągnięcie pinezki
        placeMarker.on('dragend', function(e) {
            var lat = placeMarker.getLatLng().lat;
            var lon = placeMarker.getLatLng().lng;
            updateFormFields(lat, lon);
        });
    }

    function updateFormFields(lat, lon) {
        var latInput = document.querySelector('input[wire\\:model="data.latitude"]') || 
                      document.querySelector('input[name="latitude"]');
        var lonInput = document.querySelector('input[wire\\:model="data.longitude"]') || 
                      document.querySelector('input[name="longitude"]');
        
        if (latInput) {
            latInput.value = lat.toFixed(6);
            latInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (lonInput) {
            lonInput.value = lon.toFixed(6);
            lonInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function updateMapFromForm() {
        if (placeMapInstance && placeMarker) {
            var coords = getFormLatLon();
            placeMarker.setLatLng([coords[0], coords[1]]);
            placeMapInstance.setView([coords[0], coords[1]], 13);
        }
    }

    // Inicjalizacja mapy
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initPlaceMap, 100);
        
        // Obserwuj zmiany w polach latitude i longitude
        document.addEventListener('input', function(e) {
            if (e.target.name === 'latitude' || e.target.name === 'longitude') {
                setTimeout(updateMapFromForm, 100);
            }
        });
    });
</script>
