<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>CCAMS Dashboard</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
//		setInterval( function() {
//			$.loadRanges();
//		}, 5000);
		$('#reload').click( function() {
			$.loadRanges();
		});

		$( function() {
			$.loadResCodes();
		});
		setInterval( function() {
			$.loadResCodes();
		}, 5000);
//		$('#reload').click( function() {
//			$.loadJSON();
//		});
	});	

</script>	
</head>
<body>
	
	<table>
		<tr>
			<td>FIR code ranges</td>
			<td>Airport code ranges</td>
			<td>Currently reserved codes</td>
		</tr>
		<tr>
			<td><textarea id="FIR" name="FIR" rows="40" cols="25" title="FIR codes" placeholder="NIL" disabled></textarea></td>
			<td><textarea id="APT" name="APT" rows="40" cols="25" title="Airport codes" placeholder="NIL" disabled></textarea></td>
			<td><textarea id="reserved_codes" name="codes" rows="40" cols="40" title="Currently reserved codes" placeholder="NIL" disabled></textarea></td>
		</tr>
	</table>
	<button id="reload">Reload</button>
	<br>
	<p>Check out the usage <a href="statistics" target="_blank">statistics</a></p>

</body>
</html>