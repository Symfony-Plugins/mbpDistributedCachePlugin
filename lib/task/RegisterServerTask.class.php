<?php

/**
 * The distributed-cache:register-server task registers a server to the database and saves a SERVER_NAME file to the sf_root_dir
 * 
 * @author     Burkhard Reffeling <burkhard.reffeling@madebypi.co.uk>
 */
class RegisterServerTask extends sfBaseTask
{

    protected function configure()
    {
        $this->addOptions(array(
            new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
            new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'prod'),
            new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'doctrine'),
        ));

        $this->addArguments(array(
            new sfCommandArgument('server-name', sfCommandArgument::REQUIRED, 'The unique server name'),
        ));


        $this->namespace = 'distributed-cache';
        $this->name = 'register-server';
        $this->briefDescription = 'Registers the current server to the database';
        $this->detailedDescription = <<<EOF
The [distributed-cache:register-server|INFO] task registers the current server to the database.
Call it with:

  [php symfony distributed-cache:register-server|INFO pattern]
EOF;
    }

    protected function execute($arguments = array(), $options = array())
    {
        // initialize the database connection
        $databaseManager = new sfDatabaseManager($this->configuration);
        $connection = $databaseManager->getDatabase($options['connection'])->getConnection();

        $server_name = $arguments['server-name'];

        $server_name_file = sfConfig::get('sf_root_dir') . DIRECTORY_SEPARATOR . 'SERVER_NAME';

        // retrieve current server name from file SERVER_NAME
        if ($server_name !== file_get_contents($server_name_file))
        {
            echo "Attempting to write server name '".$server_name."' to SERVER_NAME file... ";
            if(file_put_contents($server_name_file, $server_name))
            {
                echoln('...successfull');
            }
            else
            {
                echoln('... not successful. Check permissions.');
            }
        }

        // get Server from database
        $server = ServerTable::getOrCreateOneByName($server_name);
        if($server)
        {
            echoln("Server '".$server_name."' successfully created and retrieved from database");
        }
        else
        {
            echoln("Could not retrieve server '".$server_name."' from database");
        }
    }

}
