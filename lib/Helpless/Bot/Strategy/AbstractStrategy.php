<?php

namespace Helpless\Bot\Strategy;

abstract class AbstractStrategy extends \Mastercoding\Conquest\Bot\Strategy\AbstractStrategy
{
    
    /**
     * Detect if we are in a "stale" situation, in which two players are placing
     * armies on opponent regions
     *
     * @return bool
     */
    protected function detectStale(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {

        $commands = $bot->getMoves('PlaceArmies');
        if (count($commands) < static::STALE_COUNT) {
            return false;
        }

        // loop
        for ($j = 0, $i = count($commands) - 2; $j < static::STALE_COUNT - 1; $j++, $i--) {

            if ($commands[$i]->toString() != $commands[$i + 1]->toString()) {
                return false;
            }

        }

        return true;

    }

}