<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CCAMS Mode S positions</title>

	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

	<!-- Custom CSS -->
	<link href="css/style.css" rel="stylesheet">

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<!-- Include Navbar -->
<?php include 'navbar.php'; ?>

<!-- Main Content -->
<div class="container mt-5">
    <h1 class="mb-4">Airport List</h1>

    <div class="mb-3">
        <input type="text" id="filterInput" class="form-control" placeholder="Type to filter (use * / ?)">
    </div>

	<div class="table-responsive">
		<table class="table table-striped table-hover">
			<thead>
				<tr>
					<th>ICAO</th>
					<th>Name</th>
					<th>FIR</th>
				</tr>
			</thead>
			<tbody id="airportTable">
				<tr><td colspan="3" class="text-center">Loading...</td></tr>
			</tbody>
		</table>
	</div>
</div>

<div id="debug" hidden="true"></div>

<!-- Footer -->
<footer class="bg-light text-center text-muted py-3">
  Provided and maintained by Jonas Kuster
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- Ajax -->
<script>
let airports = [];

// Wildcard matching function (* = any chars, ? = single char)
	function wildcardMatch(pattern, str) {
		if (!pattern) return true; // empty filter = match all
		pattern = pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&'); // escape regex
		pattern = pattern.replace(/\*/g, '.*').replace(/\?/g, '.');
		const regex = new RegExp('^' + pattern + '$', 'i');
		return regex.test(str);
	}

	// Load GeoJSON directly
	$.getJSON('data/geojson/Airports_filtered.geojson', function(data) {
		if (data.features) {
			airports = data.features.map(f => ({
				icao: f.properties.ICAO || '',
				name: f.properties.Name || '',
				fir: f.properties.FIR || ''
			}));
			renderTable(airports);
		} else {
			$('#airportTable').html('<tr><td colspan="3" class="text-center text-danger">Invalid GeoJSON</td></tr>');
		}
	});

	// Render filtered table
	function renderTable(list) {
		const tbody = $('#airportTable');
		tbody.empty();
		if (list.length === 0) {
			tbody.append('<tr><td colspan="3" class="text-center">No airports found.</td></tr>');
			return;
		}
		$.each(list, function(i, airport) {
			tbody.append(
				`<tr>
					<td>${airport.icao}</td>
					<td>${airport.name}</td>
					<td>${airport.fir}</td>
				</tr>`
			);
		});
	}

	// Live filtering with wildcard
	$('#filterInput').on('input', function() {
		const filter = $(this).val().trim();
		const filtered = airports.filter(a =>
			wildcardMatch(filter, a.icao) ||
			wildcardMatch(filter, a.name) ||
			wildcardMatch(filter, a.fir)
		);
		renderTable(filtered);
	});
</script>

</body>
</html>