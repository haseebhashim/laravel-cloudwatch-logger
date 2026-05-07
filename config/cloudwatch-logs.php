<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS Region
    |--------------------------------------------------------------------------
    |
    | The AWS region where your CloudWatch Logs are located.
    |
    */
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    /*
    |--------------------------------------------------------------------------
    | AWS Credentials
    |--------------------------------------------------------------------------
    |
    | Optional AWS access key ID and secret access key. Leave these empty when
    | using IAM roles, ECS/EKS task roles, SSO, or the default AWS credential
    | provider chain.
    |
    */
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CloudWatch Log Group
    |--------------------------------------------------------------------------
    |
    | The name of the CloudWatch Log Group.
    |
    */
    'log_group' => env('CLOUDWATCH_LOG_GROUP', 'laravel-app'),

    /*
    |--------------------------------------------------------------------------
    | CloudWatch Log Stream
    |--------------------------------------------------------------------------
    |
    | The name of the CloudWatch Log Stream. Example:
    | {{env}}-{{hostname}}-{{date}}
    |
    | Supported placeholders: {{hostname}}, {{env}}, {{date}}
    |
    */
    'log_stream' => env('CLOUDWATCH_LOG_STREAM', php_uname('n')),

    /*
    |--------------------------------------------------------------------------
    | Retention Days
    |--------------------------------------------------------------------------
    |
    | Number of days to retain logs. Set to null for unlimited retention.
    | Valid values: 1, 3, 5, 7, 14, 30, 60, 90, 120, 150, 180, 365, 400, 545, 731, 1827, 3653
    |
    */
    'retention' => env('CLOUDWATCH_LOG_RETENTION', 30),

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | Minimum log level to send to CloudWatch.
    |
    */
    'level' => env('CLOUDWATCH_LOG_LEVEL', 'debug'),
];
