<?php

namespace Ttree\FlowPlatformSh\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Neos\Utility\Files;

class InstallerScripts implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'postUpdateAndInstall',
            ScriptEvents::POST_UPDATE_CMD  => 'postUpdateAndInstall',
        ];
    }

    public static function postUpdateAndInstall(Event $composerEvent)
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
        $io = $composerEvent->getIO();

        $io->write('<info>Platform.sh:</info>  Copy default configuration...');
        Files::copyDirectoryRecursively('Packages/Application/Ttree.FlowPlatformSh/Resources/Private/Installer/Distribution/Defaults', './', false, true);
    }
}
