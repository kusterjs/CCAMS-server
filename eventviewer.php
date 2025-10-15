<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>CCAMS Event Viewer</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!--<link rel="stylesheet" href="style.css">-->
</head>
<body>

	<label for="datePicker">Date</label>
	<input type="date" name="datePicker" id="datePicker" max="">
	<br />

	<table id="logTable">
		<tr>
			<td></td>
		</tr>
	</table>
	
	<div id="debug" hidden="true"></div>
	<!--<canvas id="csChart" width="300"></canvas>-->
	
	
	
	
</body>
</html>
<script>
	const today = new Date().toISOString().split("T")[0];
	$('#datePicker').attr('max', today);
	
	$.loadLogs = function(date) {
		var data = {date: date, debug: false};
		if ($('#debug').val() != '') data.debug = true;
		
		var post = $.post("json?r=logdata", data);
		
		post.done( function(data) {
			try {
				var resp = JSON.parse(data);

				let $table = $('#logTable');
				$table.empty();

				if (resp.length > 0) {
					// Header
					let $headerRow = $('<tr/>');
					$.each(Object.keys(resp[0]), function (_, key) {
						$headerRow.append($('<th/>').text(key));
					});
					$table.append($headerRow);

					// Rows
					$.each(resp, function (_, row) {
						let $tr = $('<tr/>');
						$.each(row, function (_, val) {
							$tr.append($('<td/>').text(val));
						});
						$table.append($tr);
					});
				}

			} catch (e) {
				alert('Parsing error during log entries loading')
			}
		});
	}


	$().ready( function() {
		$( function() {
			document.getElementById("datePicker").value = today;
			$.loadLogs($('#datePicker').val());
			let searchParams = new URLSearchParams(window.location.search);
			if (searchParams.has('debug')) $('#debug').val(true);
		});

		$('#datePicker').change (function () {
			$.loadLogs($('#datePicker'));
		});
	});
	

</script>