# Platform.sh for Flow Framework made easy

Flow framework utility package to parse Platform.sh variables

## Configure your distribution to deploy on Platform.sh

    composer require ttree/flowplatformsh
    ./flow platform:bootstrap --id fajq56c55mc5s --host eu.platform.sh --database MySQL|PostgreSQL

Check and modify the configuration to match your project:

- ```.platform/```
- ```.platform.app.yaml```
- ```.platform.env```

## Run a command during Build or Deploy hook

You can run any Flow CLI commands during build or deploy hook. Remember that during Build hook you don't have access to
services (database, cache, ...). This the default configuration:

	Ttree:
      FlowPlatformSh:
        buildHooks:
          commands:
            'flow:package:rescan': true
        deployHooks:
          commands:
            'flow:cache:flush': true
            'flow:cache:warmup': true
            'flow:doctrine:migrate': true

You can set the value to ```false``` to disable a command. If the value is an array, the array is passed as command arguments:

	Ttree:
	  FlowPlatformSh:
		deployHooks:
		  commands:
			'ttree.neosplatformsh:platform:createadminaccount':
			  username: 'admin'
			  password: 'changeme'
			'ttree.neosplatformsh:platform:importsitepackage':
			  package: 'Neos.Demo'

Check that your ```.platform.app.yaml``` execute ```php flow platform:build``` and ```php flow platform:build``` in the respective hook.

You can use a custom FLOW_CONTEXT during the build hook to avoid issue when Flow try to connect to redis cache, like this ```.platform.app.yaml```:

	variables:
      env:
        FLOW_CONTEXT: 'Production/PlatformSh'
        FLOW_PATH_TEMPORARY_BASE: '/tmp'
        FLOW_REWRITEURLS: 1
        
	hooks:
	  build: |
		set -e
		export FLOW_CONTEXT=${FLOW_CONTEXT}Build
		php flow platform:build
	  deploy: |
		set -e
		php flow platform:deploy

Check the command controller in [Ttree.NeosPlatformSh](https://github.com/ttreeagency/NeosPlatformSh).

## How to configure ```.platform.env```

This file extract variables from ```PLATFORM_RELATIONSHIPS``` and create env variables that you can use in 
your configuration (```Settings.yaml```, ```Caches.yaml```, ...). Every line contains the variable name and the
path to get the variable content from ```PLATFORM_RELATIONSHIPS```.

    DATABASE_HOST = database.0.host
    DATABASE_PORT = database.0.port
    DATABASE_NAME = database.0.path
    DATABASE_USER = database.0.username
    DATABASE_PASSWORD = database.0.password
    
    REDIS_HOST = redis.0.host
    REDIS_PORT = redis.0.port

    REDIS_ALTERNATIVE_HOST = redis.1.host
    REDIS_ALTERNATIVE_PORT = redis.1.port
    
    ELASTICSEARCH_HOST = elasticsearch.0.host
    ELASTICSEARCH_PORT = elasticsearch.0.port
    
Then you can edit your ```Settings.yaml``` to use the new env variables:

    Neos:
      Flow:
        persistence:
          backendOptions:
            driver: pdo_pgsql
            dbname: '%env:DATABASE_NAME%'
            port: '%env:DATABASE_PORT%'
            user: '%env:DATABASE_USER%'
            password: '%env:DATABASE_PASSWORD%'
            host: '%env:DATABASE_HOST%'

## Push local project data to platform.sh

You can sync a local project data (resources and databases) to platform with the following command:

    ./flow platform:push --directory Data/Persistent --publish --database --migrate --environment master
    
You can provide the path to your local ```.platform.app.yaml``` with the paramater ```--configuration```. 

The options ```--publish``` run the resources publishing after the rsync command on the remote server.

The options ```--database``` and ```--migrate``` clone the local database and run migration on the remote server.

The options ```--snapshot``` create a snapshot of the current platform environement before the synchronzation.

The options ```--environment```, default ```master```, allow to target specific platform environment.

**Warning**: Currently we push only files and databases, if you use ElasticSearch you need to rebuild the index manually.

You should see this output:

    Local -> platform.sh
    
        + Create Snapshot
        + Sync directory Data/Persistent
        + Publish resources
        + Clone database
        + Migrate database

### I use docker locally to host my database, the push command failed

You can use a custom dump command, by editing your ```Settings.yaml```, by example for PostgreSQL:

	Ttree:
      FlowPlatformSh:
        commands:
          'dump':
            'pgsql':
              '*': 'docker exec -e "PGPASSWORD=@PASSWORD@" -t [docker-container-name] pg_dump -c -b -d @DBNAME@ -U @USER@'

Replace ```[docker-container-name]``` by the name or the identifier of the PostgreSQL container.

## Acknowledgments

Development sponsored by [ttree ltd - neos solution provider](http://ttree.ch).

We try our best to craft this package with a lots of love, we are open to sponsoring, support request, ... just contact us.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
