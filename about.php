<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>About</title>

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
<div class="container my-4">

  <!-- Textareas -->
  <div class="card mb-4 p-4 shadow-sm">
    <h4>About</h4>
    <div class="row">
      <div class="col-md-12 mb-4">
		This project is developed and maintained by Jonas Kuster<br>
		Visit the <a href="https://github.com/kusterjs/CCAMS-server" target="_blank">GitHub</a> repo for documentation, raising issues and contributions<br>
		For any other feedback, ideas, etc. and private messages please use the <a href="https://forum.vatsim.net/t/ccams-centralised-code-assignment-and-management-system-plugin/2259" target="_blank">VATSIM Forum</a>
      </div>
	</div>
  </div>

</div>

<!-- Footer -->
<footer class="bg-light text-center text-muted py-3">
  Provided and maintained by Jonas Kuster
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- Ajax -->
<script>
	$.loadRanges = function() {
		$.getJSON( "json?r=get-ranges", function( data ) {
			$.each( data, function( range, val ) {
				$("textarea#"+range).val(val);
			});
		});
	};
	
	$.loadResCodes = function() {
		$.getJSON( "json?r=squawks", function( data ) {
			$("textarea#reserved_codes").val(data);
		});
	};
	
	$().ready( function() {
		$( function() {
			$.loadRanges();
		});
		$('#reload').click( function() {
			$.loadRanges();
		});

		$( function() {
			$.loadResCodes();
		});
		setInterval( function() {
			$.loadResCodes();
		}, 5000);
	});	

</script>

</body>
</html>