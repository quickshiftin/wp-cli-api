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

        // Load the args if there are any
        $aArgs = array();
        if(isset($assoc_args['args']) && !empty($assoc_args['args']))
            $aArgs = $assoc_args['args'];

        //------------------------------------------------------------
        // Inspect the wp cli config to see if the user would like to 
        // load the remote site configuration from the local
        // wp-config.php file or wp cli's config.yml locally.
        //------------------------------------------------------------
        if(isset($assoc_args['use-local-config']) &&
            //------------------------------------------------------------
            // Load configuration from wp-config.php of the local install.
            //------------------------------------------------------------
            $assoc_args['use-local-config'] === true) {

            $sApiCreds = shell_exec(
                            'php ' . __DIR__ . '/read-wp-config.php ' .
                            escapeshellarg($sHost));
            $aApiCreds = unserialize($sApiCreds);
            if(!isset($aApiCreds['user']))
                WP_CLI::Error(
                    "Environment $sHost is missing a WP_CLI_API_USER constant");

            if(!isset($aApiCreds['pass']))
                WP_CLI::Error(
                    "Environment $sHost is missing a WP_CLI_API_PASS constant");

            if(!isset($aApiCreds['url']))
                WP_CLI::Error(
                    "Environment $sHost is missing a WP_CLI_API_URL constant");

            $this->_sApiUser = $aApiCreds['user'];
            $this->_sApiPass = $aApiCreds['pass'];
            $sHost           = $aApiCreds['url'];
        } else {
            //------------------------------------------------------------
            // Load configuration from config.yml using the provided host.
            //------------------------------------------------------------
            if(!isset($assoc_args[$sHost]))
                WP_CLI::error("Host $sHost is not defined in config.yml");

            // Load the config
            $api_config = $assoc_args[$sHost];

            // Bail if api user not defined
            if(!isset($api_config['user']))
                WP_CLI::error("Host $sHost is missing a user entry for the api in config.yml");

            // Bail if api pass not defined
            if(!isset($api_config['pass']))
                WP_CLI::error("Host $sHost is missing a pass entry for the api in config.yml");

            $this->_sApiUser = $api_config['user'];
            $this->_sApiPass = $api_config['pass'];
        }

        // Run the command on the remote box
        $aResults = $this->_runCommand($sHost, $sCommand, $sSubCommand, $aArgs);
        // Inspect the results for the correct format
        if(!isset($aResults['output']) || !isset($aResults['return_code']))
            WP_CLI::error('Unexpected results from ' . $sHost);

        // Display any output from the rmeote box
        WP_CLI::log($aResults['output']);

        // Bail w/ an error if the remote side bombed
        if($aResults['return_code'] != 0)
            exit(5);
	}

    /**
     * Run the command on a remote Wordpress site using the Wordpress API.
     * @note The wp-cli-api plugin must be installed on the target Wordpress site.
     */
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
        curl_setopt($ch, CURLOPT_URL, $sHost . '/xmlrpc.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        $results = curl_exec($ch);
        curl_close($ch);

        return xmlrpc_decode($results);
    }
}

WP_CLI::add_command('api', 'WP_CLI_API_Command');
