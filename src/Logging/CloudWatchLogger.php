<?php

namespace HaseebHashim\CloudWatchLogs\Logging;

use Aws\Exception\AwsException;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Monolog\LogRecord;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use DateTimeInterface;

class CloudWatchLogger extends AbstractProcessingHandler
{
    protected CloudWatchLogsClient $client;
    protected string $groupName;
    protected string $streamName;
    protected ?int $retention;
    protected ?string $sequenceToken = null;

    public function __construct($level = Logger::DEBUG, bool $bubble = true, ?array $config = null, ?CloudWatchLogsClient $client = null)
    {
        parent::__construct($level, $bubble);

        $config = array_replace_recursive($this->defaultConfig(), $config ?? $this->loadLaravelConfig());

        $clientConfig = [
            'region'      => $config['region'],
            'version'     => 'latest',
        ];

        if (!empty($config['credentials']['key']) && !empty($config['credentials']['secret'])) {
            $clientConfig['credentials'] = [
                'key'    => $config['credentials']['key'],
                'secret' => $config['credentials']['secret'],
            ];
        }

        $this->client = $client ?? new CloudWatchLogsClient($clientConfig);

        $this->groupName  = $config['log_group'];
        $this->streamName = $this->resolveStreamName($config['log_stream']);
        $this->retention  = $this->normalizeRetention($config['retention']);

        $this->initializeCloudWatch();
    }

    protected function loadLaravelConfig(): array
    {
        if (function_exists('config')) {
            return config('cloudwatch-logs', []);
        }

        return [];
    }

    protected function defaultConfig(): array
    {
        return [
            'region' => 'us-east-1',
            'credentials' => [
                'key' => null,
                'secret' => null,
            ],
            'log_group' => 'laravel-app',
            'log_stream' => php_uname('n'),
            'retention' => 30,
        ];
    }

    /**
     * Initialize CloudWatch Logs
     */
    protected function initializeCloudWatch(): void
    {
        // Create log group
        try {
            $this->client->createLogGroup(['logGroupName' => $this->groupName]);

            // Set retention policy if provided
            if ($this->retention) {
                $this->client->putRetentionPolicy([
                    'logGroupName'    => $this->groupName,
                    'retentionInDays' => $this->retention,
                ]);
            }
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $e;
            }
        }

        // Create log stream
        try {
            $this->client->createLogStream([
                'logGroupName'  => $this->groupName,
                'logStreamName' => $this->streamName,
            ]);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $e;
            }
        }

        $this->getSequenceToken();
    }

    /**
     * Get the sequence token for the log stream
     */
    protected function getSequenceToken(): void
    {
        try {
            $result = $this->client->describeLogStreams([
                'logGroupName'        => $this->groupName,
                'logStreamNamePrefix' => $this->streamName,
            ]);

            foreach ($result['logStreams'] ?? [] as $stream) {
                if (($stream['logStreamName'] ?? null) === $this->streamName) {
                    $this->sequenceToken = $stream['uploadSequenceToken'] ?? null;
                    return;
                }
            }
        } catch (AwsException $e) {
            error_log('Failed to get sequence token: ' . $e->getMessage());
        }
    }

    /**
     * Write log record to CloudWatch
     */
    protected function write(LogRecord $record): void
    {
        $logEvent = [
            'message'   => is_string($record->formatted) ? $record->formatted : json_encode($record->formatted),
            'timestamp' => $this->milliseconds($record->datetime),
        ];

        $params = [
            'logGroupName'  => $this->groupName,
            'logStreamName' => $this->streamName,
            'logEvents'     => [$logEvent],
        ];

        if ($this->sequenceToken) {
            $params['sequenceToken'] = $this->sequenceToken;
        }

        try {
            $result = $this->client->putLogEvents($params);

            if (isset($result['nextSequenceToken'])) {
                $this->sequenceToken = $result['nextSequenceToken'];
            }
        } catch (AwsException $e) {
            if (in_array($e->getAwsErrorCode(), ['DataAlreadyAcceptedException', 'InvalidSequenceTokenException'], true)) {
                preg_match('/(?:sequenceToken is:|sequenceToken:)\s*(\S+)/', $e->getMessage(), $matches);
                if (isset($matches[1])) {
                    $this->sequenceToken = $matches[1];
                    $this->write($record);
                }
            } else {
                error_log('CloudWatch Logging Failed: ' . $e->getMessage());
            }
        }
    }

    protected function milliseconds(DateTimeInterface $dateTime): int
    {
        return ((int) $dateTime->format('U')) * 1000 + (int) $dateTime->format('v');
    }

    protected function normalizeRetention(mixed $retention): ?int
    {
        if ($retention === null || $retention === '' || $retention === 'null') {
            return null;
        }

        return (int) $retention;
    }

    protected function resolveStreamName(string $streamName): string
    {
        return str_replace(
            ['{{hostname}}', '{{env}}', '{{date}}'],
            [
                php_uname('n'),
                function_exists('config') ? (string) config('app.env', 'production') : 'production',
                date('Y-m-d'),
            ],
            $streamName
        );
    }
}
