<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Insight Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls the behavior of the application insight.
    | The insight collects request and performance data similar helping developers inspect and debug
    | application behavior in real time. You can enable and disable insight by make it true and false.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | The profiler automatically stores request and performance data as JSON
    | files under "storage/framework/profiler". To prevent excessive disk
    | usage, old entries are automatically removed after the specified number
    | of days.
    |
    | Default: 1 days
    |
    */

    'retention_days' => 1,
];
