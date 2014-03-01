<?php

namespace Helpless\Bot\Strategy;

use \Mastercoding\Conquest\Bot\Helper;

class CrossToNewContinent extends AbstractStrategy implements \Mastercoding\Conquest\Bot\Strategy\RegionPicker\RegionPickerInterface, \Mastercoding\Conquest\Bot\Strategy\AttackTransfer\AttackTransferInterface, \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\ArmyPlacementInterface
{

    /**
     * How many times the same move should be defined as stale?
     *
     * @var int
     */
    const STALE_COUNT = 5;

    /**
     * Need x percent additional armies then the theoretical amount to start an
     * attack
     *
     * @var int
     */
    const ADDITIONAL_ARMIES_PERCENTAGE = 30;

    /**
     * Check if we have captured continents only
     *
     * @param \Mastercoding\Conquest\bot\AbstractBot $bot
     * @return bool
     */
    public function onlyCapturedContinents(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {

        // loop
        for ($i = 1; $i <= 6; $i++) {

            // continent
            $continent = $bot->getMap()->getContinentById($i);

            // all or nothing (hah)
            $myRegions = Helper\General::regionsInContinentByOwner($bot->getMap(), $continent, $bot->getMap()->getYou());
            if (!(count($myRegions) == 0 || Helper\General::continentCaptured($bot->getMap(), $continent))) {
                return false;
            }

        }

        // yep
        return true;

    }

    /**
     * Find best region to cross. This is the region we own, with a connected
     * continent with smallest bonus (we do not own)
     *
     * @param \Mastercoding\Conquest\bot\AbstractBot $bot
     * @return \SplPriorityQueue
     */
    public function crossibleRegions(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {

        // crossable
        $priorityQueue = new \SplPriorityQueue;

        // loop continents
        for ($i = 1; $i <= 6; $i++) {

            // grab continent
            $continent = $bot->getMap()->getContinentById($i);

            // stale
            if ($this->detectStale($bot)) {

                // continent
                $moves = $bot->getMoves('PlaceArmies');
                $lastMove = array_pop($moves);

                // loop
                foreach ($lastMove->getPlaceArmies() as $regionId => $armies) {

                    $region = $bot->getMap()->getRegionById($regionId);
                    if ($region->getContinentId() == $continent->getId()) {
                        continue 2;
                    }

                }

            }

            // do we own the continent
            if (Helper\General::continentCaptured($bot->getMap(), $continent)) {

                // check border regions
                $borderRegions = Helper\General::borderRegionsInContinent($bot->getMap(), $continent);
                foreach ($borderRegions as $region) {

                    // mine
                    if ($region->getOwner() == $bot->getMap()->getYou()) {

                        // loop neighbors
                        foreach ($region->getNeighbors() as $neighbor) {

                            // neighbor
                            if ($neighbor->getContinentId() != $continent->getId()) {

                                // don't we have armies in that continent to?
                                $neighborContinent = $bot->getMap()->getContinentById($neighbor->getContinentId());
                                $myRegions = \Mastercoding\Conquest\Bot\Helper\General::regionsInContinentByOwner($bot->getMap(), $neighborContinent, $bot->getMap()->getYou());
                                if (count($myRegions) == 0) {

                                    // ok, this is a region with link to
                                    // continent we
                                    $priorityQueue->insert($region, $neighborContinent->getBonus());

                                }

                            }

                        }

                    }
                }

            }

        }

        // return all
        return $priorityQueue;

    }

    /**
     * @inheritDoc
     */
    public function placeArmies(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PlaceArmies $move, $amountLeft, \Mastercoding\Conquest\Command\Go\PlaceArmies $placeArmiesCommand)
    {

        // all continents
        if ($this->onlyCapturedContinents($bot)) {
            
            // crossable regions
            $crossableRegions = $this->crossibleRegions($bot);

            // top two
            if (count($crossableRegions) >= 2) {

                $bestRegionToCross = $crossableRegions->top();
                $secondBestRegionToCross = $crossableRegions->top();

                // place all
                $move->addPlaceArmies($bestRegionToCross->getId(), $amountLeft - 2);
                $move->addPlaceArmies($secondBestRegionToCross->getId(), 2);
                return array($move, 0);

            } else {

                // cross
                if (count($crossableRegions) > 0) {

                    $bestRegionToCross = $crossableRegions->top();

                    // place all
                    $move->addPlaceArmies($bestRegionToCross->getId(), $amountLeft);
                    return array($move, 0);

                }

            }

        }

        // nothing
        return array($move, $amountLeft);
    }

    /**
     * @inheritDoc
     */
    public function pickRegions(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PickRegions $move, $amountLeft, \Mastercoding\Conquest\Command\StartingRegions\Pick $pickCommand)
    {

        // not interesting for this strategy
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

        // find right region to cross, this null should never happen
        $crossableRegions = $this->crossibleRegions($bot);
        if (count($crossableRegions) == 0) {
            return $move;
        }

        // loop all crossable regions
        foreach ($crossableRegions as $fromRegion) {

            // cross to
            $priorityQueue = new \SplPriorityQueue;
            foreach ($fromRegion->getNeighbors() as $neighbor) {

                // don't we own that continent to?
                $neighborContinent = $bot->getMap()->getContinentById($neighbor->getContinentId());
                if (!Helper\General::continentCaptured($bot->getMap(), $neighborContinent) && $neighbor->getOwner() != $bot->getMap()->getYou()) {

                    $priorityQueue->insert($neighbor, -1 * $neighborContinent->getBonus());

                }

            }

            // top
            $toRegion = $priorityQueue->top();

            // enough
            $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($toRegion->getArmies(), self::ADDITIONAL_ARMIES_PERCENTAGE);
            if ($fromRegion->getArmies() > $neededArmies) {

                // attack with this one, all armies!
                $move->addAttackTransfer($fromRegion->getId(), $toRegion->getId(), $fromRegion->getAttackableArmies());
                $fromRegion->removeArmies($fromRegion->getAttackableArmies());

            }

        }

        return $move;
    }

}
