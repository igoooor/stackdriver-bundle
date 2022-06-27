<?php

namespace Igoooor\StackdriverBundle\Listener;

use Google\Cloud\Core\Report\SimpleMetadataProvider;
use Google\Cloud\ErrorReporting\Bootstrap;
use Google\Cloud\Logging\LoggingClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class StackdriverExceptionSubscriber implements EventSubscriberInterface
{
    private ?LoggingClient $client;
    private ?string $name;
    private ?string $projectId;
    private ?string $serverEnvironment;
    private ?string $keyFile;
    private ?TokenStorageInterface $tokenStorage;
    private array $excludedExceptions = [];

    public function __construct(string $projectId, string $name, string $serverEnvironment, ?string $keyFile = null, array $excludedExceptions = [], ?TokenStorageInterface $tokenStorage = null)
    {
        if ('' === $projectId) {
            return;
        }

        $this->name   = $name;
        $this->projectId = $projectId;
        $this->serverEnvironment = $serverEnvironment;
        $this->keyFile = $keyFile;
        $this->tokenStorage = $tokenStorage;
        $this->excludedExceptions = $excludedExceptions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [['logException', 10]],
        ];
    }

    public function logException(ExceptionEvent $event): void
    {
        if (null === $this->getClient()) {
            return;
        }

        $exception = $event->getThrowable();
        foreach ($this->excludedExceptions as $excludedException) {
            if ($exception instanceof $excludedException) {
                return;
            }
        }

        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : $exception->getCode();
        $metadata = new SimpleMetadataProvider([], $this->projectId, $this->name, $this->serverEnvironment);
        $psrLogger = $this->getClient()->psrLogger('error-log', [
            'metadataProvider' => $metadata,
        ]);
        Bootstrap::init($psrLogger);
        //Bootstrap::exceptionHandler($exception);
        //https://github.com/googleapis/google-cloud-php/issues/1975
        $httpRequest = [];
        $request = $event->getRequest();
        if ($request) {
            $httpRequest = [
                'method'             => $request->getMethod(),
                'url'                => $request->getUri(),
                'userAgent'          => $request->headers->get('User-Agent'),
                'responseStatusCode' => $statusCode,
                'remoteIp'           => $this->getRemoteIpAddress($request),
            ];
        }

        $psrLogger->error(
            sprintf('PHP Notice: %s', (string) $exception),
            [
                'context'        => [
                    'httpRequest'    => $httpRequest,
                    'user' => $this->getUser(),
                    'reportLocation' => [
                        'filePath'     => $exception->getFile(),
                        'lineNumber'   => $exception->getLine(),
                        'functionName' =>
                            self::getFunctionNameForReport($exception->getTrace()),
                    ],
                ],
                'serviceContext' => [
                    'service' => $psrLogger->getMetadataProvider()->serviceId(),
                    'version' => $psrLogger->getMetadataProvider()->versionId(),
                ],
            ]
        );
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
            $client = new LoggingClient($config);
            $this->client = $client;
        }

        return $this->client;
    }

    private function getRemoteIpAddress(Request $request): ?string
    {
        if ($request->server->has('HTTP_CF_CONNECTING_IP')) {
            return $request->server->get('HTTP_CF_CONNECTING_IP');
        }

        if ($request->server->has('HTTP_X_FORWARDED_FOR')) {
            return $request->server->get('HTTP_X_FORWARDED_FOR');
        }

        if ($request->server->has('REMOTE_ADDR')) {
            return $request->server->get('REMOTE_ADDR');
        }

        return null;
    }

    /**
     * Format the function name from a stack trace. This could be a global
     * function (function_name), a class function (Class->function), or a static
     * function (Class::function).
     *
     * @param array $trace The stack trace returned from Exception::getTrace()
     */
    private static function getFunctionNameForReport(array $trace = null)
    {
        if (null === $trace) {
            return '<unknown function>';
        }
        if (empty($trace[0]['function'])) {
            return '<none>';
        }
        $functionName = [$trace[0]['function']];
        if (isset($trace[0]['type'])) {
            $functionName[] = $trace[0]['type'];
        }
        if (isset($trace[0]['class'])) {
            $functionName[] = $trace[0]['class'];
        }
        return implode('', array_reverse($functionName));
    }

    private function getUser(): ?string
    {
        if (null === $this->tokenStorage) {
            return null;
        }

        if (null === $token = $this->tokenStorage->getToken()) {
            return null;
        }

        if (!is_object($user = $token->getUser()) || !$user instanceof UserInterface) {
            return null;
        }

        return $user->getUserIdentifier();
    }
}
