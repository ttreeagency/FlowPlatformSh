<?php
namespace Ttree\FlowPlatformSh\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use Symfony\Component\Yaml\Yaml;
use Ttree\FlowPlatformSh\Annotations\BuildHook;
use Ttree\FlowPlatformSh\Annotations\DeployHook;
use Ttree\FlowPlatformSh\Service\CommandService;

/**
 * @Flow\Scope("singleton")
 */
class PlatformCommandController extends CommandController
{
    /**
     * @var PackageManager
     * @Flow\Inject
     */
    protected $packageManager;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="persistence.backendOptions", package="Neos.Flow")
     */
    protected $databasesConfiguration;

    /**
     * @var CommandService
     * @Flow\Inject
     */
    protected $commandService;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="commands.rsync")
     */
    protected $rsyncCommands = [];

    /**
     * @var array
     * @Flow\InjectConfiguration(path="commands.dump")
     */
    protected $dumpCommands = [];

    /**
     * @var array
     * @Flow\InjectConfiguration(path="buildHooks.commands")
     */
    protected $buildHooks = [];

    /**
     * @var array
     * @Flow\InjectConfiguration(path="deployHooks.commands")
     */
    protected $deployHooks = [];

    /**
     * Initialize configuration
     *
     * @param string $id Platform.sh Project ID
     * @param string $host Platform.sh Region (like eu.platform.sh)
     * @param string $database Default database server, can be MySQL or PostgreSQL
     */
    public function bootstrapCommand(string $id, string $host, string $database)
    {
        $package = $this->packageManager->createPackage('Ttree.FlowPlatformSh')->getPackagePath();
        $distribution = $package . 'Resources/Private/Installer/Distribution/' . $database . '/';
        $this->outputLine($distribution);
        Files::copyDirectoryRecursively($distribution, \FLOW_PATH_ROOT, true, true);
        $project = [
            'id: ' . $id,
            'host: ' . $host,
        ];
        \file_put_contents(\FLOW_PATH_ROOT . '.platform/local/project.yaml', \implode(chr(10), $project));
    }

    /**
     * Push local Resources + Database
     *
     * @param string $directory Source direction
     * @param bool $publish Run resource:publish on the remote serveur
     * @param bool $database Clone the database to the remote server
     * @param bool $migrate Run doctrine:migrate on the remote serveur
     * @param bool $debug Show debug output
     * @param bool $snapshot Create a snapshot before synchronization
     * @param string $configuration
     */
    public function pushCommand(string $directory = null, bool $publish = false, bool $database = false, bool $migrate = false, bool $debug = false, bool $snapshot = false, string $configuration = '.platform.app.yaml', string $environment = 'master'): void
    {

        $this->outputLine();
        $this->outputLine('<b>Local -> platform.sh</b>');
        $this->outputLine();

        if ($snapshot) {
            $this->outputLine('    + <info>Create snapshot</info>');
            $this->executeShellCommand('platform snapshot:create -e @ENVIRONMENT@', [
                '@ENVIRONMENT@' => $environment
            ], $debug);
        }

        if ($directory) {
            $directory = trim($directory, '/');

            $this->outputLine('    + <info>Sync directory %s</info>', [$directory]);

            if (!\is_dir($directory)) {
                $this->outputLine('    + <error>Directory "%s" not found</error>', [$directory]);
                $this->quit(1);
            }

            $data = Yaml::parse(\FLOW_PATH_ROOT . '.platform.app.yaml');
            $mounts = Arrays::getValueByPath($data, 'mounts') ?: [];
            $mountPath = '/' . $directory;
            if (!isset($mounts[$mountPath])) {
                $this->outputLine('<error>Directory "%s" not mounted to a read write mound, check your %s</error>', [$directory, $configuration]);
                $this->outputMounts($mounts);
                $this->quit(1);
            }

            $os = \php_uname('s');

            if (isset($this->rsyncCommands[$os])) {
                $rsyncCommand = $this->rsyncCommands[$os];
            } else {
                $rsyncCommand = $this->rsyncCommands['*'];
            }
            $rsyncCommand = \str_replace(['@DIRECTORY@'], [$directory], $rsyncCommand);

            $this->executeShellCommand($rsyncCommand, [
                '@ENVIRONMENT@' => $environment
            ], $debug);
        }

        if ($publish) {
            $this->outputLine('    + <info>Publish resources</info>');
            $this->executeShellCommand('platform ssh -e @ENVIRONMENT@ "./flow resource:publish"', [
                '@ENVIRONMENT@' => $environment
            ], $debug);
        }
        if ($database) {
            $this->outputLine('    + <info>Clone database</info>');
            $driver = \explode('_', $this->databasesConfiguration['driver'])[1];
            if (!isset($this->dumpCommands[$driver])) {
                $this->outputLine('<error>No dump command for the current driver (%s) </error>', [$this->databasesConfiguration['driver']]);
            }

            $dumpCommand = $this->replace([
                '@HOST@' => $this->databasesConfiguration['host'],
                '@DBNAME@' => $this->databasesConfiguration['dbname'],
                '@USER@' => $this->databasesConfiguration['user'],
                '@PASSWORD@' => $this->databasesConfiguration['password'],
                '@CHARSET@' => $this->databasesConfiguration['charset'],
            ], $this->dumpCommands[$driver]['*']);

            $this->executeShellCommand('@DUMP_COMMAND@ > Data/Temporary/dump.sql', [
                '@DUMP_COMMAND@' => $dumpCommand,
                '@ENVIRONMENT@' => $environment
            ], $debug);

            $this->executeShellCommand('platform sql -e @ENVIRONMENT@ < Data/Temporary/dump.sql', [
                '@ENVIRONMENT@' => $environment
            ], $debug);
            unlink('Data/Temporary/dump.sql');
        }
        if ($migrate) {
            $this->outputLine('    + <info>Migrate database</info>');
            $this->executeShellCommand('platform ssh -e @ENVIRONMENT@ "./flow doctrine:migrate"', [
                '@ENVIRONMENT@' => $environment
            ], $debug);
        }
    }

    /**
     * Run command for build hook
     * @param bool $debug Debug command identifier
     */
    public function buildCommand($debug = false)
    {
        $this->outputLine('<b>Run build hook commands</b>');
        $this->commandService->executeHooks($this->buildHooks, function (...$args) { $this->outputLine(...$args); } );
    }

    /**
     * Run command for deploy hook
     * @param bool $debug Debug command identifier
     */
    public function deployCommand($debug = false)
    {
        $this->outputLine('<b>Run deploy hook commands</b>');
        $this->commandService->executeHooks($this->deployHooks, function (...$args) { $this->outputLine(...$args); } );
    }

    protected function executeShellCommand(string $command, array $arguments = [], bool $debug = false): string
    {
        if ($arguments !== []) {
            $command = $this->replace($arguments, $command);
        }

        if ($debug) {
            $this->outputLine('    // Command: <comment>%s</comment>', [$command]);
        }

        exec($command . ' 2> /dev/null', $output, $return);

        if ($return !== 0) {
            $this->outputLine('<error>Oups, the following command failed:</error> %s', [$command]);
            $this->quit($return);
        }

        return trim(implode(\PHP_EOL, $output));
    }

    protected function replace(array $search, string $string) {
        if ($search === []) {
            return $string;
        }
        return \str_replace(\array_keys($search), \array_values($search), $string);
    }

    protected function outputMounts(array $mounts): void
    {
        if (count($mounts) < 1) {
            return;
        }
        $this->outputLine();
        $this->outputLine('<info>Available in mounts</info>');
        foreach ($mounts as $mountPath => $mountDestination) {
            $this->outputLine('  %s => %s', [$mountPath, $mountDestination]);
        }
    }
}
