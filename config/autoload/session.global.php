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
        /**
         * How long (in seconds) the session cookie persists in the browser.
         * A value of 0 means a session cookie (deleted when the browser session
         * ends or the tab/window is closed). Setting this to match gc_maxlifetime
         * makes the cookie persistent so it survives browser/tab restarts within
         * the server-side session window.
         *
         * Default: 28800 (8 hours) — matches gc_maxlifetime.
         */
        'cookie_lifetime' => 28800,
    ],
];
