<?php

namespace HaseebHashim\CloudWatchLogs\Tests;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use HaseebHashim\CloudWatchLogs\Logging\CloudWatchLogger;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class CloudWatchLoggerTest extends TestCase
{
    public function test_it_creates_cloudwatch_resources_and_writes_a_log_event(): void
    {
        $commands = [];
        $mock = new MockHandler([
            $this->recordResult($commands),
            $this->recordResult($commands),
            $this->recordResult($commands),
            $this->recordResult($commands, [
                'logStreams' => [
                    [
                        'logStreamName' => 'testing',
                        'uploadSequenceToken' => 'token-1',
                    ],
                ],
            ]),
            $this->recordResult($commands, ['nextSequenceToken' => 'token-2']),
        ]);

        $handler = new CloudWatchLogger(Logger::DEBUG, true, $this->config(), $this->client($mock));
        $logger = new Logger('testing');
        $logger->pushHandler($handler);

        $logger->info('Order created', ['order_id' => 123]);

        $this->assertSame([
            'CreateLogGroup',
            'PutRetentionPolicy',
            'CreateLogStream',
            'DescribeLogStreams',
            'PutLogEvents',
        ], array_column($commands, 'name'));

        $putLogEvents = $commands[4]['payload'];
        $this->assertSame('laravel-testing', $putLogEvents['logGroupName']);
        $this->assertSame('testing', $putLogEvents['logStreamName']);
        $this->assertSame('token-1', $putLogEvents['sequenceToken']);
        $this->assertStringContainsString('Order created', $putLogEvents['logEvents'][0]['message']);
    }

    public function test_it_retries_with_the_expected_sequence_token(): void
    {
        $commands = [];
        $mock = new MockHandler([
            $this->recordResult($commands),
            $this->recordResult($commands),
            $this->recordResult($commands, ['logStreams' => []]),
            function ($command) use (&$commands) {
                $commands[] = [
                    'name' => $command->getName(),
                    'payload' => $command->toArray(),
                ];

                return new AwsException(
                    'The given sequenceToken is invalid. The next expected sequenceToken is: retry-token',
                    $command,
                    ['code' => 'InvalidSequenceTokenException']
                );
            },
            $this->recordResult($commands, ['nextSequenceToken' => 'token-after-retry']),
        ]);

        $config = $this->config(['retention' => null]);
        $handler = new CloudWatchLogger(Logger::DEBUG, true, $config, $this->client($mock));
        $logger = new Logger('testing');
        $logger->pushHandler($handler);

        $logger->warning('Retry me');

        $this->assertSame('PutLogEvents', $commands[3]['name']);
        $this->assertArrayNotHasKey('sequenceToken', $commands[3]['payload']);
        $this->assertSame('PutLogEvents', $commands[4]['name']);
        $this->assertSame('retry-token', $commands[4]['payload']['sequenceToken']);
    }

    private function client(MockHandler $mock): CloudWatchLogsClient
    {
        return new CloudWatchLogsClient([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => 'testing',
                'secret' => 'testing',
            ],
            'handler' => $mock,
        ]);
    }

    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'region' => 'us-east-1',
            'credentials' => [
                'key' => null,
                'secret' => null,
            ],
            'log_group' => 'laravel-testing',
            'log_stream' => 'testing',
            'retention' => 30,
        ], $overrides);
    }

    private function recordResult(array &$commands, array $result = []): callable
    {
        return function ($command) use (&$commands, $result) {
            $commands[] = [
                'name' => $command->getName(),
                'payload' => $command->toArray(),
            ];

            return new Result($result);
        };
    }
}
