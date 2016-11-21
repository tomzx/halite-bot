<?php

// Does not take into account production

require_once 'hlt.php';
require_once 'networking.php';

list($myID, $gameMap) = getInit();
sendInit('tomzx.v6.'.date('Y/m/d.H:i:s'));

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
                $weakestDirection = 0;
                $weakestValue = 255;
                for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    $testSite = $gameMap->getSite($testLocation, $testDirection);

                    if ($testSite->owner === $myID) {
                        continue;
                    } else {
                        if ($weakestValue > $testSite->strength) {
                            $weakestDirection = $testDirection;
                            $weakestValue = $testSite->strength;
                        }
                    }
                }

                // No adjacent piece is unowned, try to find the closest piece
                if ($weakestDirection === 0) {
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
                                if ($weakestValue > $testSite->strength) {
                                    $weakestDirection = $testDirection;
                                    $weakestValue = $testSite->strength;
                                }
                            }
                        }

                        if ($weakestDirection !== 0) {
                            break;
                        }

                        ++$distance;
                    }
                }

                if ($weakestDirection === 0) {
                    $weakestDirection = rand(1, 4);
                }

                $moves[] = new Move($testLocation, $weakestDirection);
            }
        }
    }
    sendFrame($moves);
}
