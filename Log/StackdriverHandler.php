<?php

namespace Igoooor\StackdriverBundle\Log;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Handler\PsrHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

class StackdriverHandler extends PsrHandler
{
    /**
     * @var LoggerInterface[]
     */
    protected array $loggers;
    protected ?LoggingClient $client;
    protected ?string $name;
    protected ?string $projectId;
    protected ?string $keyFile;

    public function __construct(string $projectId, string $name, int|string|Level $level = Level::Debug, ?string $keyFile = null)
    {
        if ('' === $projectId) {
            return;
        }

        $this->projectId = $projectId;
        $this->name   = $name;
        $this->level  = $level;
        $this->bubble = true;
        $this->keyFile = $keyFile;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record) || null === $this->getClient()) {
            return false;
        }

        $this->getLogger($record->channel)->log(strtolower($record->level->getName()), $record->message, $record->context);

        return !$this->bubble;
    }

    protected function getLogger(mixed $channel): LoggerInterface
    {
        if (!isset($this->loggers[$channel])) {
            $logger = $this->getClient()->psrLogger($this->name, [
                'labels' => ['context' => $channel],
            ]);
            $this->loggers[$channel] = $logger;
        }

        return $this->loggers[$channel];
    }

    private function getClient(): ?LoggingClient
    {
        if (null === $this->projectId) {
            return null;
        }

        if (null === $this->client) {
            $config = [
                'projectId' => $this->projectId,
            ];
            if (null !== $this->keyFile) {
                $config['keyFile'] = json_decode($this->keyFile, true);
            }
            $this->client = new LoggingClient($config);
        }

        return $this->client;
    }
}
