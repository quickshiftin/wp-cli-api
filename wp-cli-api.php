<?php
/**
 * Invoke WP-CLI on another server via API from local machine
 *
 * @package  wp-cli
 * @author   Jonathan Bardo <jonathan.bardo@x-team.com>
 * @author   Weston Ruter <weston@x-team.com>
 */

/**
 * Copyright (c) 2013 X-Team (http://x-team.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Bail if not a WP-CLI request
 */
if ( ! defined( 'WP_CLI' ) ) {
        return;
}

/**
 * Implements api command.
 */
class WP_CLI_API_Command extends WP_CLI_Command {

	private $global_config_path, $project_config_path;

	/**
	 * Forward command to remote host
	 *
	 * ## OPTIONS
	 *
	 * <command>
	 * : The subcommand to run.
	 *
	 * --host=<host>
	 * : name of the host to connect to
	 *
	 * ## EXAMPLES
	 *
	 *     wp api plugin status --host=vagrant
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$runner = WP_CLI::get_runner();

		/**
		 * This script can either be supplied via a WP-CLI --require config, or
		 * it can be loaded via a Composer package.
		 * YES, the result is that this file is required twice. YES, this is hacky!
		 */
		$require_arg = sprintf( '--require=%s', __FILE__ );
		if ( empty( $runner ) ) {
			$GLOBALS['argv'][] = $require_arg;
			return;
		}

		// Parse cli args to push to server
		$has_url       = false;
		$path          = null;
		$target_server = null;
		$cli_args      = array();

		// @todo Better to use WP_CLI::get_configurator()->parse_args() here?
		foreach ( array_slice( $GLOBALS['argv'], 2 ) as $arg ) {
			// Remove what we added above the first time this file was loaded
			if ( $arg === $require_arg ) {
				continue;
			} else if ( preg_match( '#^--host=(.+)$#', $arg, $matches ) ) {
				$target_server = $matches[1];
			} else if ( preg_match( '#^--path=(.+)$#', $arg, $matches ) ) {
				$path = $matches[1];
			} else {
				if ( preg_match( '#^--url=#', $arg ) ) {
					$has_url = true;
				}
				$cli_args[] = $arg;
			}
		}

		// Remove duplicated api when there is a forgotten `alias wp="wp api --host=vagrant"`
		while ( ! empty( $cli_args ) && $cli_args[0] === 'api' ) {
			array_shift( $cli_args );
		}

		// Check if a target is specified or fallback on local if not.
		if ( ! isset( $assoc_args[$target_server] ) ){
			// Run local wp cli command
			$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : getenv( 'WP_CLI_PHP_USED' );
			$cmd = sprintf( '%s %s %s', $php_bin, $GLOBALS['argv'][0], implode( ' ', $cli_args ) );
			passthru( $cmd, $exit_code );
			exit( $exit_code );
		} else {
			$api_config = $assoc_args[$target_server];
		}

		// Add default url from config is one is not set
		if ( ! $has_url && ! empty( $api_config['url'] ) ) {
			$cli_args[] = '--url=' . $api_config['url'];
		}

		if ( ! $path && ! empty( $api_config['path'] ) ) {
			$path = $api_config['path'];
		} else {
			WP_CLI::error( 'No path is specified' );
		}

		// Inline bash script
		$cmd = '
			set -e;
			if command -v wp >/dev/null 2>&1; then
				wp_command=wp;
			else
				wp_command=/tmp/wp-cli.phar;
				if [ ! -e $wp_command ]; then
					curl -L https://github.com/wp-cli/builds/blob/gh-pages/phar/wp-cli.phar?raw=true > $wp_command;
					chmod +x $wp_command;
				fi;
			fi;
			cd %s;
			$wp_command
		';

		// Replace path
		$cmd = sprintf( $cmd, escapeshellarg( $path ) );

		// Remove newlines in Bash script added just for readability
		$cmd  = trim( preg_replace( '/\s+/', ' ', $cmd ) );

		// Append WP-CLI args to command
		$cmd .= ' ' . join( ' ', array_map( 'escapeshellarg', $cli_args ) );

		// Escape command argument for each level of API tunnel inception, and pass along TTY state
		$cmd_prefix   = $api_config['cmd'];

		// Replace placeholder with command
		$cmd = str_replace( '%cmd%', $cmd, $cmd_prefix );

        WP_CLI::info('cmd: ' . $cmd);

		// Execute WP-CLI on remote server
		// passthru( $cmd, $exit_code );

		// Prevent local machine's WP-CLI from executing further
		exit( $exit_code );
	}
}

WP_CLI::add_command( 'api', 'WP_CLI_API_Command' );
