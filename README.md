# Laravel CloudWatch Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/haseebhashim/laravel-cloudwatch-logger.svg?style=flat-square)](https://packagist.org/packages/haseebhashim/laravel-cloudwatch-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/haseebhashim/laravel-cloudwatch-logger.svg?style=flat-square)](https://packagist.org/packages/haseebhashim/laravel-cloudwatch-logger)
[![License](https://img.shields.io/packagist/l/haseebhashim/laravel-cloudwatch-logger.svg?style=flat-square)](LICENSE.txt)

AWS CloudWatch Logs handler for Laravel. It plugs into Laravel's standard logging system through Monolog, creates the configured log group and stream when needed, and sends application logs to CloudWatch.

## Features

- Laravel logging channel support through Monolog
- Automatic CloudWatch log group and log stream creation
- Optional log retention policy setup
- Sequence token discovery and retry handling
- Supports AWS access keys or the default AWS credential provider chain
- Dynamic stream placeholders for hostname, environment, and date
- Compatible with Laravel 10, 11, and 12

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- AWS credentials with CloudWatch Logs permissions

## Installation

Install the package with Composer:

```bash
composer require haseebhashim/laravel-cloudwatch-logger
```

Laravel auto-discovers the service provider. If package auto-discovery is disabled, register the provider manually:

```php
HaseebHashim\CloudWatchLogs\CloudWatchLogsServiceProvider::class,
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="HaseebHashim\CloudWatchLogs\CloudWatchLogsServiceProvider" --tag="config"
```

## Environment

Add the values you need to your `.env` file:

```env
AWS_DEFAULT_REGION=us-east-1

# Optional when you use IAM roles, ECS/EKS task roles, SSO, or another AWS provider.
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=

CLOUDWATCH_LOG_GROUP=laravel-app
CLOUDWATCH_LOG_STREAM="{{env}}-{{hostname}}-{{date}}"
CLOUDWATCH_LOG_RETENTION=30
CLOUDWATCH_LOG_LEVEL=debug
```

Supported stream placeholders are resolved when the CloudWatch handler is created:

- `{{hostname}}`: server hostname
- `{{env}}`: Laravel application environment
- `{{date}}`: current date in `YYYY-MM-DD` format

Set `CLOUDWATCH_LOG_RETENTION=null` in `config/cloudwatch-logs.php` if you want CloudWatch to keep logs indefinitely.

## Laravel Logging Channel

Add a CloudWatch channel to `config/logging.php`:

```php
use HaseebHashim\CloudWatchLogs\Logging\CloudWatchLogger;
use Monolog\Formatter\JsonFormatter;

'channels' => [
    'cloudwatch' => [
        'driver' => 'monolog',
        'handler' => CloudWatchLogger::class,
        'level' => env('CLOUDWATCH_LOG_LEVEL', 'debug'),
        'formatter' => JsonFormatter::class,
    ],

    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'cloudwatch'],
        'ignore_exceptions' => false,
    ],
],
```

Use Laravel logging normally:

```php
use Illuminate\Support\Facades\Log;

Log::info('User logged in', [
    'user_id' => auth()->id(),
    'ip' => request()->ip(),
]);

Log::channel('cloudwatch')->error('Payment failed', [
    'payment_id' => $payment->id,
]);
```

## AWS Permissions

The AWS user or role needs these CloudWatch Logs permissions:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:DescribeLogStreams",
        "logs:PutLogEvents",
        "logs:PutRetentionPolicy"
      ],
      "Resource": "arn:aws:logs:*:*:*"
    }
  ]
}
```

If you create the log group and retention policy yourself, you can remove `logs:CreateLogGroup` and `logs:PutRetentionPolicy` from the application's runtime role.

## Configuration

The published config file is `config/cloudwatch-logs.php`.

```php
return [
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    'log_group' => env('CLOUDWATCH_LOG_GROUP', 'laravel-app'),
    'log_stream' => env('CLOUDWATCH_LOG_STREAM', php_uname('n')),
    'retention' => env('CLOUDWATCH_LOG_RETENTION', 30),
    'level' => env('CLOUDWATCH_LOG_LEVEL', 'debug'),
];
```

When `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` are empty, the AWS SDK uses its normal credential provider chain. This is usually best for production on AWS infrastructure.

## Test A Log

You can verify the channel from Tinker:

```bash
php artisan tinker
```

```php
Log::channel('cloudwatch')->info('CloudWatch test log', ['time' => now()->toISOString()]);
```

Then check the configured log group and stream in the AWS CloudWatch console.

You can also add a temporary route to `routes/web.php` in your Laravel application:

```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/test-cloudwatch-log', function () {
    Log::channel('cloudwatch')->info('CloudWatch test route log', [
        'time' => now()->toISOString(),
        'environment' => app()->environment(),
        'url' => request()->fullUrl(),
    ]);

    return response()->json([
        'message' => 'CloudWatch test log sent.',
        'log_group' => config('cloudwatch-logs.log_group'),
        'log_stream' => config('cloudwatch-logs.log_stream'),
    ]);
});
```

Open `/test-cloudwatch-log` once, confirm the log appears in CloudWatch, and then remove the route.

## Troubleshooting

**Logs do not appear in CloudWatch**

- Confirm `AWS_DEFAULT_REGION` matches the region you are viewing in AWS.
- Confirm the log group and stream names match your `.env` values.
- Confirm the IAM user or role has `logs:PutLogEvents` and `logs:DescribeLogStreams`.
- Check `storage/logs/laravel.log` for local application errors.

**Missing credentials**

- Set `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`, or run the app with an IAM role / task role / configured AWS profile.

**ResourceAlreadyExistsException**

- This is expected when the log group or stream already exists. The handler catches that AWS response and continues.

**InvalidSequenceTokenException**

- The handler refreshes the sequence token from the AWS error message and retries the write.

## Development

Install dependencies:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Run a syntax check:

```bash
composer lint
```

Validate the Composer package metadata:

```bash
composer validate --strict
```

## License

The MIT License. See [LICENSE.txt](LICENSE.txt).
