<?php

namespace Helpless\Bot\Strategy;

use \Mastercoding\Conquest\Bot\Helper;
use \Mastercoding\Conquest\Object\Owner\AbstractOwner;

class EarlyOpponentTakeout extends \Mastercoding\Conquest\Bot\Strategy\AbstractStrategy implements \Mastercoding\Conquest\Bot\Strategy\RegionPicker\RegionPickerInterface, \Mastercoding\Conquest\Bot\Strategy\AttackTransfer\AttackTransferInterface, \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\ArmyPlacementInterface
{

    /**
     * Need x percent additional armies then the theoretical amount to start an
     * attack
     *
     * @var int
     */
    const ADDITIONAL_ARMIES_PERCENTAGE = 30;

    /**
     * Opponent starts with 5 armies per round and is not likely
     * to capture a full continent within the first 7 moves (if this strategy is
     * in place)
     *
     * @var int
     */
    const OPPONENT_ARMIES_PER_ROUND = 5;

    /**
     * @inheritDoc
     */
    public function isDone(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {
        return $bot->getMap()->getRound() > 7;
    }

    /**
     * Get region
     */
    private function getRegion(\Mastercoding\Conquest\Bot\AbstractBot $bot)
    {

        // get regions owned by me
        foreach ($bot->getMap()->getContinents() as $continent) {

            // only defend small bonus continents
            if ($continent->getBonus() > 3) {
                continue;
            }

            $myRegions = \Mastercoding\Conquest\Bot\Helper\General::regionsInContinentByOwner($bot->getMap(), $continent, $bot->getMap()->getYou());
            foreach ($myRegions as $region) {

                // has opponent to take-out
                foreach ($region->getNeighbors() as $neighbor) {

                    // opponent owner? Stupid check, but needed for multiple
                    // opponents
                    $neighborContinent = $bot->getMap()->getContinentById($neighbor->getContinentId());
                    if ($neighbor->getOwner() != $bot->getMap()->getYou() && $neighborContinent->getBonus() <= 3 && !in_array($neighbor->getOwner()->getName(), array(AbstractOwner::NEUTRAL, AbstractOwner::UNKNOWN))) {

                        // yes
                        return $region;

                    }

                }

            }

        }

        return null;

    }

    /**
     * @inheritDoc
     */
    public function placeArmies(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\PlaceArmies $move, $amountLeft, \Mastercoding\Conquest\Command\Go\PlaceArmies $placeArmiesCommand)
    {

        // done
        if (!$this->isDone($bot)) {

            $region = $this->getRegion($bot);
            if (null !== $region) {

                // not everything, so another continent can still be captured
                $move->addPlaceArmies($region->getId(), $amountLeft);
                return array($move, 0);
            }

        }

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
     * Attack the regions in the most efficient way (or some if all is not
     * possible)
     */
    private function attackRegion(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Object\Region $regionFrom, \Mastercoding\Conquest\Object\Region $regionTo)
    {

        // wealthy enough to attack?
        $neededArmies = \Mastercoding\Conquest\Bot\Helper\Amount::amountToAttack($regionTo->getArmies(), self::ADDITIONAL_ARMIES_PERCENTAGE);

        //  attack (defend is better)
        if ($regionFrom->getArmies() >= ($neededArmies + self::OPPONENT_ARMIES_PER_ROUND)) {
            $move->addAttackTransfer($regionFrom->getId(), $regionTo->getId(), $regionFrom->getAttackableArmies());
            $regionFrom->removeArmies($regionFrom->getAttackableArmies());
        }

        return $move;

    }

    /**
     * @see self::attackTransfer
     */
    public function attack(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {

        // region
        if (!$this->isDone($bot)) {

            // attack those
            $regionFrom = $this->getRegion($bot);
            $regionTo = null;

            // attack
            if (null !== $regionFrom) {

                // grab neighbor
                foreach ($regionFrom->getNeighbors() as $neighbor) {

                    // opponent owner? Stupid check, but needed for multiple
                    // opponents
                    if ($neighbor->getOwner() != $bot->getMap()->getYou() && !in_array($neighbor->getOwner()->getName(), array(AbstractOwner::NEUTRAL, AbstractOwner::UNKNOWN))) {

                        // yes
                        $regionTo = $neighbor;
                        break;

                    }

                }

                // make move
                $move = $this->attackRegion($bot, $move, $regionFrom, $regionTo);

            }

        }

        return $move;
    }

    /**
     * @inheritDoc
     */
    public function transfer(\Mastercoding\Conquest\Bot\AbstractBot $bot, \Mastercoding\Conquest\Move\AttackTransfer $move, \Mastercoding\Conquest\Command\Go\AttackTransfer $attackTransferCommand)
    {
        return $move;
    }

}
