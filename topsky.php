<?php

function decimalToDms(float $value, string $type): string
{
    $hemisphere = match ($type) {
        'lat' => $value >= 0 ? 'N' : 'S',
        'lon' => $value >= 0 ? 'E' : 'W',
        default => throw new InvalidArgumentException("Invalid type")
    };

    $value = abs($value);

    $deg = floor($value);
    $minFloat = ($value - $deg) * 60;
    $min = floor($minFloat);
    $sec = ($minFloat - $min) * 60;

    // round seconds to milliseconds
    $sec = round($sec, 3);

    // handle rounding overflow
    if ($sec >= 60.0) {
        $sec = 0.0;
        $min++;
    }

    if ($min >= 60) {
        $min = 0;
        $deg++;
    }

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

$topsky = ["AREA:SIERRA", "\nMODE_S"];
foreach ($multiPolygon as $featureIndex => $feature) {
    foreach ($feature['geometry']['coordinates'] as $polygonIndex => $polygon) {

        foreach ($polygon as $ringIndex => $ring) {

            foreach ($ring as [$lon, $lat]) {
                $topsky[] = "\n".decimalToDms($lat, 'lat')."\t".decimalToDms($lon, 'lon');
            }
            
        }
    }
}

file_put_contents(__DIR__.'/data/topsky/modes.txt', $topsky);


?>