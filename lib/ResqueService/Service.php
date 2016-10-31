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
    
    public function __construct($service = null, $fork = true, $config = null)
    {
        $this->service = $service ? $service : getenv('SERVICE');
        $this->fork = $fork;
        $this->config = $config;
    }
    
    public function work()
    {
        try {
            $o = new \ResqueService\Services\ServiceDistributor($this->service, $this->fork);
            $o->work();
        } catch (\Exception $e) {
            echo $e->getMessage() . $e->getTraceAsString();
        }
    }
}