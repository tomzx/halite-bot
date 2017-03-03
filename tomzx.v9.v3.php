<?php

require_once 'hlt.php';
require_once 'networking.php';

$args = isset($argv[1]) ? json_decode(base64_decode($argv[1]), true) : [];

$id = $args['ID'] ?? 'unknown';
$minimumStrength = $args['MINIMUM_STRENGTH'] ?? 32;

list($myID, $gameMap) = getInit();
sendInit('tomzx.v9.v3.' . $id);

$halfWidth = ceil($_width / 2);
$halfHeight = ceil($_height / 2);
$maxTestDistance = max($halfWidth, $halfHeight);
$totalFrames = (int)(sqrt($_width * $_height) * 10);
$players = [];

function heuristic(GameMap $gameMap, $myID, Location $location)
{
    $testSite = $gameMap->getSite($location);
    if ($testSite->owner === 0) {
        return $testSite->production / ($testSite->strength ?: 1);
    } else {
        $totalDamage = 0;
        foreach (DIRECTIONS as $testDirection) {
            /** @var Site $testSite */
            $testSite = $gameMap->getSite($location, $testDirection);
            if ($testSite->owner !== 0 && $testSite->owner !== $myID) {
                $totalDamage += $testSite->strength;
            }
        }
        return $totalDamage;
    }
}

// function surroundingStrength(Location $location)
// {
//     global $gameMap;
//     global $myID;
//     $strength = 0;
//     foreach (CARDINALS as $testDirection) {
//         $testedLocation = $gameMap->getLocation($location, $testDirection);
//         $testSite = $gameMap->getSite($testedLocation);
//
//         if ($testSite->owner === $myID) {
//             $strength += $testSite->strength;
//         }
//     }
//     return $strength;
// }
//
// function localStrategy(Location $currentLocation)
// {
//     global $gameMap;
//     global $myID;
//     // Check surrounding for weakest AND not owned by us
//     $desiredDirection = STILL;
//     $mostAttractive = 0;
//     $attackSite = null;
//     $attackLocation = null;
//     $site = $gameMap->getSite($currentLocation);
//     foreach (CARDINALS as $testDirection) {
//         $testedLocation = $gameMap->getLocation($currentLocation, $testDirection);
//         $testSite = $gameMap->getSite($testedLocation);
//
//         if ($testSite->owner === $myID) {
//             continue;
//         } else {
//             $attractiveness = heuristic($gameMap, $myID, $testedLocation);
//             if ($mostAttractive < $attractiveness) {
//                 $mostAttractive = $attractiveness;
//                 $desiredDirection = $testDirection;
//                 $attackSite = $testSite;
//                 $attackLocation = $testedLocation;
//             }
//         }
//     }
//
//     // If we do not have any attack site, it means we own all surrounding sites, then wait
//     if ($attackLocation) {
//         $localStrength = surroundingStrength($attackLocation);
//
//         if ($attackSite->owner === 0) {
//             // If we're stronger, we can make our move
//             // If we're weaker, it's better to wait than to attack and wait
//             if ($localStrength > $attackSite->strength) {
//                 // $attackSite->strength -= $site->strength;
//                 return new Move($currentLocation, $desiredDirection);
//             }
//         } else {
//             // If the attack site is owned by another player, we accept to neutralize it vs owning it
//             // This is due to the fact that based on production, it might become impossible for our test
//             // site to win against the target site
//             if ($localStrength >= $attackSite->strength) {
//                 // $attackSite->strength -= $site->strength;
//                 return new Move($currentLocation, $desiredDirection);
//             }
//         }
//         // if ($attackSite->owner === 0) {
//         //     // If we're stronger, we can make our move
//         //     // If we're weaker, it's better to wait than to attack and wait
//         //     if ($attackSite->strength < $site->strength) {
//         //         return new Move($currentLocation, $desiredDirection);
//         //     }
//         // } else {
//         //     // If the attack site is owned by another player, we accept to neutralize it vs owning it
//         //     // This is due to the fact that based on production, it might become impossible for our test
//         //     // site to win against the target site
//         //     if ($attackSite->strength <= $site->strength) {
//         //         return new Move($currentLocation, $desiredDirection);
//         //     }
//         // }
//         return new Move($currentLocation, STILL);
//     }
// }

function globalStrategy()
{
}

