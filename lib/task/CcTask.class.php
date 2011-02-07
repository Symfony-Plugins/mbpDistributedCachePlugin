<?php
/**
 * The distributed-cache:cc task processes all pending clear-cache tasks for this server
 * 
 * @author     Burkhard Reffeling <burkhard.reffeling@madebypi.co.uk>
 */
class CcTask extends sfBaseTask
{

    protected function configure()
    {
        $this->addOptions(array(
            new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
            new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'prod'),
            new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'doctrine'),
          //new sfCommandOption('contextual-prefix', null, sfCommandOption::PARAMETER_REQUIRED, "The removal prefix for contextual partials. Defaults to '**' (all actions, all params)", '**'),
        ));

        $this->namespace = 'distributed-cache';
        $this->name = 'cc';
        $this->briefDescription = 'Clears parts of the cache';
        $this->detailedDescription = <<<EOF
The [distributed-cache:cc|INFO] task clears parts of the cache. This is especially useful when working in multi-server setups.
Call it with:

  [php symfony distributed-cache:cc|INFO pattern]
EOF;
    }

    protected function execute($arguments = array(), $options = array())
    {
        // initialize the database connection
        $databaseManager = new sfDatabaseManager($this->configuration);
        $connection = $databaseManager->getDatabase($options['connection'])->getConnection();

        // retrieve current server name from file SERVER_NAME
        $server_name_file = sfConfig::get('sf_root_dir') . DIRECTORY_SEPARATOR . 'SERVER_NAME';
        if (file_exists($server_name_file))
        {
            $server_name = file_get_contents($server_name_file);
        }
        else
        {
            echoln("SERVER_NAME file doesn't exist");
            sfContext::createInstance($this->configuration);
            sfContext::getInstance()->getLogger()->log("SERVER_NAME file doesn't exist");

            return;
        }

        // get Server from database
        $server = ServerTable::getInstance()->findOneBy('name', $server_name);
        if (!$server)
        {
            // create new server
            $server = new Server();
            $server->setName($server_name);
            $server->save();
        }

        // get all pending tasks
        $clearCacheTaskServers = ClearCacheTaskServerTable::getAllPendingByServer($server);

        foreach ($clearCacheTaskServers as $clearCacheTaskServer)
        {
            $clearCacheTask = $clearCacheTaskServer->getClearCacheTask();
            echoln('Processing ClearCacheTask ' . $clearCacheTask->getId());
            if ($clearCacheTask->getIsClearAll())
            {
                $this->clearAll($clearCacheTask->getApplicationName());
            }
            else
            {
                $this->clear($clearCacheTask->getPattern(), $clearCacheTask->getApplicationName(), $clearCacheTask->getContextualPrefix());
            }

            // set task as finished
            $clearCacheTaskServer->setIsSuccessful(true);
            $clearCacheTaskServer->save();
        }
    }

    protected function clear($pattern, $applicationName='frontend', $contextualPrefix = '**')
    {
        if ($pattern)
        {
            // @todo switch context to application
            $context = sfContext::createInstance($this->configuration);
            $cacheManager = $context->getViewCacheManager();
            // cache exists
            if ($cacheManager)
            {
                $cache = $cacheManager->getCache();
                $base_cache_dir = $cache->getOption('cache_dir');
                $base_prefix = $cache->getOption('prefix');

                $dirHandle = opendir($base_cache_dir);
                // loop through all cache identifiers (you might have several)
                while (false !== ($dirName = readdir($dirHandle)))
                {
                    if (($dirName != '.') && ($dirName != '..'))
                    {
                        $cache->setOption('cache_dir', $base_cache_dir . DIRECTORY_SEPARATOR . $dirName);
                        $cache->setOption('prefix', $base_prefix . DIRECTORY_SEPARATOR . $dirName);
                        $remove_success = $cacheManager->remove($pattern, '*', 'all', $contextualPrefix);
                        $context->getLogger()->info('Clearing Cache PATTERN: ' . $pattern . ' under PATH: ' . $base_cache_dir . DIRECTORY_SEPARATOR . $dirName . ' STATUS: ' . ($remove_success ? 'successful' : 'failed'));
                    }
                    // reset cache_dir and prefix
                    $cache->setOption('cache_dir', $base_cache_dir);
                    $cache->setOption('prefix', $base_prefix);
                }
            }
        }
    }
    
    protected function clearAll($applicationName='frontend')
    {
        $cc = new sfCacheClearTask($this->dispatcher, $this->formatter);
        $cc->run();
    }

}
