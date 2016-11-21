<?php

// Strong pieces still merge with one another

require_once 'hlt.php';
require_once 'networking.php';

list($myID, $gameMap) = getInit();
sendInit('tomzx.v4.'.date('Y/m/d.H:i:s'));

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
                $weakestDirection = rand(1, 4);
                $weakestValue = 255;
                for ($testDirection = 1; $testDirection < 5; ++$testDirection) {
                    $testSite = $gameMap->getSite($testLocation, $testDirection);

                    if ($testSite->owner === $myID) {
                        continue;
                    }

                    if ($weakestValue > $testSite->strength) {
                        $weakestDirection = $testDirection;
                        $weakestValue = $testSite->strength;
                    }
                }

                $moves[] = new Move($testLocation, $weakestDirection);
            }
        }
    }
    sendFrame($moves);
}
