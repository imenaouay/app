<?php

namespace Bow\Services;

use Monolog\Logger;
use Bow\Config\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Bow\Application\Services as BowService;

class LoggerService extends BowService
{
    /**
     * @var mixed
     */
    private $whoops;

    /**
     * @var mixed
     */
    private $monolog;

    /**
     * @var Config
     */
    private $config;

    /**
     * Permet de créer le service
     *
     * @param Config $config
     * @return void
     */
    public function make(Config $config)
    {
        $this->whoops = new \Whoops\Run;

        $this->logger = new Logger('BOW');

        $this->config = $config;
    }

    /**
     * Permet de lancer le service
     *
     * @return void
     */
    public function start()
    {
        $this->whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $this->whoops->register();
        
        $this->logger->pushHandler(new StreamHandler($this->config['resource.log'].'/bow.log', Logger::DEBUG));
        $this->logger->pushHandler(new FirePHPHandler());
    }
}
