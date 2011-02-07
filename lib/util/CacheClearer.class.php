<?php

/**
 * CacheClearScheduler provides static methods to schedule cache clearing tasks of a given application
 *
 * @author     Burkhard Reffeling <burkhard.reffeling@madebypi.co.uk>
 */

class CacheClearer
{

    public static function clear($pattern, $applicationName='frontend', $contextualPrefix = '**')
    {
        // create new ClearCacheTask
        $clearCacheTask = new ClearCacheTask();
        $clearCacheTask->setIsClearAll(false);
        $clearCacheTask->setPattern($pattern);
        $clearCacheTask->setApplicationName($applicationName);
        $clearCacheTask->setContextualPrefix($contextualPrefix);
        $clearCacheTask->save();

        self::assignTaskToAllServers($clearCacheTask);
    }

    public static function clearAll($applicationName='frontend')
    {
        // create new ClearCacheTask
        $clearCacheTask = new ClearCacheTask();
        $clearCacheTask->setIsClearAll(true);
        $clearCacheTask->setApplicationName($applicationName);
        $clearCacheTask->save();

        self::assignTaskToAllServers($clearCacheTask);
    }

    protected static function assignTaskToAllServers(ClearCacheTask $clearCacheTask)
    {
        // get all servers
        $servers = ServerTable::getInstance()->findAll();
        // assign servers
        foreach ($servers as $server)
        {
            $clearCacheTaskServer = new ClearCacheTaskServer();
            $clearCacheTaskServer->setClearCacheTask($clearCacheTask);
            $clearCacheTaskServer->setServer($server);
            $clearCacheTaskServer->save();
        }
    }

}