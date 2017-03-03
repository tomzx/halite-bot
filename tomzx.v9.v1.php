<?php

require_once 'hlt.php';
require_once 'networking.php';

$args = isset($argv[1]) ? json_decode(base64_decode($argv[1]), true) : [];

$id = $args['ID'] ?? 'unknown';
$minimumStrength = $args['MINIMUM_STRENGTH'] ?? 31;

list($myID, $gameMap) = getInit();
sendInit('tomzx.v9.v1.'.$id);

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

                // Check surrounding for weakest AND not owned by us
                $desiredDirection = STILL;
                $mostAttractive = 0;
                $attackSite = null;
                for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    $testSite = $gameMap->getSite($testLocation, $testDirection);

                    if ($testSite->owner === $myID) {
                        continue;
                    } else {
                        $attractiveness = $testSite->production / ($testSite->strength ?: 1);
                        if ($mostAttractive < $attractiveness) {
                            $mostAttractive = $attractiveness;
                            $desiredDirection = $testDirection;
                            $attackSite = $testSite;
                        }
                    }
                }

                // If we do not have any attack site, it means we own all surrounding sites, then go to the next step
                if ($attackSite) {
                    if ($attackSite->owner === 0) {
                        // If we're stronger, we can make our move
                        // If we're weaker, it's better to wait than to attack and wait
                        if ($attackSite->strength < $site->strength) {
                            $moves[] = new Move($testLocation, $desiredDirection);
                        }
                    } else {
                        // If the attack site is owned by another player, we accept to neutralize it vs owning it
                        // This is due to the fact that based on production, it might become impossible for our test
                        // site to win against the target site
                        if ($attackSite->strength < $site->strength) {
                            $moves[] = new Move($testLocation, $desiredDirection);
                        }
                    }
                    continue;
                }

                // If we're weaker, it's better to wait than to attack and wait
                // But if we do not have any attack site (it means we own all surrounding sites, then go to the next step)
                // if ($attackSite && $attackSite->strength > $site->strength) {
                //     continue;
                // }

                // No adjacent piece is unowned, try to find the closest piece
                if ($site->strength > $minimumStrength) {
                    if ($desiredDirection === STILL) {
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
