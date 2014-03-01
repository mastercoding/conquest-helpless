<?php

namespace Helpless\Bot\Strategy;

use \Mastercoding\Conquest\Bot\Helper;

class BreakupContinent extends AbstractStrategy implements \Mastercoding\Conquest\Bot\Strategy\RegionPicker\RegionPickerInterface, \Mastercoding\Conquest\Bot\Strategy\AttackTransfer\AttackTransferInterface, \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\ArmyPlacementInterface
{

    /**
     * Need x percent additional armies then the theoretical amount to start an
     * attack
     *
     * @var int
     */
    const ADDITIONAL_ARMIES_PERCENTAGE = 20;

    /**
     * How many times the same move should be defined as stale?
     *
     * @var int
     */
    const STALE_COUNT = 5;

    /**
     * Opponent starts with 5 armies per round and is not likely
     * to capture a full continent within the first 7 moves (if this strategy is
     * in place)
     *
     * @var int
     */
    const OPPONENT_ARMIES_PER_ROUND = 5;

    /**
     * Get additional armies percentage
     *
     * @param \Mastercoding\Conquest\Bot\AbstractBot $bot
     * @return int
     */
    private function getAdditionalArmiesPercentage(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {
        return self::ADDITIONAL_ARMIES_PERCENTAGE;
    }

    /**
     * Find the best region to attack in a continent that i do not own any
     * regions in and neutral does not own any regions either
     *
     * @param \Mastercoding\Conquest\Bot\AbstractBot $bot
     * @param \Mastercoding\Conquest\Object\Continent $continent
     * @return null|Region
     */
    private function getBestInsertionForRegion(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Object\Continent $continent)
    {

        // any owned?
        $regionsInContinent = Helper\General::regionsInContinentByOwner($bot->getMap(), $continent, $bot->getMap()->getYou());
        if (0 == count($regionsInContinent)) {

            // any neutral?
            foreach ($continent->getRegions() as $region) {
                if (\Mastercoding\Conquest\Object\Owner\AbstractOwner::NEUTRAL == $region->getOwner()->getName()) {
                    return null;
                }
            }

            // queue
            $priorityQueue = new \SplPriorityQueue;

            // all neutral, no mine, might be fully opponent
            foreach ($continent->getRegions() as $region) {

                // is opponents?
                if ($region->getOwner()->getName() != \Mastercoding\Conquest\Object\Owner\AbstractOwner::UNKNOWN) {

                    // has neighbor that is mine?
                    foreach ($region->getNeighbors() as $neighbor) {

                        // me
                        if ($neighbor->getOwner() == $bot->getMap()->getYou()) {

                            // insert
                            $priorityQueue->insert(array($region, $neighbor), $neighbor->getArmies() / $region->getArmies());

                        }

                    }
                }

            }

            // any
            if (0 == count($priorityQueue)) {
                return null;
            }

            //  top
            return $priorityQueue->top();

        }

        return null;

    }

    /**
     * Prioritized continents
     *
     * @param \Mastercoding\Conquest\Bot\AbstractBot $bot
     * @return \SplPriorityQueue
     */
    private function getContinents(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {

        // sort bonus descending
        $priorityQueue = new \SplPriorityQueue;
        for ($i = 1; $i <= 6; $i++) {

            $continent = $bot->getMap()->getContinentById($i);
            $priorityQueue->insert($continent, $continent->getBonus());

        }
        return $priorityQueue;
    }

    /**
     * @inheritDoc
     */
    public function placeArmies(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PlaceArmies $move, $amountLeft, \Mastercoding\Conquest\Command\Go\PlaceArmies $placeArmiesCommand)
    {

        // sort bonus descending
        $priorityQueue = $this->getContinents($bot);

        // loop
        foreach ($priorityQueue as $continent) {

            $bestInsertion = $this->getBestInsertionForRegion($bot, $continent);
            if (null !== $bestInsertion) {

                // stale
                if ($this->detectStale($bot)) {

                    // continent
                    $moves = $bot->getMoves('PlaceArmies');
                    $lastMove = array_pop($moves);

                    // loop
                    foreach ($lastMove->getPlaceArmies() as $regionId => $armies) {

                        if ($regionId == $bestInsertion[1]->getId()) {
                            continue 2;
                        }

                    }

                }
                
                // ok
                $region = $bestInsertion[0];
                $neighbor = $bestInsertion[1];

                // needed
                $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($region->getArmies() + self::OPPONENT_ARMIES_PER_ROUND, $this->getAdditionalArmiesPercentage($bot));
                if ($neededArmies > $neighbor->getAttackableArmies()) {

                    $additional = min($neededArmies - $neighbor->getAttackableArmies(), $amountLeft);
                    $move->addPlaceArmies($neighbor->getId(), $additional);
                    $amountLeft -= $additional;

                    // none left?
                    if (0 == $amountLeft) {
                        break;
                    }

                }

            }

        }

        // no armies placed by me
        return array($move, $amountLeft);

    }

    /**
     * @inheritDoc
     */
    public function pickRegions(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PickRegions $move, $amountLeft, \Mastercoding\Conquest\Command\StartingRegions\Pick $pickCommand)
    {

        // return
        return array($move, $amountLeft);

    }

    /**
     * @inheritDoc
     */
    public function transfer(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {
        return $move;
    }

    /**
     * @inheritDoc
     */
    public function attack(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {

        // sort bonus descending
        $priorityQueue = $this->getContinents($bot);

        // loop
        foreach ($priorityQueue as $continent) {

            $bestInsertion = $this->getBestInsertionForRegion($bot, $continent);
            if (null !== $bestInsertion) {

                // ok
                $region = $bestInsertion[0];
                $neighbor = $bestInsertion[1];

                // needed
                $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($region->getArmies() + self::OPPONENT_ARMIES_PER_ROUND, $this->getAdditionalArmiesPercentage($bot));
                if ($neededArmies <= $neighbor->getAttackableArmies()) {

                    $move->addAttackTransfer($neighbor->getId(), $region->getId(), $neededArmies);

                } else {
                    $bot->addBlockAttackRegion($region);
                }

            }

        }

        // attack those
        return $move;
    }

}
