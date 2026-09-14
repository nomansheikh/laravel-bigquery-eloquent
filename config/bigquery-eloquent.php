<?php

return [
    'project_id' => env('BIGQUERY_PROJECT_ID', ''),

    'key_file' => env('BIGQUERY_KEY_FILE', ''),

    'dataset' => env('BIGQUERY_DATASET', ''),

    /*
     * The region the dataset lives in, for example "EU" or "asia-northeast1".
     * Queries against a dataset outside the US fail unless this is set.
     */
    'location' => env('BIGQUERY_LOCATION'),

    /*
     * Cancels any query the planner estimates will scan more than this many bytes,
     * before it is billed.
     */
    'maximum_bytes_billed' => env('BIGQUERY_MAXIMUM_BYTES_BILLED'),

    'job_timeout_ms' => env('BIGQUERY_JOB_TIMEOUT_MS'),

    /*
     * Labels attached to every job, useful for attributing BigQuery spend.
     */
    'labels' => [],
];
