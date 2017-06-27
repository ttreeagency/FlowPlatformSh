# Platform.sh for Flow Framework made easy

Flow framework utility package to parse Platform.sh variables

## How to use ?

Create a file ```.platform.env``` in your distribution directory:

    DATABASE_HOST = database.0.host
    DATABASE_PORT = database.0.port
    DATABASE_NAME = database.0.path
    DATABASE_USER = database.0.username
    DATABASE_PASSWORD = database.0.password
    
    DATABASE_HOST = redis.0.host
    DATABASE_PORT = redis.0.port

    ELASTICSEARCH_HOST = elasticsearch.0.host
    ELASTICSEARCH_PORT = elasticsearch.0.port
    
Based on this configuration, this package will create env variables from ```PLATFORM_RELATIONSHIPS```. 

## Acknowledgments

Development sponsored by [ttree ltd - neos solution provider](http://ttree.ch).

We try our best to craft this package with a lots of love, we are open to sponsoring, support request, ... just contact us.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
