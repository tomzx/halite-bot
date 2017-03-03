<?php

// Moves too frequently, wasting precious strengthening cycles

require_once 'hlt.php';
require_once 'networking.php';

$args = isset($argv[1]) ? json_decode(base64_decode($argv[1]), true) : [];

$id = $args['ID'] ?? 'unknown';
$minimumStrength = $args['MINIMUM_STRENGTH'] ?? 128;

list($myID, $gameMap) = getInit();
sendInit('tomzx.v8.'.$id);

// Wait until the last moment to move, this is to prevent pieces that recently were moved from being reset
while (true) {
    $moves = [];
    $gameMap = getFrame();
    for ($y = 0; $y < $gameMap->height; ++$y) {
        for ($x = 0; $x < $gameMap->width; ++$x) {
            $testLocation = new Location($x, $y);
            $site = $gameMap->getSite($testLocation);
            if ($site->owner === $myID) {
                if ($site->strength === 0) {
                    $moves[] = new Move($testLocation, STILL);
                    continue;
                }

                // Check surrounding for weakest AND not owned
                $desiredDirection = 0;
                $mostAttractive = 0;
                for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    $testSite = $gameMap->getSite($testLocation, $testDirection);

                    if ($testSite->owner === $myID) {
                        continue;
                    } else {
                        $attractiveness = $testSite->production / ($testSite->strength ?: 1);
                        if ($mostAttractive < $attractiveness) {
                            $mostAttractive = $attractiveness;
                            $desiredDirection = $testDirection;
                        }
                    }
                }

                // No adjacent piece is unowned, try to find the closest piece
                if ($site->strength > $minimumStrength) {
                    if ($desiredDirection === 0) {
                        $distance = 1;
                        $testLocations = [];
                        for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                            $testLocations[$testDirection] = $testLocation;
                        }
                        while ($distance < $_width && $distance < $_height) {
                            for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                                $testLocations[$testDirection] = $gameMap->getLocation($testLocations[$testDirection], $testDirection);
                                $testSite = $gameMap->getSite($testLocations[$testDirection]);

                                if ($testSite->owner === $myID) {
                                    continue;
                                } else {
                                    $attractiveness = $testSite->production / ($testSite->strength ?: 1);
                                    if ($mostAttractive < $attractiveness) {
                                        $mostAttractive = $attractiveness;
                                        $desiredDirection = $testDirection;
                                    }
                                }
                            }

                            if ($desiredDirection !== 0) {
                                break;
                            }

                            ++$distance;
                        }
                    }

                    if ($desiredDirection === 0) {
                        $desiredDirection = rand(1, 4);
                    }
                }

                $moves[] = new Move($testLocation, $desiredDirection);
            }
        }
    }
    sendFrame($moves);
}
