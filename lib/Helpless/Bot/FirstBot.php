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
     * Block regions from attacking
     *
     * @var \SplObjectStorage
     */
    private $blockRegionsForAttack;

    /**
     * Setup listeners
     */
    public function __construct($map, $eventDispatcher)
    {
        parent::__construct($map, $eventDispatcher);

        // storage
        $this->blockRegionsForAttack = new \SplObjectStorage;

        // setup listeners
        $eventDispatcher->addListener(\Mastercoding\Conquest\Event::SETUP_MAP_COMPLETE, array($this, 'setupMapComplete'));
        $eventDispatcher->addListener(\Mastercoding\Conquest\Event::AFTER_UPDATE_MAP, array($this, 'updateMap'));

    }

    /**
     * Add region to blocked attack region
     *
     * @param \Mastercoding\Conquest\Object\Region $region
     */
    public function addBlockAttackRegion(\Mastercoding\Conquest\Object\Region $region)
    {
        $this->blockRegionsForAttack->attach($region);
        return $this;
    }

    /**
     * Is the region blocked
     *
     * @param \Mastercoding\Conquest\Object\Region $region
     * @return bool
     */
    public function isRegionBlocked(\Mastercoding\Conquest\Object\Region $region)
    {
        return $this->blockRegionsForAttack->contains($region);
    }

    /**
     * After update map, this is called
     */
    public function updateMap()
    {

        // reset
        $this->blockRegionsForAttack = new \SplObjectStorage;

        // re-order strategies
        $priorityQueue = new \SplPriorityQueue;
        foreach ($this->captureContinentStrategies as $captureStrategy) {

            $continent = $captureStrategy->getContinent();

            // get region count and captured region count
            $regions = count($continent->getRegions());
            $myRegions = count(\Mastercoding\Conquest\Bot\Helper\General::regionsInContinentByOwner($this->getMap(), $continent, $this->getMap()->getYou()));
            $opponentRegions = 0;
            foreach ($continent->getRegions() as $region) {

                if ($region->getOwner() != $this->getMap()->getYou() && !in_array($region->getOwner()->getName(), array(\Mastercoding\Conquest\Object\Owner\AbstractOwner::UNKNOWN, \Mastercoding\Conquest\Object\Owner\AbstractOwner::NEUTRAL))) {
                    $opponentRegions++;
                }

            }

            // based on bonus
            $bonus = $continent->getBonus();

            // to capture
            $priorityQueue->insert($captureStrategy, (($regions - $myRegions + $opponentRegions)));

        }

        // set priorities, reversed order
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

            // store for re-ordering
            $this->captureContinentStrategies->attach($capture);

        }

        // to new continent
        $crossToNew = new \Helpless\Bot\Strategy\CrossToNewContinent;
        $crossToNew->setPriority(4);
        $this->addStrategy($crossToNew);

        // early opponent takeout
        //$earlyOpponentTakeout = new
        // \Helpless\Bot\Strategy\EarlyOpponentTakeout;
        //$earlyOpponentTakeout->setPriority(100);
        //$this->addStrategy($earlyOpponentTakeout);

        // pick armies random, we should never loose armies due to strategies not
        // needing them
        $randomArmies = new \Mastercoding\Conquest\Bot\Strategy\ArmyPlacement\Random;
        $this->addArmyPlacementStrategy($randomArmies);

    }

}
