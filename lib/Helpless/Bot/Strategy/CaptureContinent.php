<?php

namespace Helpless\Bot\Strategy;

use \Mastercoding\Conquest\Bot\Helper;

class CaptureContinent extends \Mastercoding\Conquest\Bot\Strategy\AbstractStrategy implements \Mastercoding\Conquest\Bot\Strategy\RegionPicker\RegionPickerInterface, \Mastercoding\Conquest\Bot\Strategy\AttackTransfer\AttackTransferInterface, \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\ArmyPlacementInterface
{

    /**
     * Need x percent additional armies then the theoretical amount to start an
     * attack
     *
     * @var int
     */
    const ADDITIONAL_ARMIES_PERCENTAGE = 30;

    /**
     * The continent to caputre
     *
     * @var \Mastercoding\Conquest\Object\Continent
     */
    private $continent;

    /**
     * @inheritDoc
     */
    public function isDone(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {
        return Helper\General::continentCaptured($bot->getMap(), $this->continent);
    }

    /**
     * Set the continent to capture
     *
     * @param \Mastercoding\Conquest\Object\Continent $continent
     */
    public function setContinent(\Mastercoding\Conquest\Object\Continent $continent)
    {
        $this->continent = $continent;
    }

    /**
     * Get the continent to capture
     *
     * @return \Mastercoding\Conquest\Object\Continent
     */
    public function getContinent()
    {
        return $this->continent;
    }

    /**
     * @inheritDoc
     */
    public function placeArmies(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PlaceArmies $move, $amountLeft, \Mastercoding\Conquest\Command\Go\PlaceArmies $placeArmiesCommand)
    {

        // captured, defend borders
        if (Helper\General::continentCaptured($bot->getMap(), $this->continent)) {

            // border regions
            $borderRegions = \Mastercoding\Conquest\Bot\Helper\General::borderRegionsInContinent($bot->getMap(), $this->continent);
            foreach ($borderRegions as $region) {

                $myArmies = $region->getArmies();
                foreach ($region->getNeighbors() as $neighbor) {

                    // not own region or neutral
                    if ($neighbor->getOwner() != $bot->getMap()->getYou() && $neighbor->getOwner()->getName() != \Mastercoding\Conquest\Object\Owner\AbstractOwner::NEUTRAL) {

                        $neededArmies = Helper\Amount::amountToDefend($neighbor->getArmies(), self::ADDITIONAL_ARMIES_PERCENTAGE);
                        if ($neededArmies > $myArmies) {

                            //
                            $additionalArmies = $neededArmies - $myArmies;
                            $amountToPlace = min($additionalArmies, $amountLeft);
                            $amountLeft -= $amountToPlace;

                            // place armies
                            $move->addPlaceArmies($region->getId(), $amountToPlace);

                        }

                    }

                }

            }

            return array($move, $amountLeft);
        }

        // get regions owned by me
        $myRegions = \Mastercoding\Conquest\Bot\Helper\General::regionsInContinentByOwner($bot->getMap(), $this->continent, $bot->getMap()->getYou());

        // loop regions to see if any of them have opponent owned neighbors, if
        // so pick this one, otherwise, pick neutral/unknown
        $priorityQueue = new \SplPriorityQueue;
        foreach ($this->continent->getRegions() as $region) {

            // mine?
            if ($region->getOwner() == $bot->getMap()->getYou()) {
                continue;
            }

            // needed
            $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($region->getArmies(), self::ADDITIONAL_ARMIES_PERCENTAGE);

            // not mine, needs to be captured. Is there a neighbor that can
            // capture this?
            $captureable = false;
            $localPriorityQueue = new \SplPriorityQueue;
            foreach ($region->getNeighbors() as $neighbor) {

                // neighbor mine and in same continent?
                if ($neighbor->getOwner() == $bot->getMap()->getYou()) {

                    // same continent
                    if ($neededArmies <= $neighbor->getAttackableArmies()) {

                        $captureable = true;
                        break;

                    } else {

                        $localPriorityQueue->insert($neighbor, $neighbor->getAttackableArmies());

                    }

                }

            }

            // any neighbors in this continent?
            if (!$captureable && count($localPriorityQueue) > 0) {
                $topRegion = $localPriorityQueue->top();
                $priorityQueue->insert($topRegion, $topRegion->getAttackableArmies());
            }

        }

        // get region with top priority
        if (count($priorityQueue) > 0) {

            // top
            $topPriority = $priorityQueue->top();

            // ok, all armies on this one (better implementation to come)
            $amount = $amountLeft;
            $move->addPlaceArmies($topPriority->getId(), $amount);

            return array($move, $amountLeft - $amount);

        }

        // no armies placed by me
        return array($move, $amountLeft);

    }

    /**
     * @inheritDoc
     */
    public function pickRegions(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PickRegions $move, $amountLeft, \Mastercoding\Conquest\Command\StartingRegions\Pick $pickCommand)
    {

        // choice
        $choices = array_diff($pickCommand->getRegionIds(), $move->getRegionIds());

        // as many from this continent as possible
        foreach ($choices as $regionId) {

            if (null !== $this->continent->getRegionById($regionId)) {
                $move->addRegionId($regionId);
                $amountLeft--;
            }

            if ($amountLeft == 0) {
                break;
            }

        }

        // return
        return array($move, $amountLeft);

    }

    /**
     * @inheritDoc
     */
    public function transfer(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {

        // move from region with only me neighbors
        // to region with not only me neighbors,
        // or, if entire continent is ours, move to continent edges
        $borderRegions = \Mastercoding\Conquest\Bot\Helper\General::borderRegionsInContinent($bot->getMap(), $this->continent);

        // not all mine
        $notAllMineNeighboredRegions = new \SplObjectStorage;
        
        // implement $absoluteNotAllMineNeighboredRegions = new \SplObjectStorage;
        foreach ($this->continent->getRegions() as $region) {

            if ($bot->getMap()->getYou() == $region->getOwner() && !Helper\General::allYoursOrDifferentContinentNeutral($bot->getMap(), $region)) {
                $notAllMineNeighboredRegions->attach($region);
            }

        }

        // loop regions, again
        foreach ($this->continent->getRegions() as $region) {

            // only one?
            if ($region->getAttackableArmies() == 0) {
                continue;
            }

            // all neighbors mine?
            if ($region->getOwner() == $bot->getMap()->getYou() && Helper\General::allYoursOrDifferentContinentNeutral($bot->getMap(), $region)) {

                // continent captured? Move to edge
                if (count($notAllMineNeighboredRegions) == 0) {

                    // closest edge
                    try {
                        $closestEdge = Helper\Path::closestRegion($bot->getMap(), $region, $borderRegions, true);
                        if (null !== $closestEdge) {

                            $path = Helper\Path::shortestPath($bot->getMap(), $closestEdge, $region, true);
                            $move->addAttackTransfer($region->getId(), $path[1]->getId(), $region->getAttackableArmies());

                        }

                    } catch ( \Exception $e ) {

                        // region is border, move to other continent?

                    }

                } else {

                    // shortest path to region with not all mine
                    $closestRegion = Helper\Path::closestRegion($bot->getMap(), $region, $notAllMineNeighboredRegions, true);
                    if (null !== $closestRegion) {

                        $path = Helper\Path::shortestPath($bot->getMap(), $closestRegion, $region, true);
                        $move->addAttackTransfer($region->getId(), $path[1]->getId(), $region->getAttackableArmies());
                    } else {
                        
                        // implement
                        
                    }

                }

            }

        }

        return $move;
    }

    /**
     * Attack the regions in the most efficient way (or some if all is not
     * possible)
     */
    private function attackRegions(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \SplObjectStorage $regions)
    {

        foreach ($regions as $region) {

            // wealthy enough to attack?
            $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($region->getArmies(), self::ADDITIONAL_ARMIES_PERCENTAGE);

            // top neighbor
            $priorityQueue = new \SplPriorityQueue;

            // find wealthy neigbor
            $totalNeighborArmies = 0;
            foreach ($region->getNeighbors() as $neighbor) {

                if ($neighbor->getOwner() == $bot->getMap()->getYou()) {

                    // enough (needs to be >, we need 1 left on region)
                    if ($neighbor->getAttackableArmies() >= $neededArmies) {

                        $priorityQueue->insert($neighbor, $neighbor->getAttackableArmies());

                    } else {

                        if ($neighbor->getAttackableArmies() > 0) {
                            $totalNeighborArmies += $neighbor->getAttackableArmies();
                        }

                    }
                }
            }

            // can we attack with just one?
            if (count($priorityQueue) > 0) {

                // get wealthiest neighbor
                $neighbor = $priorityQueue->top();

                // other count
                $otherOwners = 0;
                foreach ($neighbor->getNeighbors() as $neighborsNeighbor) {
                    if ($neighborsNeighbor->getOwner() != $bot->getMap()->getYou() && !in_array($neighborsNeighbor->getOwner()->getName(), array(\Mastercoding\Conquest\Object\Owner\AbstractOwner::NEUTRAL, \Mastercoding\Conquest\Object\Owner\AbstractOwner::UNKNOWN))) {
                        $otherOwners++;
                    }
                }

                // one other owner?
                if ($otherOwners <= 1) {
                    $neededArmies = $neighbor->getAttackableArmies();
                } else {

                    // for now, dont attack with more if we have more
                    $factorBigger = $neighbor->getAttackableArmies() / $neededArmies;
                    if ($factorBigger > 2) {

                        // add 10%
                        if ($factorBigger > 4) {
                            $neededArmies *= 1.3;
                        } else if ($factorBigger > 3) {
                            $neededArmies *= 1.2;
                        } else {
                            $neededArmies *= 1.1;
                        }

                    }

                }

                // attack with this one
                $neighbor->removeArmies($neededArmies);
                $move->addAttackTransfer($neighbor->getId(), $region->getId(), $neededArmies);

            } else if ($totalNeighborArmies >= $neededArmies) {

                // all attackable
                foreach ($region->getNeighbors() as $neighbor) {

                    if ($neighbor->getOwner() == $bot->getMap()->getYou()) {

                        // enough (needs to be >, we need 1 left on region)
                        if ($neighbor->getAttackableArmies() > 0) {

                            $move->addAttackTransfer($neighbor->getId(), $region->getId(), $neighbor->getAttackableArmies());
                            $neighbor->removeArmies($neighbor->getAttackableArmies());

                        }

                    }
                }
            }

        }

        return $move;
    }

    /**
     * @inheritDoc
     */
    public function attack(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {
        // attack continent regions
        $notMineNeighbors = new \SplObjectStorage;
        foreach ($this->continent->getRegions() as $region) {

            // mine?
            if ($region->getOwner() == $bot->getMap()->getYou()) {

                // neighbours that are not mine?
                foreach ($region->getNeighbors() as $neighbor) {

                    // add
                    if ($neighbor->getOwner() != $bot->getMap()->getYou() && $neighbor->getContinentId() == $this->continent->getId()) {
                        $notMineNeighbors->attach($neighbor);
                    }

                }

            }

        }

        // attack those
        $move = $this->attackRegions($bot, $move, $notMineNeighbors);
        return $move;
    }

}
