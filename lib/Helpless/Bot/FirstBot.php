<?php

namespace Helpless\Bot;

class FirstBot extends \Mastercoding\Conquest\Bot\StrategicBot
{

    /**
     * Setup listeners
     */
    public function __construct($map, $eventDispatcher)
    {
        parent::__construct($map, $eventDispatcher);

        // setup listeners
        $eventDispatcher->addListener(\Mastercoding\Conquest\Event::SETUP_MAP_COMPLETE, array($this, 'setupMapComplete'));
        $eventDispatcher->addListener(\Mastercoding\Conquest\Event::AFTER_UPDATE_MAP, array($this, 'mapUpdate'));

    }

    /**
     * After map has been updated
     */
    public function mapUpdate()
    {

    }

    /**
     * The map has been set-up
     */
    public function setupMapComplete()
    {

        // capture continents based on smallest army base first
        $continents = array();
        for ($i = 1; $i <= 6; $i++) {

            $continent = $this->getMap()->getContinentById($i);
            $continents[] = $continent;

        }

        // sorted continents based on bonus, highest bonus first
        usort($continents, function($a, $b)
        {
            if ($a->getBonus() > $b->getBonus()) {
                return -1;
            } else if ($a->getBonus() < $b->getBonus()) {
                return 1;
            }
            return 0;

        });

        // captures
        for ($i = 0; $i < count($continents); $i++) {
            $capture = new \Helpless\Bot\Strategy\CaptureContinent();
            $capture->setContinent($continents[$i]);
            $capture->setPriority(5 + $i);
            $this->addStrategy($capture);
        }

        // to new continent
        $crossToNew = new \Helpless\Bot\Strategy\CrossToNewContinent;
        $crossToNew->setPriority(4);
        $this->addStrategy($crossToNew);

        // pick armies random, we should never loose armies due to strategies not
        // needing them
        $randomArmies = new \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\Random;
        $this->addArmyPlacementStrategy($randomArmies);

    }

}
