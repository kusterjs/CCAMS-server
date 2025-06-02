<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>CCAMS statistics</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<!--<link rel="stylesheet" href="style.css">-->
</head>
<body>

	<select name="date" id="date">
	</select>
	<br />

	<table>
		<tr>
			<td><div style="width: 800px; height: 800px;"><canvas id="designatorChart"></canvas></div></td>
			<td><table>
				<tr>
					<td colspan="2"><div style="width: 800px"><canvas id="timeChart"></canvas></div></td>
				</tr>
				<tr>
					<td><div style="width: 400px"><canvas id="facilityChart"></canvas></div></td>
					<td><div style="width: 400px"><canvas id="clientChart"></canvas></div></td>
				</tr>
			</table></td>
		</tr>
		<tr>
			<td><div style="width: 800px"><canvas id="weekRequestChart"></canvas></div></td>
			<td><div style="width: 800px"><canvas id="monthRequestChart"></canvas></div></td>
		</tr>
	</table>
	
	<div id="debug" hidden="true"></div>
	<!--<canvas id="csChart" width="300"></canvas>-->
	
	
	
	
</body>
</html>
<script>
	Chart.register(ChartDataLabels);

	var designatorChart = new Chart($('#designatorChart'), {
		type: 'bar',
		data: {
			datasets: [
				{
					label: 'Refused',
					backgroundColor: ['rgba(128,64,64,0.8)']
				},
				{
					label: 'Accepted',
					backgroundColor: ['rgba(64,128,64,0.8)']
				}
			]
		},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Designators'
				},
				legend: false,
				datalabels: false,
				tooltip: {
					callbacks: {
						label: function(context) {
							const label = context.dataset.label || '';
							const value = Math.abs(context.parsed.x); // 'x' for horizontal bars; use 'y' for vertical
							return `${label}: ${value}`;
						}
					}
				}
			},
			responsive: true,
			maintainAspectRatio: false,
			indexAxis: 'y',
			scales: {
				x: {
					stacked: true,
					beginAtZero: true
				},
				y: {
					stacked: true,
					ticks: {
						autoSkip: false
					}
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
		type: 'doughnut',
		data: {},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Facilities'
				},
				datalabels: false
			}
		}
	});	
	var timeChart = new Chart($('#timeChart'), {
		type: 'bar',
		data: {
			datasets: [
				{
					label: 'Refused',
					backgroundColor: ['rgba(128,64,64,0.8)']
				},
				{
					label: 'Accepted',
					backgroundColor: ['rgba(64,128,64,0.8)']
				}
			]
		},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Time (hour UTC)'
				},
				legend: false,
				datalabels: false,
				tooltip: {
					callbacks: {
						title: function(context) {
							return `Hour ${context[0].label}`;
						},
						label: function(context) {
							const label = context.dataset.label || '';
							const value = Math.abs(context.parsed.y); // 'x' for horizontal bars; use 'y' for vertical
							return `${label}: ${value}`;
						}
					}
				}
			},
			responsive: true,
			scales: {
				x: {
					stacked: true,
					beginAtZero: true,
					ticks: {
						autoSkip: false
					}
				},
				y: {
					stacked: true
				}
			}
		}
	});	
	var clientChart = new Chart($('#clientChart'), {
		type: 'doughnut',
		data: {},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Clients'
				},
				datalabels: {
					display: false,
					color: 'black',
					anchor: 'center',
					align: 'center',
					formatter: function(value, context) {
						// context.chart.data.labels contains all labels
						const label = context.chart.data.labels[context.dataIndex];
						return `${label}: ${value}`;
					}
				}
			}
		},
		plugins: [ChartDataLabels]
	});	
	var weekRequestChart = new Chart($('#weekRequestChart'), {
		type: 'bar',
		data: {
			datasets: [
				{
					label: 'Refused',
					backgroundColor: ['rgba(128,64,64,0.8)']
				},
				{
					label: 'Accepted',
					backgroundColor: ['rgba(64,128,64,0.8)']
				}
			]
		},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Current week (per day)'
				},
				legend: false,
				datalabels: false,
				tooltip: {
					callbacks: {
						label: function(context) {
							const label = context.dataset.label || '';
							const value = Math.abs(context.parsed.y); // 'x' for horizontal bars; use 'y' for vertical
							return `${label}: ${value}`;
						}
					}
				}
			},
			responsive: true,
			scales: {
				x: {
					stacked: true,
					beginAtZero: true,
					ticks: {
						autoSkip: false
					}
				},
				y: {
					stacked: true
				}
			}
		}
	});	
	var monthRequestChart = new Chart($('#monthRequestChart'), {
		type: 'bar',
		data: {
			datasets: [
				{
					label: 'Refused',
					backgroundColor: ['rgba(128,64,64,0.8)']
				},
				{
					label: 'Accepted',
					backgroundColor: ['rgba(64,128,64,0.8)']
				}
			]
		},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Current month (per day)'
				},
				legend: false,
				datalabels: false,
				tooltip: {
					callbacks: {
						label: function(context) {
							const label = context.dataset.label || '';
							const value = Math.abs(context.parsed.y); // 'x' for horizontal bars; use 'y' for vertical
							return `${label}: ${value}`;
						}
					}
				}
			},
			responsive: true,
			scales: {
				x: {
					stacked: true,
					beginAtZero: true,
					ticks: {
						autoSkip: false
					}
				},
				y: {
					stacked: true
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
		var data = {stats: 'daily', date: $('#date').val(), debug: false};
		if ($('#debug').val() != '') data.debug = true;
		
		var post = $.post("json?r=stats-daily", data);
		
		post.done( function(data) {
			// designatorChart.data.datasets.pop();
			csChart.data.datasets.pop();
			facilityChart.data.datasets.pop();
			// timeChart.data.datasets.pop();
			clientChart.data.datasets.pop();
			try {
				var resp = JSON.parse(data);
				
				designatorChart.data.labels = Object.keys(resp[0].designator);
				designatorChart.data.datasets[0].data = Object.values(resp[1].designator).map(n => n * -1);
				designatorChart.data.datasets[1].data = Object.values(resp[2].designator);
				// designatorChart.data.datasets.forEach(dataset => {dataset.data = }
					// {label: 'Designator', data: Object.values(resp[1].designator), backgroundColor: ['rgba(32,32,32,0.8)']});
				designatorChart.update();
				
/*				csChart.data.labels = Object.keys(resp[1].callsign);
				csChart.data.datasets.push({label: 'Call Signs', data: Object.values(resp[1].callsign)});
				csChart.update();
*/				
				facilityChart.data.labels = Object.keys(resp[0].facility);
				facilityChart.data.datasets.push({data: Object.values(resp[2].facility)});
				facilityChart.update();

				timeChart.data.labels = Object.keys(resp[0].hour);
				timeChart.data.datasets[0].data = Object.values(resp[1].hour).map(n => n * -1);
				timeChart.data.datasets[1].data = Object.values(resp[2].hour);
				timeChart.update();

				clientChart.data.labels = Object.keys(resp[0].client);
				clientChart.data.datasets.push({data: Object.values(resp[2].client)});
				clientChart.update();

			} catch (e) {
				alert('Daily statistics incomplete')
			}
		});
		
		post = $.post("json?r=stats-weekly", date);
		
		post.done( function(data) {
			// weekRequestChart.data.datasets.pop();
			try {
				var resp = JSON.parse(data);
				
				weekRequestChart.data.labels = Object.keys(resp[0].date);
				weekRequestChart.data.datasets[0].data = Object.values(resp[1].date).map(n => n * -1);
				weekRequestChart.data.datasets[1].data = Object.values(resp[2].date);
				weekRequestChart.update();

			} catch (e) {
				alert('Weekly statistics incomplete')
			}
		});		

		post = $.post("json?r=stats-monthly", date);
		
		post.done( function(data) {
			// monthRequestChart.data.datasets.pop();
			try {
				var resp = JSON.parse(data);
				
				monthRequestChart.data.labels = Object.keys(resp[0].date);
				monthRequestChart.data.datasets[0].data = Object.values(resp[1].date).map(n => n * -1);
				monthRequestChart.data.datasets[1].data = Object.values(resp[2].date);
				monthRequestChart.update();

			} catch (e) {
				alert('Montly statistics incomplete')
			}
		});		
	};

	$().ready( function() {
		$( function() {
			$.loadDays();
			$.loadStats({date: 'today'});
			let searchParams = new URLSearchParams(window.location.search);
			if (searchParams.has('debug')) $('#debug').val(true);
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