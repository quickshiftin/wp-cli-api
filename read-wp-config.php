<?php
//------------------------------------------------------------
// When the wp-config has already been loaded using
// APPLICATION_ENV from the environment, we can't get the
// config for another environment. Soo, we have an isolated
// script where we'll hack the APPLICATION_ENV then echo back
// the results as a php serialized array.
//------------------------------------------------------------
putenv('APPLICATION_ENV=' . $argv[1]);

// Tell wp-config.php we only want to get the
// environment-specific configuration.
define('WP_CLI_LOAD_ONLY', true);

// Load wp-config.php, now that we've coerced the APPLICATION_ENV
include('./wp-config.php');

// Initialize the results
$a = array(
    'user' => null,
    'pass' => null,
    'url'  => null,
);

// Set the results to real data if present
if(defined('WP_CLI_API_USER'))
    $a['user'] = WP_CLI_API_USER;

if(defined('WP_CLI_API_PASS'))
    $a['pass'] = WP_CLI_API_PASS;

if(defined('WP_CLI_API_URL'))
    $a['url'] = WP_CLI_API_URL;

// echo the result as a serialized array
echo serialize($a);
