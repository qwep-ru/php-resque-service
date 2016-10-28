<?php 

namespace ResqueService;

/**
 * @author Dmitry Vyatkin <dmi.vyatkin@gmail.com>
 */
class Service 
{
    private $service;
    
    const SERVICE_NODE = 'node';
    
    public function __construct($config)
    {
        $this->service = getenv('SERVICE');
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