<?php 

namespace ResqueService\Services;

/**
 * @author Dmitry Vyatkin <dmi.vyatkin@gmail.com>
 */
class ServiceDistributor extends ServiceAbstract
{
    const SLEEP_TIME = 60;
    private $serviceClass;
    private $fork;
    private $timeout;
    private $config;
    
    public function __construct($serviceClass = null, $fork = null, $timeout = null, $config = null)
    {
        parent::__construct();
        $this->serviceClass = $serviceClass;
        $this->fork = $fork;
        $this->timeout = $timeout ? $timeout : self::SLEEP_TIME;
        $this->config = $config;
    }
    
    
    public function work()
    {
        $this->updateProcLine('Starting');
        $this->registerSigHandlers();
        $this->pruneDeadWorkers();
        
        while(true) {
            if($this->shutdown) {
                break;
            }
            
            $this->child = $this->fork ? parent::fork() : -1;
            if ($this->child === 0 || $this->child === false || $this->child === -1) {
                $status = 'Service since ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->log(\Psr\Log\LogLevel::INFO, $status);
                
                if ($this->serviceClass) {
                    try {
                        $o = new $this->serviceClass($this->config);
                        $o->work();
                    } catch (\Exception $e) {
                        throw $e;
                    }
                }
                
                if ($this->child === 0) {
                    exit(0);
                }
            }
             
            if($this->child > 0 || $this->child === -1) {
                // Parent process, sit and wait
                $status = 'Forked default collector ' . $this->child . ' at ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->log(\Psr\Log\LogLevel::INFO, $status);
                 
                // Wait until the child process finishes before continuing
                if($this->child > 0) {
                    pcntl_wait($status);
                    $exitStatus = pcntl_wexitstatus($status);
                    if($exitStatus !== 0) {
                        throw new \Exception('Job exited with exit code ' . $exitStatus);
                    }
                }
            }
             
            $this->child = null;
            
            usleep($this->timeout * 1000000);
        }
    }    
}