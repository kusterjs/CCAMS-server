<?php

	if (array_key_exists('debug',$_GET)) {
		error_reporting(E_ALL);
		echo realpath(__FILE__).'<br>';
	}
    
	// file copy curl function
    function curl_copy($orig, $file = '') {
		if ($file=='') $file = pathinfo($orig, PATHINFO_BASENAME);
		$dest = __DIR__.'/data/'.$file;
		if (!($fp = fopen($dest,'w'))) return false;
		if (!($curl = curl_init($orig))) return false;
		if (!curl_setopt($curl, CURLOPT_FILE, $fp)) return false;

		if (!curl_exec($curl)) return false;
		curl_close($curl);

		if (!fclose($fp)) return false;
		return $dest;
	}

	// download files to server data folder
	if (!curl_copy('https://raw.githubusercontent.com/kusterjs/CCAMS/master/config.txt')) {
		echo 'Unable to download CCAMS config<br>';
	}

	// process FIR geojson
	$geojson = __DIR__.'/data/Boundaries.geojson';
	$pathinfo = pathinfo($geojson, PATHINFO_ALL);
	if (($data = json_decode(file_get_contents($geojson), true)) && ($filter = file(__DIR__.'/data/config.txt', FILE_IGNORE_NEW_LINES))) {	
		$filteredFeatures = [];

		foreach ($data['features'] as $feature) {
			$props = $feature['properties'];

			if (preg_match('/^'.$filter[0].'/', substr($props['id'], 0, 4)) &&
				!preg_match('/^'.$filter[1].'/', $props['id']) 
			) {
				$filteredFeatures[] = $feature;
			}
		}

		$json = json_encode([
			'type' => 'FeatureCollection',
			'name' => 'VATSIM Mode S FIR Coverage Map',
			'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84']],
			'features' => $filteredFeatures
		]);

		file_put_contents($pathinfo['dirname'].'/geojson/'.$pathinfo['filename'].'_filtered.'.$pathinfo['extension'], $json);	

		// process airports into geojson
		foreach (file(__DIR__.'/data/VATSpy.dat', FILE_IGNORE_NEW_LINES) as $vatspy) {
			$parts = explode('|', $vatspy);
			if (count($parts)==7 && substr($vatspy, 0, 1)!=';') {
				$vatspy_apt[] = $parts;
			}
		}

		$filter = file(__DIR__.'/data/config.txt', FILE_IGNORE_NEW_LINES);

		$filteredFeatures = [];

		foreach ($vatspy_apt as $apt) {
			if (preg_match('/^'.$filter[0].'/', $apt[0]) &&
				!preg_match('/^'.$filter[1].'/', $apt[0]) &&
				!$apt[6]
			) {
				$filteredFeatures[] = ['type' => 'Feature',
					'properties' => ['ICAO' => $apt[0], 
						'Name' => $apt[1],
						'FIR' => $apt[5], 
						'IATA' => $apt[4]
					],
					'geometry' => ['type' => 'Point',
						'coordinates' => [$apt[3], $apt[2]]
					]
				];
			}
		}

		$json = json_encode([
			'type' => 'FeatureCollection',
			'name' => 'VATSIM Mode S Airport Map',
			'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84']],
			'features' => $filteredFeatures
		]);

		file_put_contents(__DIR__ . '/data/geojson/Airports_filtered.geojson', $json);	
	}
	
?>
