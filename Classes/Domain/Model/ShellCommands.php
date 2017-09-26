<?php

namespace Ttree\FlowPlatformSh\Domain\Model;

final class ShellCommands
{
    /**
     * @var string
     */
    private $os;

    /**
     * @var string
     */
    private $databaseDriver;

    /**
     * @var string
     */
    protected $rsync;

    /**
     * @var string
     */
    protected $dump;

    /**
     * @var string
     */
    protected $restore;

    /**
     * @var string
     */
    protected $migrate;

    /**
     * @var string
     */
    protected $publish;

    /**
     * @var string
     */
    protected $flush;

    public function __construct(array $configuration, string $os, string $databaseDriver)
    {
        $this->os = $os;
        $this->databaseDriver = $databaseDriver;

        $this->rsync = $configuration['rsync'][$os] ?? $configuration['rsync']['*'];
        $this->migrate = $configuration['migrate'][$os] ?? $configuration['migrate']['*'];
        $this->publish = $configuration['publish'][$os] ?? $configuration['publish']['*'];
        $this->flush = $configuration['flush'][$os] ?? $configuration['flush']['*'];

        $this->dump = $configuration['dump'][$this->databaseDriver][$os] ?? $configuration['dump'][$this->databaseDriver]['*'];
        $this->restore = $configuration['restore'][$this->databaseDriver][$os] ?? $configuration['restore'][$this->databaseDriver]['*'];
    }

    public function rsyncCommand(): string
    {
        return $this->rsync;
    }

    public function dumpCommand(): string
    {
        return $this->dump;
    }

    public function restoreCommand(): string
    {
        return $this->restore;
    }

    public function migrateCommand(): string
    {
        return $this->migrate;
    }

    public function publishCommand(): string
    {
        return $this->publish;
    }

    public function flushCommand(): string
    {
        return $this->flush;
    }
}
