<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Modify code ranges</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
	
	
	$.loadRanges = function() {
		$.getJSON( "json?r=get-ranges", function( data ) {
			$('#ranges').val(data[$('#rangename').val()]);
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
//		setInterval( function() {
//			$.loadRanges();
//		}, 5000);
		$('#rangename').change (function () {
			$.loadRanges();
		});


		$('#rangeModifyForm').submit( function (event) {
			event.preventDefault();

			var posting = $.post( "json?r=set-ranges", $('#rangeModifyForm').serialize());
			
			posting.done( function (data) {
				alert('Update successful');
				$.loadRanges();
				//$('#result').empty().append(data);
			})
		});
	});
	

</script>	
</head>
<body>
	
	<form action="/" id="rangeModifyForm">
		<select name="rangename" id="rangename">
			<option value="FIR">FIR</option>
			<option value="APT">APT</option>
		</select>
		<br />
		<textarea id="ranges" name="ranges" rows="40" cols="30" placeholder="ICAO:0001:0077:Condition"></textarea><br />
		<button id="save">Save</button>
	</form>
	<div id="result"></div>
	
</body>
</html>