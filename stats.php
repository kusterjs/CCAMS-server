<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>CCAMS statistics</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.6.2/dist/chart.js"></script>
<!--<link rel="stylesheet" href="style.css">-->
</head>
<body>

	<select name="date" id="date">
	</select>
	<br />

	<table>
		<tr>
			<td><div style="width: 800px"><canvas id="designatorChart"></canvas></div></td>
			<td><div style="width: 800px"><canvas id="facilityChart"></canvas></div></td>
		</tr>
		<tr>
			<td><div style="width: 800px"><canvas id="timeChart"></canvas></div></td>
			<td><div style="width: 800px"><canvas id="versionChart"></canvas></div></td>
		</tr>
		<tr>
			<td><div style="width: 800px"><canvas id="dailyRequestChart"></canvas></div></td>
			<td></td>
		</tr>
	</table>
	
	
	<!--<canvas id="csChart" width="300"></canvas>-->
	
	
	
	
</body>
</html>
<script>
	var designatorChart = new Chart($('#designatorChart'), {
		type: 'bar',
		data: {},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});	
	var csChart = new Chart($('#csChart'), {
		type: 'bar',
		data: {},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});	
	var facilityChart = new Chart($('#facilityChart'), {
		type: 'bar',
		data: {},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});	
	var timeChart = new Chart($('#timeChart'), {
		type: 'bar',
		data: {},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});	
	var versionChart = new Chart($('#versionChart'), {
		type: 'bar',
		data: {},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});	
	var dailyRequestChart = new Chart($('#dailyRequestChart'), {
		type: 'bar',
		data: {},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});	

	$.loadDays = function() {
		$.getJSON( "json?r=logfiles", function( data ) {
			$.each( data['day'], function( range, val ) {
				$('#date').append('<option value="' + val + '">' + val + '</option>');
				//$("textarea#"+range).val(val);
			});
		});
	};
	
	$.loadStats = function(date) {
		var post = $.post("json?r=stats-daily", date);
		
		post.done( function(data) {
			designatorChart.data.datasets.pop();
			csChart.data.datasets.pop();
			facilityChart.data.datasets.pop();
			timeChart.data.datasets.pop();
			versionChart.data.datasets.pop();
			try {
				var resp = JSON.parse(data);
				
				designatorChart.data.labels = Object.keys(resp.designator);
				designatorChart.data.datasets.push({label: 'Designator', data: Object.values(resp.designator), backgroundColor: ['rgba(32,32,32,0.8)']});
				designatorChart.update();
				
/*				csChart.data.labels = Object.keys(resp.callsign);
				csChart.data.datasets.push({label: 'Call Signs', data: Object.values(resp.callsign)});
				csChart.update();
*/				
				facilityChart.data.labels = Object.keys(resp.facility);
				facilityChart.data.datasets.push({label: 'Facilities', data: Object.values(resp.facility), backgroundColor: ['rgba(32,32,32,0.8)']});
				facilityChart.update();

				timeChart.data.labels = Object.keys(resp.hour);
				timeChart.data.datasets.push({label: 'Hour (UTC)', data: Object.values(resp.hour), backgroundColor: ['rgba(32,32,32,0.8)']});
				timeChart.update();

				versionChart.data.labels = Object.keys(resp.version);
				versionChart.data.datasets.push({label: 'Version', data: Object.values(resp.version), backgroundColor: ['rgba(32,32,32,0.8)']});
				versionChart.update();

			} catch (e) {
				alert('Statistics incomplete')
			}
		});
		
		post = $.post("json?r=stats-weekly", date);
		
		post.done( function(data) {
			dailyRequestChart.data.datasets.pop();
			try {
				var resp = JSON.parse(data);
				
				dailyRequestChart.data.labels = Object.keys(resp.date);
				dailyRequestChart.data.datasets.push({label: 'Daily Requests', data: Object.values(resp.date), backgroundColor: ['rgba(128,32,32,0.8)']});
				dailyRequestChart.update();

			} catch (e) {
				alert('Statistics incomplete')
			}
		});		
	};

	$().ready( function() {
		$( function() {
			$.loadDays();
			$.loadStats({date: 'today'});
		});
//		setInterval( function() {
//			$.loadRanges();
//		}, 5000);
		$('#date').change (function () {
			$.loadStats($('#date'));
			//$.loadRanges();
		});


		/*$('#statsForm').submit( function (event) {
			$.loadStats(event);
			
			/*event.preventDefault();

			var posting = $.post( "json?r=stats", $('#statsForm').serialize());
			
			posting.done( function (data) {
				//alert('Update successful');
				//var c1 = data;
				//alert(data['callsign']);
				//myChart.data.labels = data.get('callsign');
				var resp = JSON.parse(data);
				console.log(Object.keys(resp.callsign));
				console.log(Object.values(resp.callsign));
				myChart.data.labels = Object.keys(resp.callsign);
				myChart.data.datasets.pop();
				myChart.data.datasets.push({label: 'Call Signs', data: Object.values(resp.callsign)});
				myChart.update();
				//myChart.data.datasets.data = [12, 19, 3, 5, 2, 3];
				//$.loadRanges();
				//$('#result').empty().append(data);
			})*/
		//});
	});
	

</script>