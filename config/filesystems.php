<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | بثّ الوسائط عبر PHP
    |--------------------------------------------------------------------------
    |
    | خادم التطوير المدمج (`php artisan serve`) لا يدعم ترويسة Range، فلا يعمل
    | تحريك الفيديو (seek) ولا استئناف الموضع عند تبديل الجودة. عند التفعيل
    | تُبنى روابط الفيديو عبر مسار Laravel الذي يعيد 206 Partial Content.
    |
    | في الإنتاج خلف nginx/Apache اتركه false: الخادم يعالج Range بنفسه وبكفاءة
    | أعلى بكثير من تمرير الملف عبر PHP.
    |
    | تنبيه: عند تفعيله محلياً فعّل أيضاً PHP_CLI_SERVER_WORKERS في .env، وإلا
    | حجب بثّ الفيديو خادمَ التطوير أحادي الخيط عن باقي طلبات الـ API.
    |
    */

    'stream_media_via_php' => (bool) env('STREAM_MEDIA_VIA_PHP', false),

];
