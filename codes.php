<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CCAMS Codes</title>

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
    <h4>CCAMS Codes Config and Management</h4>
    <div class="row">
      <div class="col-md-4 mb-4">
		<label for="FIR">FIR code ranges</label><br>
		<textarea id="FIR" name="FIR" rows="40" cols="25" title="FIR codes" placeholder="NIL" disabled></textarea>
      </div>
      <div class="col-md-4 mb-4">
		<label for="APT">Airport code ranges</label><br>
        <textarea id="APT" name="APT" rows="40" cols="25" title="Airport codes" placeholder="NIL" disabled></textarea>
      </div>
      <div class="col-md-4 mb-4">
		<label for="Areserved_codesPT">Currently reserved codes</label><br>
		<textarea id="reserved_codes" name="codes" rows="40" cols="40" title="Currently reserved codes" placeholder="NIL" disabled></textarea>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12 mb-4">
		<button id="reload">Reload</button>
      </div>
    </div>
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