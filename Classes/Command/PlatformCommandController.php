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
     * @param string $directory
     * @param string $configuration
     * @param bool $publish
     * @param bool $clean
     * @internal param bool $ressource
     */
    public function rsyncCommand(string $directory, string $configuration = '.platform.app.yaml', bool $publish = false, bool $clean = false)
    {
        $directory = trim($directory, '/');
        if (!\is_dir($directory)) {
            $this->outputLine('<error>Directory "%s" not found</error>', [$directory]);
            $this->quit(1);
        }
        $this->outputLine();
        $this->outputLine('<b>Sync %s to platform.sh ...</b>', [$directory]);
        $this->outputLine();

        $data = Yaml::parse(\FLOW_PATH_ROOT . '.platform.app.yaml');
        $mounts = Arrays::getValueByPath($data, 'mounts') ?: [];
        $mountPath = '/'. $directory;
        if (!isset($mounts[$mountPath])) {
            $this->outputLine('<error>Directory "%s" not mounted to a read write mound, check your %s</error>', [$directory, $configuration]);
            $this->outputMounts($mounts);
            $this->quit(1);
        }

        $os = \php_uname('s');

        if ($os === 'Darwin') {
            $rsyncCommand = 'rsync -az --iconv=utf-8-mac,utf-8 ./%1$s `platform ssh --pipe`:/app/%1$s/';
        } else {
            $rsyncCommand = 'rsync -az ./%1$s `platform ssh --pipe`:/app/%1$s/';
        }
        $this->executeShellCommand($rsyncCommand, [$directory], true);

        if ($publish) {
            $this->executeShellCommand('platform ssh "./flow resource:publish"', [], true);
        }
        if ($clean) {
            $this->executeShellCommand('platform ssh "./flow resource:clean"', [], true);
        }
    }

    protected function executeShellCommand(string $command, array $arguments = [], bool $debug = false): string
    {
        $command = \vsprintf($command, $arguments);

        if ($debug) {
            $this->outputLine('// Command: <comment>%s</comment>', [$command]);
        }

        $output = shell_exec($command . ' 2> /dev/null');

        return trim($output);
    }

    protected function outputMounts(array $mounts)
    {
        if (count($mounts) < 1) {
            return;
        }
        $this->outputLine();
        $this->outputLine('<info>Available in mounts in %s</info>', [$configuration]);
        foreach ($mounts as $mountPath => $mountDestination) {
            $this->outputLine('  %s => %s', [$mountPath, $mountDestination]);
        }
    }
}
