<?php 

namespace ResqueService;

/**
 * @author Dmitry Vyatkin <dmi.vyatkin@gmail.com>
 */
class Service 
{
    const SERVICE_NODE = 'node';
    
    private $service;
    private $config;
    
    public function __construct($service = null, $config = null)
    {
        $this->service = $service ? $service : getenv('SERVICE');
        $this->config = $config;
    }
    
    public function work()
    {
        try {
            switch ($this->service) {
                case SERVICE_NODE:
                    $o = new \ResqueService\Services\NodeResqueDistributor;
                    $o->work();
                    break;
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . $e->getTraceAsString();
        }
    }
}