function offensiveStrategy(Location $currentLocation)
{
    global $gameMap;
    global $maxTestDistance;
    global $myID;
    $distance = 1;
    $testLocations = [];
    $desiredDirection = STILL;
    $mostAttractive = 0;
    foreach (CARDINALS as $testDirection) {
        $testLocations[$testDirection] = $currentLocation;
    }
    while ($distance <= $maxTestDistance) {
        foreach (CARDINALS as $testDirection) {
            $testLocations[$testDirection] = $gameMap->getLocation($testLocations[$testDirection], $testDirection);
            $testSite = $gameMap->getSite($testLocations[$testDirection]);

            if ($testSite->owner === $myID) {
                continue;
            } else {
                $attractiveness = heuristic($gameMap, $myID, $testLocations[$testDirection]);
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

    if ($desiredDirection === STILL) {
        return;
    }

    $site = $gameMap->getSite($currentLocation, $desiredDirection);
    // $site->strength -= $site->strength;
    return new Move($currentLocation, $desiredDirection);
}

// Minimize the amount of production spent
// Minimize the amount of strength "overused"
function getAttackerCombination(array $attackers, $requiredStrength)
{
    $productions = [];
    foreach ($attackers as $index => $attacker) {
        $productions[$index] = $attacker['site']->production;
    }
    uasort($productions, function ($a, $b) {
        return $a <=> $b;
    });
    $attackStrength = 0;
    $attackerSet = [];
    // TODO(tom@tomrochette.com): Try out more combinations
    foreach ($productions as $index => $production) {
        $attackStrength += $attackers[$index]['site']->strength;
        $attackerSet[] = $attackers[$index];
        if ($attackStrength >= $requiredStrength) {
            break;
        }
    }
    return $attackerSet;
}

// Wait until the last moment to move, this is to prevent pieces that recently were moved from being reset
$frame = 0;
while (true) {
    ++$frame;
    $moves = [];
    $gameMap = getFrame();
    $randomDirection = $frame % 3 === 0 ? NORTH : EAST;

    $myLocations = [];
    $borders = [];
    $players = [];
    for ($y = 0; $y < $gameMap->height; ++$y) {
        for ($x = 0; $x < $gameMap->width; ++$x) {
            $location = new Location($x, $y);
            $site = $gameMap->getSite($location);

            if ( ! isset($players[$site->owner])) {
                $players[$site->owner] = [
                    'production' => 0,
                    'strength'   => 0,
                    'territory'  => 0,
                ];
            }

            $players[$site->owner]['production'] += $site->production;
            $players[$site->owner]['strength'] += $site->strength;
            $players[$site->owner]['territory'] += 1;

            if ($site->owner === $myID) {
                $myLocations[] = [
                    'location' => $location,
                    'site'     => $site,
                ];
            }
        }
    }

    usort($myLocations, function ($a, $b) {
        return $b['site']->strength <=> $a['site']->strength;
    });

    foreach ($myLocations as $currentLocation) {
        $location = $currentLocation['location'];
        $site = $currentLocation['site'];
        $site->attacking = false;
        $site->hasTarget = false;
        $site->waiting = false;

        foreach (CARDINALS as $testDirection) {
            $testLocation = $gameMap->getLocation($location, $testDirection);
            $testSite = $gameMap->getSite($testLocation);

            if ($testSite->owner === $myID) {
                continue;
            }

            $coordinates = $testLocation->x . ':' . $testLocation->y;
            if ( ! isset($borders[$coordinates])) {
                $borders[$coordinates] = [
                    'site'           => $testSite,
                    'attractiveness' => heuristic($gameMap, $myID, $testLocation),
                    'attackStrength' => 0,
                ];
            }

            $borders[$coordinates]['attackers'][] = [
                'location'  => $location,
                'site'      => $site,
                'direction' => $testDirection,
            ];
            $site->hasTarget = true;
            // $borders[$coordinates]['attackStrength'] += $site->strength;
        }
    }

    uasort($borders, function ($a, $b) use ($gameMap, $myID) {
        return $b['attractiveness'] <=> $a['attractiveness'];
    });

    foreach ($borders as $border) {
        $attackStrength = 0;
        foreach ($border['attackers'] as $attacker) {
            if ($attacker['site']->waiting || $attacker['site']->attacking) {
                continue;
            }
            $attackStrength += $attacker['site']->strength;
            $attacker['site']->waiting = true;
        }

        if ($border['site']->owner === 0) {
            if ($attackStrength <= $border['site']->strength) {
                continue;
            }
        } else {
            if ($attackStrength < $border['site']->strength) {
                continue;
            }
        }

        $attackers = getAttackerCombination($border['attackers'], $border['site']->strength);

        // TODO(tom@tomrochette.com): Could be reduced to the minimum required to win the territory instead of all
        foreach ($attackers as $attacker) {
            $attacker['site']->attacking = true;
            $moves[] = new Move($attacker['location'], $attacker['direction']);
        }
    }

    $gameRatio = $frame / $totalFrames;
    foreach ($myLocations as $currentLocation) {
        $location = $currentLocation['location'];
        $site = $currentLocation['site'];
        // If the site has no strength, there's nothing we can do
        if ($site->strength === 0) {
            continue;
        }

        if ($site->attacking) {
            continue;
        }

        if ($gameRatio < 0.75) {
            if ($site->hasTarget || $site->waiting) {
                continue;
            }
        }

        // $move = localStrategy($location);
        //
        // if ($move) {
        //     $moves[] = $move;
        //     continue;
        // }
        //
        // // If we're bigger than a certain threshold
        // // Find the most attractive site the closest to us
        if ($site->strength > $minimumStrength * (1 - $gameRatio)) {
            $move = offensiveStrategy($location);

            if ($move) {
                $moves[] = $move;
            } else {
                // $randomDirection = rand(1, 4);
                $moves[] = new Move($location, $randomDirection);
            }
        }
    }
    sendFrame($moves);
}
