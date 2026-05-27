<?php

declare(strict_types=1);

use PhpDb\Adapter\Profiler\Profiler;
use PhpDb\Adapter\Profiler\ProfilerInterface;

return [
    'dependencies' => [
        'aliases' => [
            ProfilerInterface::class => Profiler::class,
        ],
        'invokables' => [
            Profiler::class => Profiler::class,
        ],
    ],
];