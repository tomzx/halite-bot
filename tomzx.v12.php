<?php

require_once 'hlt.php';
require_once 'networking.php';

$args = isset($argv[1]) ? json_decode(base64_decode($argv[1]), true) : [];

$id = $args['ID'] ?? 'unknown';
$minimumStrength = $args['MINIMUM_STRENGTH'] ?? 31;

list($myID, $gameMap) = getInit();
sendInit('tomzx.v12.' . $id);

function writeLog($message)
{
    file_put_contents('v12.log', $message.PHP_EOL, FILE_APPEND);
}

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
                    // Do nothing
                    continue;
                }

                // Check surrounding for weakest AND not owned by us
                $desiredDirection = STILL;
                $mostAttractive = 0;
                $attackSite = null;
                for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    $targetLocation = $gameMap->getLocation($testLocation, $testDirection);
                    $targetSite = $gameMap->getSite($targetLocation);

                    if ($targetSite->owner === $myID) {
                        continue;
                    } else {
                        $attractiveness = $targetSite->production / ($targetSite->strength ?: 1);
                        if ($mostAttractive < $attractiveness) {
                            $mostAttractive = $attractiveness;
                            $desiredDirection = $testDirection;
                            $attackSite = $targetSite;
                        }
                    }
                }

                // If we're weaker, it's better to wait than to attack and wait
                // But if we do not have any attack site (it means we own all surrounding sites, then go to the next step)
                if ($attackSite && $attackSite->strength > $site->strength) {
                    continue;
                }

                // Wait until we're strong enough to start moving around
                if ($site->strength <= $minimumStrength) {
                    continue;
                }

                // No adjacent piece is unowned, find the cheapest production site as we're likely
                // to move again next iteration
                if ($desiredDirection === STILL) {
                    $leastProductionDirection = STILL;
                    $leastProductionValue = 255*max($_width, $_height);
                    // writeLog('Testing ('.$x.', '.$y.')');
                    // // TODO(tom@tomrochette.com): If multiple directions have the same production, we need a second
                    // // metric to decide which one to pick, for now the first to be the lowest wins!
                    // for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    //     $targetLocation = $gameMap->getLocation($testLocation, $testDirection);
                    //     $targetSite = $gameMap->getSite($targetLocation);
                    //     writeLog('    '.$testDirection.' production: '.$targetSite->production);
                    //
                    //     if ( ! $leastProductionSite) {
                    //         $leastProductionSite = $targetSite;
                    //         $leastProductionDirection = $testDirection;
                    //     } elseif ($leastProductionSite->production > $targetSite->production) {
                    //         $leastProductionSite = $targetSite;
                    //         $leastProductionDirection = $testDirection;
                    //     }
                    // }
                    //
                    // $desiredDirection = $leastProductionDirection;
                    // writeLog('Selected direction '.$desiredDirection);

                    for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                        $targetLocation = $testLocation;
                        $directionTotalProduction = 0;
                        $distance = 1;
                        while ($distance < $_width && $distance < $_height) {
                            // $testLocations[$testDirection] = $gameMap->getLocation($testLocations[$testDirection], $testDirection);
                            $targetLocation = $gameMap->getLocation($targetLocation, $testDirection);
                            $targetSite = $gameMap->getSite($targetLocation);


                            // Not us anymore
                            if ($targetSite->owner !== $myID) {
                                break;
                            }

                            $directionTotalProduction += $targetSite->production;

                            // writeLog('    '.$testDirection.' production ... '.$directionTotalProduction . ' '.$targetLocation->x .', '. $targetLocation->y.' '.$targetSite->owner);

                            // We're already over the current least productive path, we won't do better in this direction
                            if ($directionTotalProduction > $leastProductionValue) {
                                break;
                            }

                            // if ($targetSite->owner === $myID) {
                            //     continue;
                            // } else {
                            //     $attractiveness = $testSite->production / ($testSite->strength ?: 1);
                            //     if ($mostAttractive < $attractiveness) {
                            //         $mostAttractive = $attractiveness;
                            //         $desiredDirection = $testDirection;
                            //     }
                            // }
                            ++$distance;
                        }

                        // writeLog('    '.$testDirection.' production: '.$directionTotalProduction);

                        if ($directionTotalProduction < $leastProductionValue) {
                            $leastProductionValue = $directionTotalProduction;
                            $leastProductionDirection = $testDirection;
                        }
                    }

                    $desiredDirection = $leastProductionDirection;
                    // writeLog('Selected direction '.$desiredDirection);
                }

                $moves[] = new Move($testLocation, $desiredDirection);
            }
        }
    }
    sendFrame($moves);
}
