<?php

function decimalToDms(float $value, string $type): string
{
    $hemisphere = match ($type) {
        'lat' => $value >= 0 ? 'N' : 'S',
        'lon' => $value >= 0 ? 'E' : 'W',
        default => throw new InvalidArgumentException("Invalid type")
    };

    $value = abs($value);

    // 1 degree = 3600 seconds
    $totalSeconds = round($value * 3600, 3);

    // Decompose
    $deg = floor($totalSeconds / 3600);
    $remaining = $totalSeconds - ($deg * 3600);

    $min = floor($remaining / 60);
    $sec = $remaining - ($min * 60);

    return sprintf(
        '%s%03d.%02d.%06.3f',
        $hemisphere,
        $deg,
        $min,
        $sec
    );
}

function geojsonToTopSkyArea($geojson, $areaNameprefix, $isModeS = true): array
{
    $data = json_decode(file_get_contents($geojson), true);
    $multiPolygon = $data['features'];
    $topsky = [];
    
    if (json_last_error() !== JSON_ERROR_NONE) {
		throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
	} else {
        foreach ($multiPolygon as $featureIndex => $feature) {
            foreach ($feature['geometry']['coordinates'] as $polygonIndex => $polygon) {
                foreach (array_reverse($polygon) as $ringIndex => $ring) {
                    if (($ringIndex==count($polygon)-1)==$isModeS) {
                        $topsky[] = "AREA:".$areaNameprefix.($polygonIndex>0 ? '_'.$polygonIndex+1 : '')."\n";
                        $topsky[] = "MODE_S\n";
                    } else {
                        $topsky[] = "AREA:".$areaNameprefix."_EXCLUSION".($polygonIndex>0 ? '_'.$polygonIndex+1 : '').('_'.$ringIndex+1)."\n";
                    }

                    foreach ($ring as [$lon, $lat]) {
                        $topsky[] = decimalToDms($lat, 'lat')." ".decimalToDms($lon, 'lon')."\n";
                    }
                    $topsky[] = "\n";
                }
            }
        }
    }
    return $topsky;
}

/**
 * Build filtered prefixes and include unmatched candidates.
 *
 * @param string[] $candidates  Codes used to generate prefix candidates
 * @param string[] $airports    Codes used to invalidate prefixes
 * @return string[]
 */
function buildFilteredPrefixes(array $candidates, array $airports): array
{
    $prefixes = fn(array $codes, int $len) =>
        array_values(array_unique(array_map(
            fn($c) => substr($c, 0, $len),
            $codes
        )));

    // Prefix candidates
    $p2 = $prefixes($candidates, 2);
    $p3 = $prefixes($candidates, 3);

    // Used prefixes from airports
    $used2 = [];
    $used3 = [];
    foreach ($airports as $a) {
        $used2[substr($a, 0, 2)] = true;
        $used3[substr($a, 0, 3)] = true;
    }

    // Filtered 2-letter prefixes
    $f2 = array_values(array_diff($p2, array_keys($used2)));

    // Filtered 3-letter prefixes
    $f3 = array_values(array_filter(
        $p3,
        fn($p) =>
            !isset($used3[$p]) &&
            !array_filter($f2, fn($p2) => str_starts_with($p, $p2))
    ));

    // Add candidates that start with neither f2 nor f3
    $unmatchedCandidates = array_values(array_filter(
        $candidates,
        function (string $c) use ($f2, $f3): bool {
            foreach ($f2 as $p2) {
                if (str_starts_with($c, $p2)) {
                    return false;
                }
            }
            foreach ($f3 as $p3) {
                if (str_starts_with($c, $p3)) {
                    return false;
                }
            }
            return true;
        }
    ));

    // Combine and sort
    $result = array_merge($f2, $f3, $unmatchedCandidates);
    sort($result, SORT_STRING);

    return $result;
}


// TopSky plugin Mode S area definitions
$topsky = array_merge(geojsonToTopSkyArea(__DIR__.'/data/geojson/Boundaries_dissolved.geojson', 'SIERRA'), geojsonToTopSkyArea(__DIR__.'/data/geojson/Encompassing.geojson', 'SIERRA_ENCOMPASSING', false));

// TopSky plugin Mode S airport inclusion and exclusion lists
foreach (file(__DIR__.'/data/VATSpy.dat', FILE_IGNORE_NEW_LINES) as $vatspy) {
    $parts = explode('|', $vatspy);
    if (count($parts)==7 && substr($vatspy, 0, 1)!=';') {
        $vatspy_apt[] = $parts;
    }
}

$filter = file(__DIR__.'/data/config.txt', FILE_IGNORE_NEW_LINES);

foreach ($vatspy_apt as $apt) {
    if ($apt[6]) {
        // pseudo airport, exclude
    } else if (preg_match('/^'.$filter[1].'/', $apt[0])) {
        $match['ireg'][] = $apt[0];
    } else if (preg_match('/^'.$filter[0].'/', $apt[0])) {
        $match['reg'][] = $apt[0];
    } else {
        $match['nreg'][] = $apt[0];
    }
}

if (!empty($apt_regm = buildFilteredPrefixes($match['reg'], $match['nreg']))) {
    $topsky[] = "MODE_S_AIRPORTS:".implode(',', $apt_regm)."\n\n";
}
if (!empty($apt_iregm = buildFilteredPrefixes($match['ireg'], $match['reg']))) {
    $topsky[] = "MODE_S_AIRPORTS_EXCLUDE:".implode(',', $apt_iregm)."\n\n";
}

file_put_contents(__DIR__.'/data/topsky/modes.txt', $topsky);


?>