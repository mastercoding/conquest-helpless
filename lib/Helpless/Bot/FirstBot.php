<?php

namespace Helpless\Bot;

class FirstBot extends \Mastercoding\Conquest\Bot\StrategicBot
{

    /**
     * Offset in priority queue for capture continent
     *
     * @var int
     */
    const CAPTURE_CONTINENT_PRIORITY_OFFSET = 5;

    /**
     * Capture continent strategies
     */
    private $captureContinentStrategies;

    /**
     * Setup listeners
     */
    public function __construct($map, $eventDispatcher)
    {
        parent::__construct($map, $eventDispatcher);

        // setup listeners
        $eventDispatcher->addListener(\Mastercoding\Conquest\Event::SETUP_MAP_COMPLETE, array($this, 'setupMapComplete'));
        $eventDispatcher->addListener(\Mastercoding\Conquest\Event::AFTER_UPDATE_MAP, array($this, 'updateMap'));

    }

    /**
     * After update map, this is called
     */
    public function updateMap()
    {

        // re-order strategies
        $priorityQueue = new \SplPriorityQueue;
        foreach ($this->captureContinentStrategies as $captureStrategy) {

            $continent = $captureStrategy->getContinent();

            // get region count and captured region count
            $regions = count($continent->getRegions());
            $myRegions = count(\Mastercoding\Conquest\Bot\Helper\General::regionsInContinentByOwner($this->getMap(), $continent, $this->getMap()->getYou()));

            // to capture
            $priorityQueue->insert($captureStrategy, ($regions - $myRegions));

        }

        // set priorities
        $i = 1;
        foreach ($priorityQueue as $captureStrategy) {

            $captureStrategy->setPriority(self::CAPTURE_CONTINENT_PRIORITY_OFFSET + $i);
            $i++;

        }

        // sort
        $this->strategiesChanged();

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
        $this->captureContinentStrategies = new \SplObjectStorage;
        for ($i = 0; $i < count($continents); $i++) {

            // create capture continent strategy
            $capture = new \Helpless\Bot\Strategy\CaptureContinent();
            $capture->setContinent($continents[$i]);
            $capture->setPriority(self::CAPTURE_CONTINENT_PRIORITY_OFFSET + $i);
            $this->addStrategy($capture);

            // testing with africa and south america
            if ($continents[$i]->getId() == 4) {
                $capture->setPriority(20);
            }

            // store for re-ordering
            $this->captureContinentStrategies->attach($capture);

        }

        // to new continent
        $crossToNew = new \Helpless\Bot\Strategy\CrossToNewContinent;
        $crossToNew->setPriority(4);
        $this->addStrategy($crossToNew);

        // early opponent takeout
        $earlyOpponentTakeout = new \Helpless\Bot\Strategy\EarlyOpponentTakeout;
        $earlyOpponentTakeout->setPriority(100);
        $this->addStrategy($earlyOpponentTakeout);

        // pick armies random, we should never loose armies due to strategies not
        // needing them
        $randomArmies = new \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\Random;
        $this->addArmyPlacementStrategy($randomArmies);

    }

}
