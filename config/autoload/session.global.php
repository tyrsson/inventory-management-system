<?php

declare(strict_types=1);

return [
    'session' => [
        /**
         * How long (in seconds) the DB session record remains valid.
         * Also controls the session cookie lifetime when no per-session
         * override is set via SessionCookiePersistenceInterface.
         *
         * Default: 28800 (8 hours).
         * Override in a *.local.php file for environment-specific values.
         */
        'gc_maxlifetime' => 28800,
    ],
];
