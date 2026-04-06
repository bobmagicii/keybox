# Keybox

Manage a pile of SSH keys in a portable box. Also manage their permissions so SSH doesn't yell at you about invalid permissions since it won't tell you what the permissions need to be.

## Importing Keys

Import keys from somewhere to the locally managed directory. If no --path is specified then it scans `~/.ssh` by default. It will ask you to name each pair you wish to import.

> php keybox.phar import --path=...

Show what keys have been imported.

> php keybox.phar keys

## Set Keys for Hosts

Choose a key for specified host, in this case, Github. It will then present a list asking you to choose a key.

> php keybox.phar set github.com

Unset a host key association.

> php keybox.phar unset github.com

Show what hosts are using which keys.

> php keybox.phar hosts

## Actually Using This

Once keys have been imported and set to some hosts there will be a `keys/hosts.conf` file containing all the SSH configuration needed.

The main way to use this would be to edit `~/.ssh/config` to add the following to the end of that file:

`Include /path/to/keys/hosts.conf`

From then on you can use Keybox to manage the host configuration without having to hand edit the SSH config file.

