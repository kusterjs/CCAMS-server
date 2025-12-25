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

$geojson = __DIR__.'/data/geojson/Boundaries_dissolved.geojson';
$data = json_decode(file_get_contents($geojson), true);
$multiPolygon = $data['features'];
$topsky = [];

foreach ($multiPolygon as $featureIndex => $feature) {
    foreach ($feature['geometry']['coordinates'] as $polygonIndex => $polygon) {

        foreach ($polygon as $ringIndex => $ring) {
            if ($ringIndex==0) {
                $topsky[] = "AREA:SIERRA\n".($polygonIndex>0 ? '_'.$polygonIndex : '');
                $topsky[] = "MODE_S\n";
            } else {
                $topsky[] = "AREA:SIERRA_EXCLUSION\n";
            }

            foreach ($ring as [$lon, $lat]) {
                $topsky[] = decimalToDms($lat, 'lat')."\t".decimalToDms($lon, 'lon')."\n";
            }
            $topsky[] = "\n";
        }
    }
}

file_put_contents(__DIR__.'/data/topsky/modes.txt', $topsky);


?>