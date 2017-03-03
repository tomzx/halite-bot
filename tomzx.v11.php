<?php

require_once 'hlt.php';
require_once 'networking.php';

$args = isset($argv[1]) ? json_decode(base64_decode($argv[1]), true) : [];

$id = $args['ID'] ?? 'unknown';
$minimumStrength = $args['MINIMUM_STRENGTH'] ?? 31;

list($myID, $gameMap) = getInit();
sendInit('tomzx.v11.'.$id);

function evaluateSiteAttractiveness(GameMap $gameMap, Location $location)
{
    global $myID;
    $site = $gameMap->getSite($location);
    $strength = $site->strength;
    // $attractiveness = $site->production / ($site->strength ?: 1);
    /*for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
        $targetSite = $gameMap->getSite($location, $testDirection);
        if ($targetSite->owner !== 0 && $targetSite->owner !== $myID) {
            // Enemy site increase the site strength
            $strength += $targetSite->strength;
        } elseif ($targetSite->owner === $myID) {
            // Our sites reinforce
            $strength -= $targetSite->strength;
        }
    }*/
    $strength = $strength > 0 ? $strength : 1;
    return $site->production / $strength;
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
                $desiredDirection = 0;
                $mostAttractive = 0;
                $attackSite = null;
                for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    $targetLocation = $gameMap->getLocation($testLocation, $testDirection);
                    $targetSite = $gameMap->getSite($targetLocation);

                    if ($targetSite->owner === $myID) {
                        continue;
                    } else {
                        $attractiveness = evaluateSiteAttractiveness($gameMap, $targetLocation);
                        // $attractiveness = $targetSite->production / ($targetSite->strength ?: 1);
                        if ($mostAttractive < $attractiveness) {
                            $mostAttractive = $attractiveness;
                            $desiredDirection = $testDirection;
                            $attackSite = $targetSite;
                        }
                    }
                }

                // If we do not have any attack site, it means we own all surrounding sites, then go to the next step
                if ($attackSite) {
                    // If we're stronger, we can make our move
                    // If we're weaker, it's better to wait than to attack and wait
                    if ($attackSite->strength < $site->strength) {
                        $moves[] = new Move($testLocation, $desiredDirection);
                    }
                    continue;
                }

                // No adjacent piece is unowned, try to find the closest piece we can attack
                if ($site->strength > $minimumStrength) {
                    if ($desiredDirection === STILL) {
                        $distance = 1;
                        $targetLocations = [];
                        for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                            $targetLocations[$testDirection] = $testLocation;
                        }
                        while ($distance < $_width && $distance < $_height) {
                            for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                                $targetLocations[$testDirection] = $gameMap->getLocation($targetLocations[$testDirection], $testDirection);
                                $targetLocation = $targetLocations[$testDirection];
                                $targetSite = $gameMap->getSite($targetLocation);

                                if ($targetSite->owner === $myID) {
                                    continue;
                                } else {
                                    // $attractiveness = $targetSite->production / ($targetSite->strength ?: 1);
                                    $attractiveness = evaluateSiteAttractiveness($gameMap, $targetLocation);
                                    if ($mostAttractive < $attractiveness) {
                                        $mostAttractive = $attractiveness;
                                        $desiredDirection = $testDirection;
                                    }
                                }
                            }

                            if ($desiredDirection !== STILL) {
                                break;
                            }

                            ++$distance;
                        }
                    }

                    if ($desiredDirection === STILL) {
                        $desiredDirection = rand(1, 4);
                    }
                }

                $moves[] = new Move($testLocation, $desiredDirection);
            }
        }
    }
    sendFrame($moves);
}
