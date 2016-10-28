<?php 

namespace ResqueService\Services;

/**
 * @author Dmitry Vyatkin <dmi.vyatkin@gmail.com>
 */
class NodeResqueDistributor extends ServiceAbstract
{
    public function __construct()
    {
        parent::__construct();
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
            
            $this->child = parent::fork();
            if ($this->child === 0 || $this->child === false || $this->child === -1) {
                $status = 'Collector since ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->log(Psr\Log\LogLevel::INFO, $status);
                
                if ($this->child === 0) {
                    exit(0);
                }
            }
             
            if($this->child > 0 || $this->child === -1) {
                // Parent process, sit and wait
                $status = 'Forked default collector ' . $this->child . ' at ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->log(Psr\Log\LogLevel::INFO, $status);
                 
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
        }
    }    
}