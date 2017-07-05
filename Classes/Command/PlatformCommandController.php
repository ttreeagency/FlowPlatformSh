<?php
namespace Ttree\FlowPlatformSh\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use Symfony\Component\Yaml\Yaml;

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
     * Initialize configuration
     *
     * @param string $database Default database server, can be MySQL or PostgreSQL
     */
    public function booststrapCommand(string $database)
    {
        $package = $this->packageManager->createPackage('Ttree.FlowPlatformSh')->getPackagePath();
        $distribution = $package . 'Resources/Private/Installer/Distribution/' . $database . '/';
        $this->outputLine($distribution);
        Files::copyDirectoryRecursively($distribution, \FLOW_PATH_ROOT, true, true);
    }

    /**
     * Sync directory
     *
     * @param string $directory Source direction
     * @param bool $publish Run resource:publish on the remote serveur
     * @param bool $database Clone the database to the remote server
     * @param bool $migrate Run doctrine:migrate on the remote serveur
     * @param bool $debug Show debug output
     * @param bool $snapshot Create a snapshot before synchronization
     * @param string $configuration
     */
    public function syncCommand(string $directory = null, bool $publish = false, bool $database = false, bool $migrate = false, bool $debug = false, bool $snapshot = false, string $configuration = '.platform.app.yaml'): void
    {

        $this->outputLine();
        $this->outputLine('<b>Local -> platform.sh</b>');
        $this->outputLine();

        if ($snapshot) {
            $this->outputLine('    + <info>Create snapshot</info>');
            $this->executeShellCommand('platform snapshot:create', [], $debug);
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

            $this->executeShellCommand($rsyncCommand, [], $debug);
        }

        if ($publish) {
            $this->outputLine('    + <info>Publish resources</info>');
            $this->executeShellCommand('platform ssh "./flow resource:publish"', [], $debug);
        }
        if ($database) {
            $this->outputLine('    + <info>Clone database</info>');
            list ($_, $driver) = \explode('_', $this->databasesConfiguration['driver']);
            if (!isset($this->dumpCommands[$driver])) {
                $this->outputLine('<error>No dump command for the current driver (%s) </error>', [$this->databasesConfiguration['driver']]);
            }
            $dumpCommand = \str_replace([
                '@HOST@',
                '@DBNAME@',
                '@USER@',
                '@PASSWORD@',
                '@CHARSET@',
            ], [
                $this->databasesConfiguration['host'],
                $this->databasesConfiguration['dbname'],
                $this->databasesConfiguration['user'],
                $this->databasesConfiguration['password'],
                $this->databasesConfiguration['charset'],
            ], $this->dumpCommands[$driver]['*']);
            $this->executeShellCommand('%s > Data/Temporary/dump.sql', [$dumpCommand], $debug);
            $this->executeShellCommand('platform sql < Data/Temporary/dump.sql', [], $debug);
            unlink('Data/Temporary/dump.sql');
        }
        if ($migrate) {
            $this->outputLine('    + <info>Migrate database</info>');
            $this->executeShellCommand('platform ssh "./flow doctrine:migrate"', [], $debug);
        }
    }

    protected function executeShellCommand(string $command, array $arguments = [], bool $debug = false): string
    {
        $command = \vsprintf($command, $arguments);

        if ($debug) {
            $this->outputLine('    // Command: <comment>%s</comment>', [$command]);
        }

        $output = shell_exec($command . ' 2> /dev/null');

        return trim($output);
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
