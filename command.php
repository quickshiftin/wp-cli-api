<?php
/**
 * Invoke WP-CLI on another server via API from local machine
 *
 * @package  wp-cli
 * @author   Nathan Nobbe <nathan@moxune.com>
 */

/**
 * Copyright (c) 2014 Moxune LLC (http://moxune.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 4 or, at
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
if(!defined('WP_CLI'))
        return;

/**
 * Implements api command.
 */
class WP_CLI_API_Command extends WP_CLI_Command
{
    private $_sApiUser;
    private $_sApiPass;

	/**
	 * Forward command to remote host
	 *
	 * ## OPTIONS
     *
	 * <host>
	 * : name of the host to connect to
	 *
	 * <command>
	 * : The command to run.
	 *
	 * [<subcommand>]
	 * : The sub command to run.
     *
	 * [--args=<commandargs>]
	 * : Arguments (Supply as a string)
     *
	 * ## EXAMPLES
	 *
	 *     wp api mysite.com plugin status --host=vagrant
     *     wp api mysite.com option add my_option
	 *
	 * @when before_wp_load
	 */
	public function __invoke($args, $assoc_args)
    {
        //-------------------------------------------------------------------
        // Bail right away if the xmlrpc encoder is missing
        //-------------------------------------------------------------------
        if(!function_exists('xmlrpc_encode_request'))
            WP_CLI::error('function xmlrpc_encode_request DNE');

        //-------------------------------------------------------------------
        // Load the arguments
        //-------------------------------------------------------------------
        $sHost    = $args[0];
        $sCommand = $args[1];

        $sSubCommand = '';
        if(isset($args[2]))
            $sSubCommand = $args[2];

        $aArgs = array();
        if(isset($assoc_args['args']) && !empty($assoc_args['args']))
            $aArgs = $assoc_args['args'];

        //------------------------------------------------------------
        // Load configuration from config.yml using the provided host.
        //------------------------------------------------------------
		if(!isset($assoc_args[$sHost])) {
            WP_CLI::error("Host $sHost is not defined in config.yml");
            exit(1);
		}

        // Load the config
        $api_config = $assoc_args[$sHost];

        // Bail if api user not defined
        if(!isset($api_config['user'])) {
            WP_CLI::error("Host $sHost is missing a user entry for the api in config.yml");
            exit(2);
        }

        // Bail if api pass not defined
        if(!isset($api_config['pass'])) {
            WP_CLI::error("Host $sHost is missing a pass entry for the api in config.yml");
            exit(3);
        }

        $this->_sApiUser = $api_config['user'];
        $this->_sApiPass = $api_config['pass'];

        // Run the command on the remote box
        $aResults = $this->_runCommand($sHost, $sCommand, $sSubCommand, $aArgs);
        // Inspect the results for the correct format
        if(!isset($aResults['output']) || !isset($aResults['return_code'])) {
            WP_CLI::error('Unexpected results from ' . $sHost);
            exit(4);
        }

        // Display any output from the rmeote box
        WP_CLI::log($aResults['output']);

        // Bail w/ an error if the remote side bombed
        if($aResults['return_code'] != 0)
            exit(5);
	}

    private function _runCommand(
        $sHost, $sCommand, $sSubCommand='', $sArgs=''
    ) {
        $content = array(
            'command'     => $sCommand,
            'sub_command' => $sSubCommand,
            'arguments'   => $sArgs,
        );

        $params  = array($this->_sApiUser, $this->_sApiPass, $content, true);
        $request = xmlrpc_encode_request('wp.cli', $params);
        $ch      = curl_init();

        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        // XXX Hardcoded protocol...
        //     Let's make this something we can set in the config on a per-host basis..
        curl_setopt($ch, CURLOPT_URL, 'http://' . $sHost . '/xmlrpc.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

        $results = curl_exec($ch);
        curl_close($ch);

        return xmlrpc_decode($results);
    }
}

WP_CLI::add_command('api', 'WP_CLI_API_Command');
