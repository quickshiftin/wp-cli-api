#!/bin/bash
#------------------------------------------------------------
# Look for the wp command. If it isn't present, download the
# phar to /tmp and execute wp from there.
#------------------------------------------------------------

# Bail if not enough arguments have been provided
if [ $# -lt 2 ]; then
    exit 1
fi

command="$1"
sub_command="$2"
shift
shift

wp=$(which wp >/dev/null 2>&1)
if [ $? -ne 0 ]; then
    wp=/tmp/wp-cli.phar;
    if [ ! -e $wp ]; then
        curl -L https://github.com/wp-cli/builds/blob/gh-pages/phar/wp-cli.phar?raw=true > $wp_command;
        chmod +x $wp;
    fi;
fi;
$wp "$command" "$sub_command" "$@"
