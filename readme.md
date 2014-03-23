# WP-CLI API
*Seamlessly run WP-CLI commands on a remote server via API*

WP-CLI is an extremely useful tool for managing WordPress installs, but it requires 
you to invoke it on the same server where WordPress is installed. This means you have
to ssh into the server, navigate to the install, and then run the command. It would
be much more convenient if you could remain on your local shell and invoke WP-CLI 
remotely. 

WP-CLI API operates against your WordPress' API. Install the wp-cli-api plugin on the WordPress site you want to control, and install the `api`  and/or `yapi` comamnds in WP-CLI on the computer you want to control remote sites with.

## config.yml-based configuration and the `yapi` command

Assuming you have a clone of your WordPress install on your machine, add an `api` section
to your `wp-cli.yml` config file. In this section you define the remote servers which 
host the other environments for your site, e.g. development, staging, production.
Then you just invoke WP-CLI normally, but supply a host argument to the api command
host you want to connect to http://livesite.com:

```bash
wp yapi production plugin status
```
The command and sub-command can be specified with positional parameters, but anything past that has to be passed as a string inside the `--args` option.
```bash
wp yapi production option get --args='siteurl'
```
One advantage of the `config.yml` approach is it's independent of WordPress, so you can excute `wp yapi` from any working directory.
```yaml
api:
  production:
    user: api-user
    pass: api-pass
    url: 'http://livesite.com'

  staging:
    user: api-user
    pass: api-pass
    url: 'http://staging.livesite.com'

  deelopment:
    user: api-user
    pass: api-pass
    url: 'http://livesite.local'
```
## wp-config.php-based configuration and the `api` command

You can alternatively configure the command with `wp-config.php` from WordPress. To do this, you must run the `wp api` command from the WordPress installation directory. You must also install the [wpConfigure plugin](http://quickshiftin.com/software/wp-configure) first. Then your `wp-config.php` file might look something like this
**wp-config.php**
```php
require_once __DIR__ . '/wpCofigure.php';
wpConfigure('site, array(
    'production' => array(
        'WP_CLI_API_USER' => 'api-user',
        'WP_CLI_API_PASS' => 'api-pass',
        'WP_CLI_API_URL'  => 'http://livesite.com',
    ),  

    'staging:production' => array(
        'WP_CLI_API_URL' => 'http://staging.livesite.com',
    ),

    //------------------------------------------------------------
    // development configuration loaded from wp-config-local.php
    // Copy from wp-config-local-example.php to get
    // started.
    //------------------------------------------------------------
));
```
**wp-config-local.php**
return array(
    'development:staging' => array(
        'WP_CLI_API_URL' => 'http://livesite.local'
));

Remember, you need to be in the webroot of your WordPress install for this command to work.
```bash
wp api staging option get --args='siteurl'
```



## Installation

Note that you do not necessarily need WP-CLI installed on your server to use this. If the `wp` command is not
recognized on the server, the script will download the `wp-cli.phar` file and use that at runtime.

Installing WP-CLI-API on your machine can be done either by installation as a Composer package,
or by adding a `require` config to a `wp-cli.local.yml`.

## Alternatives

 * [vaapi](https://github.com/x-team/vaapi)
 * [`wp` Bash function](https://github.com/humanmade/Salty-WordPress/issues/16)
 * [WP Remote CLI](https://github.com/humanmade/wp-remote-cli/)

