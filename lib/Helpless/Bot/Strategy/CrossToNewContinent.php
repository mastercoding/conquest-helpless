<?php

namespace Helpless\Bot\Strategy;

use \Mastercoding\Conquest\Bot\Helper;

class CrossToNewContinent extends \Mastercoding\Conquest\Bot\Strategy\AbstractStrategy implements \Mastercoding\Conquest\Bot\Strategy\RegionPicker\RegionPickerInterface, \Mastercoding\Conquest\Bot\Strategy\AttackTransfer\AttackTransferInterface, \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\ArmyPlacementInterface
{

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
     * @return \Mastercoding\Conquest\Object\Region
     */
    public function bestRegionToCross(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {

        // crossable
        $priorityQueue = new \SplPriorityQueue;

        // loop continents
        for ($i = 1; $i <= 6; $i++) {

            // grab continent
            $continent = $bot->getMap()->getContinentById($i);

            // do we own the continent
            if (Helper\General::continentCaptured($bot->getMap(), $continent)) {

                // check border regions
                $borderRegions = Helper\General::borderRegionsInContinent($bot->getMap(), $continent);
                foreach ($borderRegions as $region) {

                    // loop neighbors
                    foreach ($region->getNeighbors() as $neighbor) {

                        // neighbor
                        if ($neighbor->getContinentId() != $continent->getId()) {

                            // don't we own that continent to?
                            $neighborContinent = $bot->getMap()->getContinentById($neighbor->getContinentId());
                            if (!Helper\General::continentCaptured($bot->getMap(), $neighborContinent)) {

                                // ok, this is a region with link to continent we
                                // do not own
                                $priorityQueue->insert($region, -1 * $neighborContinent->getBonus());

                            }

                        }

                    }

                }

            }

        }

        // return top
        if (count($priorityQueue) != 0) {
            $top = $priorityQueue->top();
            return $top;
        }
        return null;

    }

    /**
     * @inheritDoc
     */
    public function placeArmies(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PlaceArmies $move, $amountLeft, \Mastercoding\Conquest\Command\Go\PlaceArmies $placeArmiesCommand)
    {

        // all continents
        if ($this->onlyCapturedContinents($bot)) {

            // find right region to cross
            $bestRegionToCross = $this->bestRegionToCross($bot);

            // place all
            $move->addPlaceArmies($bestRegionToCross->getId(), $amountLeft);
            return array($move, 0);

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
    public function attackTransfer(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {

        // all continents
        if ($this->onlyCapturedContinents($bot)) {

            // find right region to cross, this null should never happen
            $fromRegion = $this->bestRegionToCross($bot);
            if (null === $fromRegion) {
                return $move;
            }

            // cross to
            $priorityQueue = new \SplPriorityQueue;
            foreach ($fromRegion->getNeighbors() as $neighbor) {

                // don't we own that continent to?
                $neighborContinent = $bot->getMap()->getContinentById($neighbor->getContinentId());
                if (!Helper\General::continentCaptured($bot->getMap(), $neighborContinent)) {

                    $priorityQueue->insert($neighbor, -1 * $neighborContinent->getBonus());

                }

            }

            // top
            $toRegion = $priorityQueue->top();

            // enough
            $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($toRegion->getArmies(), self::ADDITIONAL_ARMIES_PERCENTAGE);
            if ($fromRegion->getArmies() > $neededArmies) {

                // attack with this one, all armies!
                $move->addAttackTransfer($fromRegion->getId(), $toRegion->getId(), $fromRegion->getArmies() - 1);
                $fromRegion->removeArmies($fromRegion->getArmies() - 1);

            }

        }

        return $move;
    }

}
