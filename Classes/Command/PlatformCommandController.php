<?php
namespace Ttree\FlowPlatformSh\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Files;

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
}
