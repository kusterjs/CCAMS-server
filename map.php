<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CCAMS Mode S Coverage Map</title>

	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

	<!-- Leaflet CSS -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

	<!-- Chart.js -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

	<!-- Leaflet JS -->
	<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

	<style>
		#map { height: 100vh; width: 100%; border-radius: 0.5rem; }
		.polygon-label {
			background: transparent;
			border: none;
			box-shadow: none;
			color: #0000ff88;
			font-size: 12px;
			pointer-events: none;
			text-align: center;
		}
	</style>
</head>
<body>

<!-- Include Navbar -->
<?php include 'navbar.php'; ?>

<!-- Main Content -->
<div class="container my-4">

  <!-- OSM Map -->
  <div class="card mb-4 p-4 shadow-sm">
    <h4>VATSIM Mode S capable Airspace and Airports</h4>
    <div class="row">
      <div class="col-md-12 mb-4">
		Download Mode S area in <a href="data/geojson/Boundaries_dissolved.geojson" download="Boundaries_ModeS.geojson">GeoJSON</a> or <a href="data/topsky/modes.txt" download>TopSky</a> format
      </div>
	</div>
    <div id="map"></div>
  </div>

</div>

<div id="debug" hidden="true"></div>

<!-- Footer -->
<footer class="bg-light text-center text-muted py-3">
  Provided and maintained by Jonas Kuster
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet Map Initialization -->
<script>
    const map = L.map('map').setView([47.0, 8.0], 7);

    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const baseLayers = {
        "OpenStreetMap": osm
    };

    const overlayLayers = {};
    const overlayBoundsLayers = []; // ONLY layers that should affect bounds

    /**
     * Helper function to load a GeoJSON layer
     */
    function loadGeoJsonLayer(url, options, name, useForBounds = true, order = 0) {
        return fetch(url)
            .then(r => r.json())
            .then(data => {
                const layer = L.geoJSON(data, options);
                overlayLayers[name] = { layer, order }; // store order with layer

                if (useForBounds && layer.getLayers().length > 0) {
                    overlayBoundsLayers.push({ layer, order });
                }

                return { name, layer, order };
            });
    }

    const layerPromises = [];

    /* ---------- Layer 0: FIRs ---------- */
    layerPromises.push(
        loadGeoJsonLayer(
            'data/Boundaries.geojson',
            {
                style: function(feature) {
                    return {
                        color: '#0000ffff',
						weight: 1,
                        fillOpacity: 0.0
                    }
                },
                onEachFeature: (feature, layer) =>
					layer.bindTooltip(
						feature.properties.id,
						{
							permanent: true,
							direction: 'center',
							className: 'polygon-label'
						}
					)
                    // layer.bindPopup(
                    //     `<strong>${feature.properties.id}</strong><br>`
                    // )
            },
            'FIR',
            false,
            10
        )
    );

    /* ---------- Layer 1: FIRs filtered ---------- */
    layerPromises.push(
        loadGeoJsonLayer(
            'data/geojson/Boundaries_filtered.geojson',
            {
                onEachFeature: (feature, layer) =>
                    layer.bindPopup(
                        `<strong>${feature.properties.id}</strong><br>`
                    )
            },
            'Mode S FIR',
            true,
            20
        )
    );

    /* ---------- Layer 2: FIRs dissolved ---------- */
    layerPromises.push(
        loadGeoJsonLayer(
            'data/geojson/Boundaries_dissolved.geojson',
            { 
                style: function(feature) {
                    return {
                        color: '#ff0000',
                        fillColor: '#ff0000',
                        fillOpacity: 0.2
                    }
                }
            },
            'Mode S FIR dissolved',
            true,
            30
        )
    );

    /* ---------- Layer 3: Airports ---------- */
    layerPromises.push(
        loadGeoJsonLayer(
            'data/geojson/Airports_filtered.geojson',
            {
                pointToLayer: (feature, latlng) =>
                    L.circleMarker(latlng, {
                        radius: 4,
                        color: '#003366',
                        fillColor: '#00000001',
                        fillOpacity: 0.8
                    }),
                onEachFeature: (feature, layer) =>
                    layer.bindPopup(
                        `<strong>${feature.properties.ICAO}` + (feature.properties.IATA.length === 0 ? `` : ` / ${feature.properties.IATA}`) + `</strong><br>` + 
                        `${feature.properties.Name}<br>` +
                        `${feature.properties.FIR}`
                    )
            },
            'Airports',
            true,
            50
        )
    );

    /* ---------- Finalize ---------- */
    Promise.all(layerPromises).then(layers => {
        // Sort by order ascending
        layers.sort((a, b) => a.order - b.order);

        // Add layers to map in sorted order
        layers.forEach(l => l.layer.addTo(map));

        // Fit bounds using only layers marked for bounds
        if (overlayBoundsLayers.length > 0) {
            const boundsGroup = L.featureGroup(
                overlayBoundsLayers
                    .sort((a, b) => a.order - b.order) // optional, same order
                    .map(o => o.layer)
            );
            map.fitBounds(boundsGroup.getBounds(), { padding: [0, 0] });
        }

        // Add layer control
        L.control.layers(baseLayers, Object.fromEntries(
            layers.map(l => [l.name, l.layer])
        ), { collapsed: false }).addTo(map);
    });

    
</script>

</body>
</html>