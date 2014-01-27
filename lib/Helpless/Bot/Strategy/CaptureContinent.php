<?php

namespace Helpless\Bot\Strategy;

use \Mastercoding\Conquest\Bot\Helper;

class CaptureContinent extends AbstractStrategy implements \Mastercoding\Conquest\Bot\Strategy\RegionPicker\RegionPickerInterface, \Mastercoding\Conquest\Bot\Strategy\AttackTransfer\AttackTransferInterface, \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\ArmyPlacementInterface
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
     * The continent to caputre
     *
     * @var \Mastercoding\Conquest\Object\Continent
     */
    private $continent;

    /**
     * Get additional armies percentage
     *
     * @param \Mastercoding\Conquest\Bot\AbstractBot $bot
     * @return int
     */
    private function getAdditionalArmiesPercentage(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {
        #if ($bot->getMap()->getRound() <= 10) {
        #    return 20;
        #}

        return self::ADDITIONAL_ARMIES_PERCENTAGE;
    }

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

        // stale
        if ($this->detectStale($bot)) {
            
            // continent
            $moves = $bot->getMoves('PlaceArmies');
            $lastMove = array_pop($moves);

            // loop
            foreach ($lastMove->getPlaceArmies() as $regionId => $armies) {
                
                $region = $bot->getMap()->getRegionById($regionId);
                if ($region->getContinentId() == $this->continent->getId()) {
                    return array($move, $amountLeft);
                }

            }

        }

        // captured, defend borders
        if (Helper\General::continentCaptured($bot->getMap(), $this->continent)) {

            // border regions
            $borderRegions = \Mastercoding\Conquest\Bot\Helper\General::borderRegionsInContinent($bot->getMap(), $this->continent);
            foreach ($borderRegions as $region) {

                $myArmies = $region->getArmies();
                foreach ($region->getNeighbors() as $neighbor) {

                    // not own region or neutral
                    if ($neighbor->getOwner() != $bot->getMap()->getYou() && $neighbor->getOwner()->getName() != \Mastercoding\Conquest\Object\Owner\AbstractOwner::NEUTRAL) {

                        $neededArmies = Helper\Amount::amountToDefend($neighbor->getArmies(), $this->getAdditionalArmiesPercentage($bot));
                        if ($neededArmies > $myArmies) {

                            //
                            $additionalArmies = $neededArmies - $myArmies;
                            $amountToPlace = min($additionalArmies, $amountLeft - 1);
                            $amountLeft -= $amountToPlace;

                            // place armies
                            if ($amountToPlace != 0) {
                                $move->addPlaceArmies($region->getId(), $amountToPlace);
                            }

                        }

                    }

                }

            }

            return array($move, $amountLeft);
        }

        // loop regions to see if any of them have opponent owned neighbors, if
        // so pick this one, otherwise, pick neutral/unknown
        $priorityQueue = new \SplPriorityQueue;
        foreach ($this->continent->getRegions() as $region) {

            // mine?
            if ($region->getOwner() == $bot->getMap()->getYou()) {
                continue;
            }

            // needed
            $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($region->getArmies(), $this->getAdditionalArmiesPercentage($bot));

            // not mine, needs to be captured. Is there a neighbor that can
            // capture this?
            $captureable = false;
            $localPriorityQueue = new \SplPriorityQueue;
            foreach ($region->getNeighbors() as $neighbor) {

                // neighbor mine and in same continent?
                if ($neighbor->getOwner() == $bot->getMap()->getYou() && $neighbor->getContinentId() == $this->continent->getId()) {

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

            // stale?
            $amount = $amountLeft;

            // 0?
            if ($amount != 0) {
                $move->addPlaceArmies($topPriority->getId(), $amount);
            }
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
                //break;
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

        // implement $absoluteNotAllMineNeighboredRegions = new
        // \SplObjectStorage;
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
     * Check if the region can attack multiple regions and count how many armies
     * are left aftewards
     *
     * @return array(attackable_regions, armies_left)
     */
    private function regionAttackMultipleNeighbors(\Mastercoding\Conquest\Bot\AbstractBot $bot, $region)
    {

        $avail = $region->getAttackableArmies();

        $attackable = 0;
        foreach ($region->getNeighbors() as $neighbor) {
            if ($neighbor->getOwner() != $bot->getMap()->getYou() && !in_array($neighbor->getOwner()->getName(), array(\Mastercoding\Conquest\Object\Owner\AbstractOwner::UNKNOWN))) {

                // wealthy enough to attack?
                $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($neighbor->getArmies(), $this->getAdditionalArmiesPercentage($bot));
                if ($neededArmies <= $avail) {

                    $attackable++;
                    $avail -= $neededArmies;

                }

            }
        }

        // attackable
        if ($attackable > 0) {
            return array($attackable, $avail);
        }
        return array(0, null);

    }

    /**
     * Attack the regions in the most efficient way (or some if all is not
     * possible)
     */
    private function attackRegions(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \SplPriorityQueue $regions)
    {

        foreach ($regions as $region) {

            // wealthy enough to attack?
            $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($region->getArmies(), $this->getAdditionalArmiesPercentage($bot));

            // top neighbor
            $priorityQueue = new \SplPriorityQueue;

            // find wealthy neigbor
            $totalNeighborArmies = 0;
            foreach ($region->getNeighbors() as $neighbor) {

                if ($neighbor->getOwner() == $bot->getMap()->getYou() && !$bot->isRegionBlocked($neighbor)) {

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

                // other owners > 1? check if it can attack multiple
                $multiple = $this->regionAttackMultipleNeighbors($bot, $neighbor);

                // one other owner?
                if ($multiple[0] <= 1) {
                    $neededArmies = $neighbor->getAttackableArmies();
                } else {

                    // left
                    $extra = floor($multiple[1] / $multiple[0]);
                    $neededArmies += $extra;

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
        $notMineNeighbors = new \SplPriorityQueue;
        foreach ($this->continent->getRegions() as $region) {

            // mine?
            if ($region->getOwner() == $bot->getMap()->getYou()) {

                // neighbours that are not mine?
                foreach ($region->getNeighbors() as $neighbor) {

                    // add
                    if ($neighbor->getOwner() != $bot->getMap()->getYou() && $neighbor->getContinentId() == $this->continent->getId()) {

                        $priority = 0;
                        if (!in_array($neighbor->getOwner()->getName(), array(\Mastercoding\Conquest\Object\Owner\AbstractOwner::NEUTRAL, \Mastercoding\Conquest\Object\Owner\AbstractOwner::UNKNOWN))) {
                            $priority = 1;
                        }

                        $notMineNeighbors->insert($neighbor, $priority);

                    }

                }

            }

        }

        // attack those
        $move = $this->attackRegions($bot, $move, $notMineNeighbors);
        return $move;
    }

}
