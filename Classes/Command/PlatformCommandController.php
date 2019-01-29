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
use Ttree\FlowPlatformSh\Domain\Model\ShellCommands;
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
     * @Flow\InjectConfiguration(path="commands")
     */
    protected $commandConfiguration;

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
     * platform.sh -> Local
     *
     * @param string|null $directory
     * @param bool $publish
     * @param bool $database
     * @param bool $migrate
     * @param bool $flush
     * @param $dryRun $debug
     * @param string $configuration
     * @param string $environment
     * @param bool $yes
     */
    public function pullCommand(string $directory = null, bool $publish = false, bool $database = false, bool $migrate = false, bool $flush = false, bool $dryRun = false, string $configuration = '.platform.app.yaml', string $environment = 'master', bool $yes = false)
    {
        $this->askForUserAgreement($yes, '<info>Are you sure, all local data will be deleted ?</info> (yes|<b>no</b>) ', '<b>platform.sh -> Local</b>');

        $shellConfiguration = $this->shellConfiguration('pull');
        $platformConfiguration = $this->platformConfiguration($configuration);

        if ($database) {
            $this->database($shellConfiguration, $environment, $dryRun);
        }
        if ($migrate) {
            $this->migrate($shellConfiguration, $environment, $dryRun);
        }
        if ($directory) {
            $this->rsync($directory, $shellConfiguration, $platformConfiguration, $environment, $dryRun);
        }
        if ($publish) {
            $this->publish($shellConfiguration, $environment, $dryRun);
        }
        if ($flush) {
            $this->flush($shellConfiguration, $environment, $dryRun);
        }
    }

    /**
     * Local -> platform.sh
     *
     * @param string $directory Source direction
     * @param bool $publish Run resource:publish on the remote serveur
     * @param bool $database Clone the database to the remote server
     * @param bool $migrate Run doctrine:migrate on the remote serveur
     * @param bool $flush Run doctrine:migrate on the remote serveur
     * @param bool $dryRun Dry run, don't execute commands
     * @param bool $snapshot Create a snapshot before synchronization
     * @param string $configuration
     * @param string $environment
     * @param bool $yes
     */
    public function pushCommand(string $directory = null, bool $publish = false, bool $database = false, bool $migrate = false, bool $flush = false, bool $dryRun = false, bool $snapshot = false, string $configuration = '.platform.app.yaml', string $environment = 'master', bool $yes = false): void
    {
        $this->askForUserAgreement($yes, '<info>Are you sure, all data on the remote target will be deleted ?</info> [yes|no]', '<b>Local -> platform.sh</b>');

        $shellConfiguration = $this->shellConfiguration('push');
        $platformConfiguration = $this->platformConfiguration($configuration);

        if ($snapshot) {
            $this->snapshot($environment, $dryRun);
        }
        if ($directory) {
            $this->rsync($directory, $shellConfiguration, $platformConfiguration, $environment, $dryRun);
        }
        if ($database) {
            $this->database($shellConfiguration, $environment, $dryRun);
        }
        if ($migrate) {
            $this->migrate($shellConfiguration, $environment, $dryRun);
        }
        if ($publish) {
            $this->publish($shellConfiguration, $environment, $dryRun);
        }
        if ($flush) {
            $this->flush($shellConfiguration, $environment, $dryRun);
        }
    }

    protected function platformConfiguration(string $configuration) :array
    {
        return Yaml::parse(\FLOW_PATH_ROOT . $configuration);
    }

    protected function shellConfiguration(string $type) :ShellCommands
    {
        $os = \php_uname('s');
        $databaseDriver = \explode('_', $this->databasesConfiguration['driver'])[1];
        return new ShellCommands($this->commandConfiguration[$type], $os, $databaseDriver);
    }

    protected function askForUserAgreement(bool $yes, string $question, string $message): void
    {
        $this->outputLine();
        $this->outputLine($message);
        $this->outputLine();

        if (!$yes) {
            $agree = $this->output->ask($question, 'no');
            if ($agree !== 'yes') {
                $this->quit(1);
            }
            $this->outputLine();
        }
    }

    protected function flush(ShellCommands $shellConfiguration, string $environment, bool $dryRun): void
    {
        $this->outputLine('    + <info>Flush all caches</info>');
        $this->executeShellCommand($shellConfiguration->flushCommand(), [
            '@ENVIRONMENT@' => $environment
        ], $dryRun);
    }

    protected function migrate(ShellCommands $shellConfiguration, string $environment, bool $dryRun): void
    {
        $this->outputLine('    + <info>Migrate database</info>');
        $this->executeShellCommand($shellConfiguration->migrateCommand(), [
            '@ENVIRONMENT@' => $environment
        ], $dryRun);
    }

    protected function database(ShellCommands $shellConfiguration, string $environment, bool $dryRun): void
    {
        $this->outputLine('    + <info>Clone database</info>');

        $dumpCommand = $this->replace([
            '@HOST@' => $this->databasesConfiguration['host'],
            '@DBNAME@' => $this->databasesConfiguration['dbname'],
            '@USER@' => $this->databasesConfiguration['user'],
            '@PASSWORD@' => $this->databasesConfiguration['password'],
            '@CHARSET@' => $this->databasesConfiguration['charset'],
        ], $shellConfiguration->dumpCommand());

        $this->executeShellCommand('@DUMP_COMMAND@', [
            '@DUMP_COMMAND@' => $dumpCommand,
            '@ENVIRONMENT@' => $environment
        ], $dryRun);

        $this->executeShellCommand($shellConfiguration->restoreCommand(), [
            '@HOST@' => $this->databasesConfiguration['host'],
            '@DBNAME@' => $this->databasesConfiguration['dbname'],
            '@USER@' => $this->databasesConfiguration['user'],
            '@PASSWORD@' => $this->databasesConfiguration['password'],
            '@CHARSET@' => $this->databasesConfiguration['charset'],
            '@ENVIRONMENT@' => $environment
        ], $dryRun);
    }

    protected function publish(ShellCommands $shellConfiguration, string $environment, bool $dryRun): void
    {
        $this->outputLine('    + <info>Publish resources</info>');
        $this->executeShellCommand($shellConfiguration->publishCommand(), [
            '@ENVIRONMENT@' => $environment
        ], $dryRun);
    }

    protected function snapshot(string $environment, bool $dryRun): void
    {
        $this->outputLine('    + <info>Create snapshot</info>');
        $this->executeShellCommand('platform snapshot:create -e @ENVIRONMENT@', [
            '@ENVIRONMENT@' => $environment
        ], $dryRun);
    }

    protected function rsync(string $directory, ShellCommands $shellConfiguration, array $platformConfiguration, string $environment, bool $dryRun): void
    {
        $directory = trim($directory, '/');

        $this->outputLine('    + <info>Sync directory %s</info>', [$directory]);

        if (!\is_dir($directory)) {
            $this->outputLine('    + <error>Directory "%s" not found</error>', [$directory]);
            $this->quit(1);
        }

        $mounts = Arrays::getValueByPath($platformConfiguration, 'mounts') ?: [];
        $mountPath = '/' . $directory;
        if (!isset($mounts[$mountPath])) {
            $this->outputLine('<error>Directory "%s" not mounted to a read write mound, check your %s</error>', [$directory, $shellConfiguration]);
            $this->outputMounts($mounts);
            $this->quit(1);
        }

        $rsyncCommand = \str_replace(['@DIRECTORY@'], [$directory], $shellConfiguration->rsyncCommand());

        $this->executeShellCommand($rsyncCommand, [
            '@ENVIRONMENT@' => $environment
        ], $dryRun);
    }

    /**
     * Run command for build hook
     * @param bool $debug Debug command identifier
     */
    public function buildCommand($debug = false)
    {
        $this->outputLine('<b>Run build hook commands</b>');
        $this->commandService->executeHooks($this->buildHooks, function (...$args) { $this->outputLine(...$args); } );
        $this->sendAndExit(0);
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

    protected function executeShellCommand(string $command, array $arguments = [], bool $dryRun = false) :string
    {
        if ($arguments !== []) {
            $command = $this->replace($arguments, $command);
        }

        if ($dryRun) {
            $this->outputLine('    <comment>Command</comment>: %s', [$command]);
            $this->outputLine();
            return $command;
        } else {
            exec($command . '', $output, $return);

            if ($return !== 0) {
                $this->outputLine('<error>Oups, the following command failed:</error> %s', [$command]);
                $this->quit($return);
            }

            return trim(implode(\PHP_EOL, $output));
        }
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
