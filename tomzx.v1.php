<?php

// Limited to a single column

require_once 'hlt.php';
require_once 'networking.php';

list($myID, $gameMap) = getInit();
sendInit('tomzx.v1.'.date('Y/m/d.H:i:s'));

while (true) {
    $moves = [];
    $gameMap = getFrame();
    for ($y = 0; $y < $gameMap->height; ++$y) {
        for ($x = 0; $x < $gameMap->width; ++$x) {
            $site = $gameMap->getSite(new Location($x, $y));
            if ($site->owner === $myID) {
                if ($site->strength === 0) {
                    $moves[] = new Move(new Location($x, $y), STILL);
                    continue;
                }

                $moves[] = new Move(new Location($x, $y), NORTH);
            }
        }
    }
    sendFrame($moves);
}
