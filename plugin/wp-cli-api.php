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
    $command    = $args['command'];
    $subcommand = $args['subcommand'];
    $cargs      = $args['args'];

    // Not a valid user name/password?  bail.
    $user = wp_authenticate($username, $password);
    if(!$user || is_wp_error($user))
        return false;

    // Build the command to execute; start with the wp command
    $command = __DIR__ . '/wp-wrapper.sh ' . escapeshellarg($command);

    // Append the sub command to the total command to execute,
    // if one has bee provided
    if(!empty($subcommand))
        $command .= ' ' .escapeshellarg($subcommand);

    // @note Since the arguments must be supplied as a string,
    //       the assumption is that they're already shell escaped
    if(!empty($subcommand))
        $command .= ' ' . $cargs;

    // TODO Add some filtering here against the db
    //      Being able to restrict commands on the
    //      server is nice from a security perspective...
    exec($command, $output, $return_code);

    return array(
        'command'     => $command,
        'output'      => $output,
        'return_code' => $return_code,
    );
}
