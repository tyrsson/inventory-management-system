#!/bin/bash
set -e

composer update --no-interaction --ignore-platform-req=php

exec php -S 0.0.0.0:8080 -t public/
