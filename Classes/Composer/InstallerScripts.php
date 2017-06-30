<?php
namespace Ttree\FlowPlatformSh\Composer;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Files;

class InstallerScripts
{
    public static function postUpdateAndInstall(Event $event)
    {
        if (!defined('FLOW_PATH_ROOT')) {
            define('FLOW_PATH_ROOT', Files::getUnixStylePath(getcwd()) . '/');
        }

        if (!defined('FLOW_PATH_PACKAGES')) {
            define('FLOW_PATH_PACKAGES', Files::getUnixStylePath(getcwd()) . '/Packages/');
        }

        if (!defined('FLOW_PATH_CONFIGURATION')) {
            define('FLOW_PATH_CONFIGURATION', Files::getUnixStylePath(getcwd()) . '/Configuration/');
        }

        Files::copyDirectoryRecursively('Packages/Application/Ttree.FlowPlatformSh/Resources/Private/Installer/Distribution/Defaults', './', false, true);
    }
}
