<?php

namespace Ttree\FlowPlatformSh\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Exception;

/**
 * @Flow\Scope("singleton")
 */
class CommandService
{
    /**
     * @var array
     * @Flow\InjectConfiguration(package="Neos.Flow")
     */
    protected $settings;

    public function executeHooks(array $hooks, \Closure $outputLine): void
    {
        $outputLine('Execute commands from Settings.yaml ...');
        foreach ($hooks as $command => $arguments) {
            if ($arguments === false) {
                $outputLine('<comment>[NOTICE] Command "%s" skipped</comment>', [$command]);
                continue;
            }
            if (!\is_array($arguments)) {
                $arguments = [];
            }
            $outputLine('<info>[INFO] Command "%s"</info>', [$command]);
            $this->executeCommand($command, $arguments);
            $outputLine();
        }
    }

    protected function executeCommand(string $command, array $arguments = []): void
    {
        $executed = Scripts::executeCommand($command, $this->settings, true, $arguments);
        if ($executed !== true) {
            throw new Exception(sprintf('The command "%s" return an error, check your logs.', $command), 1346759496);
        }
    }
}
