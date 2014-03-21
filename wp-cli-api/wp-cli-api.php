<?php
/*
Plugin Name: wp-cli-api
Description: Run wp command against this wordpress install over API.
Author: Nathan Nobbe
Author URI: http://moxune.com
License: Copyright Moxune LLC
*/

$GLOBALS['xmlrpc_internalencoding'] = 'UTF-8';

/**
 * Filters the XMLRPC method to include our own custom method
 */
function wp_api_xmlrpc_methods($methods)
{
    $methods['wp.cli'] = 'wp_cli_api_callback';
    return $methods;
}
add_filter('xmlrpc_methods', 'wp_api_xmlrpc_methods');

/**
 * Callback function for our custom XML RPC method. Stolen from the
 */
function wp_cli_api_callback($args)
{
    $username   = $args[0];
    $password   = $args[1];
    $command    = escapeshellarg($args[2]['command']);
    $subcommand = $args[2]['sub_command'];
    $cargs      = $args[2]['arguments'];

    // Not a valid user name/password?  bail.
    $user = wp_authenticate($username, $password);
    if(!$user || is_wp_error($user))
        return false;

    // Determine the path to the WP install for the command executor
    $webroot = __DIR__ . '/../../../';

    // Look for the wp executable on disk, bail if not found
    $wp_exec = exec('which wp', $care, $r);
    if($r > 0)
        return false;

    // Append the sub command to the total command to execute,
    // if one has bee provided
    if(!empty($subcommand))
        $command .= ' ' . escapeshellarg($subcommand);

    // @note Since the arguments must be supplied as a string,
    //       the assumption is that they're already shell escaped
    if(!empty($subcommand))
        $command .= ' ' . $cargs;

    // Prepend the command with the path to the executable
    $command = $wp_exec . ' ' . $command;

    //----------------------------------------------------
    // TODO Add some filtering here against the db
    //      Being able to restrict commands on the
    //      server is nice from a security perspective...
    //----------------------------------------------------

    // cd into the webroot for the site
    // (where we need to be for the wp command to have context)
    chdir($webroot);

    // Forward the value of APPLICATION_ENV when calling wp over the shell
    putenv('APPLICATION_ENV=' . APPLICATION_ENV);

    // Actually invoke the wp command
    // @TODO Only write STDERR to STDOUT if the client wants us to
    exec("$command 2>&1", $output, $return_code);

    // Return the results
    return array(
        'command'     => $command,
        'output'      => implode(PHP_EOL, $output),
        'return_code' => $return_code,
    );
}
