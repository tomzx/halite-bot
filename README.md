# Halite Bot

[See my current ranking](https://halite.io/user.php?userID=3128)

This is a bot for the [Halite](https://halite.io/) game, written in PHP. It was the first bot written in PHP for the game.

This repository contains an archive of the different versions/iterations of the bot and a small history of the changes that improved its success rate.

# Versions
## v1
This version is based of the template bot. The only thing it did was to stay still if the current site had no strength, otherwise it would move north.

The biggest issue with this bot is that it was limited to only moving in a single line.

## v2
This version addresses the issue mentioned in the v1. To do so, it would move in any direction at random if a site strength was higher than 5, otherwise it would still move north.

Obviously this was still suboptimal, so some better strategy had to be devised.

## v3
This version would attempt to figure out which adjacent location/site was the weakest and it would attack it as soon as possible (which meant as soon as its strength was 1).

The bot suffered from paralysis when all the locations/sites adjacents to it were owned by it. Thus, pieces in the middle of the expanding territory would stay in place, resulting in wasted strength.

## v4
This version is basically exactly v3 but instead of staying still, it would go in any direction at random.

One of the major issues with this newest version was that strong pieces, which may have capped the 255 strength limit, would merge with one another, thus wasting the accumulated strength by the operation.

## v5
This version attempts to fix the problem identified in v4 by determining which of the four adjacents locations/sites is the weakest, whether it is owned or not. If any adjacent location/site is not owned, then it is a priority to acquire it. Otherwise, find the weakest adjacent location/site and merge with it. This would hopefully resolve the issue of strength capping by merging lower strength sites.

The issue identified in v5 was similar to v3, namely that once all adjacent locations/sites were owned, a "dance" between 2 locations/sites would occur, thus resetting the strength of the location that was just left.

## v6
This version attempts to solve the issue by radically changing the plan of attack. If no adjacent location/site is not owned, then we attempt to find the closed location on both x and y axis. In the worst case, we own all pieces on both axis and we revert to giving it a random direction.

This worked great, however it did not take into account a critical factor of the game: production. The bot did not have any prioritization heuristic regarding which adjacent square it should attack other than "is it the weakest". Thus, it would prefer to attack a location which had a production of 1 over with 10 if the first had a strength of 1 and the second a strength of 2.

## v7
This version introduces its first metric/heuristic: attractiveness. The formula was very simple: `$site->production / ($site->strength ?: 1)`. Thus, a site is more attractive the bigger its production is and the smaller its strength is.

This produced a significant improvement over the previous versions, but still exhibited issues recognized in previous versions, specifically that pieces which were strength capped or near strength capped would still merge with one another.

## v8 - Submitted v1 - Ranked 398 out of 615 (Bronze)
At this point I introduced an important change: the bot now has a single parametric value. When it is invoked, it can be given a "minimum strength", a value which is used to determine the minimal strength a piece should have before it can move out to its surroundings.

At the same time, an important tool is added: a test randomizer. In order to better assess the success of an iteration, it competes against its predecessors. If its performance (number of games won) is significantly higher than its predecessors, then it is considered an improvement and kept as a new iteration. The test randomizer also serves another functionality, that of determining the best parameter value to give to the bot that will run on halite.io.

The test randomizer runs the "champion" (current iteration) against challengers (previous iterations). It calls the halite environment with varying map width and sizes, as well as different challengers count. It also has a mode where the challengers are always the same set, which provides more stability for parameter selection. Finally, it also provides the ability to test the current iteration against itself to further fine-tune the parameters of the bot.

With this tool, I was able to run about 700k games over a period of about 16h.

## v9 - Submitted v2 - Ranked ~70 out of 662 (Silver)
This version differs from the last one by only a minor change. I finally take the time to read the rules "more properly" and determine that it is better to wait for a piece to strengthen instead of attacking its surrounding as soon as possible.

With this single change, the bot is a lot stronger, jumping into a different ranking.

At this point I'm starting to think that most of the local tactics have been exhausted and other strategies/approaches will have to be used. The most likely next step will be to generate some kind of "map production hotspot acquisition planning". In other words, optimize the acquisition of locations/sites based on their production rate instead of simply doing a local (looking at the four adjacents locations) selection.

While thinking about this, I also had the idea that it would make sense to devise some sort of path through which pieces would travel. For instance, it is very important to keep the highly productive sites in place as long as possible. Any movement on these sites would nullify their production value.

## License

The code is licensed under the [MIT license](http://choosealicense.com/licenses/mit/). See [LICENSE](LICENSE).