<?php 

namespace ResqueService;


/**
 * @author Dmitry Vyatkin <dmi.vyatkin@gmail.com>
 */
class Service 
{
    private $service;
    private $config;
    private $fork;
    private $timeout;
    private $workers = 1;
    
    public function __construct($service = null, $fork = false, $timeout = null, $config = null)
    {
        $this->service = $service ? $service : getenv('SERVICE');
        $this->fork = $fork;
        $this->config = $config;
        $this->timeout = $timeout;
    }
    
    public function work()
    {
        try {
            if ($this->workers > 1) {
                for($i = 0; $i < $this->workers; ++$i) {
                    $pid = pcntl_fork();
                    if($pid == -1) {
                        die("Could not fork worker ".$i."\n");
                    }
                    // Child, start the worker
                    else if(!$pid) {
                        $o = new \ResqueService\Services\ServiceDistributor($this->service, $this->fork, $this->timeout, $this->config);
                        $o->work();
                        break;
                    }
                }
            } else {
                $o = new \ResqueService\Services\ServiceDistributor($this->service, $this->fork, $this->timeout, $this->config);
                $o->work();
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . $e->getTraceAsString();
        }
    }
    
    /**
     * @return int
     */
    public function getWorkers()
    {
        return $this->workers;
    }

    /**
     * @param int $workers
     * @return Service
     */
    public function setWorkers($workers)
    {
        $this->workers = $workers;
        return $this;
    }

}