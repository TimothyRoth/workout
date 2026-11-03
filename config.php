<?php

/**
 * VERSION is the short hash of the deployed git commit, used as a cache
 * breaker for static assets. It is read from .git directly so it changes
 * automatically with every commit / git pull, without a hook or build step.
 */
define('VERSION', \App\Version::current());
