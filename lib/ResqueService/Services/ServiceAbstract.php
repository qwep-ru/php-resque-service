<?php 

namespace ResqueService\Services;

use \Psr\Log\LogLevel;

/**
 * @author Dmitry Vyatkin <dmi.vyatkin@gmail.com>
 */
abstract class ServiceAbstract
{
    
    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    protected  $shutdown = false;
    
    /**
     * @var string The hostname of this worker.
     */
    protected $hostname;
    
    /**
     * @var LoggerInterface Logging object that impliments the PSR-3 LoggerInterface
     */
    protected $logger;
    
    /**
     * @var boolean True if this worker is paused.
     */
    protected $paused = false;
    
    /**
     * @var string String identifying this worker.
     */
    protected $id;
    
    /**
     * @var int Process ID of child worker processes.
     */
    protected $child = null;
    
    
    public function __construct()
    {
        $this->logger = new \ResqueService\Logger(true);
        
        if(function_exists('gethostname')) {
            $hostname = gethostname();
        }
        else {
            $hostname = php_uname('n');
        }
        $this->hostname = $hostname;
        $this->id = $this->hostname . ':'.getmypid();
    }
    
    
    abstract public function work();
        
    
    protected function updateProcLine($status)
    {
        $processTitle = 'resque-' . $this->id . '-' . $this->serviceClass .': ' . $status;
        if(function_exists('cli_set_process_title')) {
            cli_set_process_title($processTitle);
        }
        else if(function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }
    
    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    protected function registerSigHandlers()
    {
        if(!function_exists('pcntl_signal')) {
            return;
        }
    
        declare(ticks = 1);
        pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
        pcntl_signal(SIGINT, array($this, 'shutDownNow'));
        pcntl_signal(SIGQUIT, array($this, 'shutdown'));
        pcntl_signal(SIGUSR1, array($this, 'killChild'));
        pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
        pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
        $this->logger->log(LogLevel::DEBUG, 'Registered signals');
    }
    
    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown()
    {
        $this->shutdown = true;
        $this->logger->log(LogLevel::NOTICE, 'Shutting down');
    }
    
    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow()
    {
        $this->shutdown();
        $this->killChild();
    }
    
    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     */
    public function killChild()
    {
        if(!$this->child) {
            $this->logger->log(LogLevel::DEBUG, 'No child to kill.');
            return;
        }
    
        $this->logger->log(LogLevel::INFO, 'Killing child at {child}', array('child' => $this->child));
        if(exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            $this->logger->log(LogLevel::DEBUG, 'Child {child} found, killing.', array('child' => $this->child));
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        }
        else {
            $this->logger->log(LogLevel::INFO, 'Child {child} not found, restarting.', array('child' => $this->child));
            $this->shutdown();
        }
    }
    
    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing()
    {
        $this->logger->log(LogLevel::NOTICE, 'USR2 received; pausing job processing');
        $this->paused = true;
    }
    
    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing()
    {
        $this->logger->log(LogLevel::NOTICE, 'CONT received; resuming job processing');
        $this->paused = false;
    }
    
    /**
     * Look for any workers which should be running on this server and if
     * they're not, remove them from Redis.
     *
     * This is a form of garbage collection to handle cases where the
     * server may have been killed and the Resque workers did not die gracefully
     * and therefore leave state information in Redis.
     */
    public function pruneDeadWorkers()
    {
        $workerPids = $this->workerPids();
    }
    
    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * @return array Array of Resque worker process IDs.
     */
    public function workerPids()
    {
        $pids = array();
        exec('ps -A -o pid,command | grep [r]esque-ix', $cmdOutput);
        foreach($cmdOutput as $line) {
            list($pids[],) = explode(' ', trim($line), 2);
        }
        return $pids;
    }
    
    /**
     * fork() helper method for php-resque that handles issues PHP socket
     * and phpredis have with passing around sockets between child/parent
     * processes.
     *
     * Will close connection to Redis before forking.
     *
     * @return int Return vars as per pcntl_fork()
     */
    public static function fork()
    {
        if(!function_exists('pcntl_fork')) {
            return -1;
        }
    
        $pid = pcntl_fork();
        if($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }
    
        return $pid;
    }
}