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
    
    public function __construct($service = null, $fork = true, $timeout = null, $config = null)
    {
        $this->service = $service ? $service : getenv('SERVICE');
        $this->fork = $fork;
        $this->config = $config;
        $this->timeout = $timeout;
    }
    
    public function work()
    {
        try {
            $o = new \ResqueService\Services\ServiceDistributor($this->service, $this->fork, $this->timeout, $this->config);
            $o->work();
        } catch (\Exception $e) {
            echo $e->getMessage() . $e->getTraceAsString();
        }
    }
}