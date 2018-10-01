# Oscam Munin Plugins

Some plugins to graph many information from an Oscam server

*These are **Virtual Node** plugins. This mean that they can run on any regular node (or on the Munin master), and every instance (symlink) will point to the host defined in the master conf.*


### Installation
- Copy ```oscam_conf.php``` to the ```/etc/munin/``` and adjust your settings
- Copy all other ```oscam_*``` to the main Munin plugin directory, ie :```/usr/share/munin/plugins/```
- Then symlink needed oscam plugins from the ```/etc/munin/plugins/``` folder
- Update the ```/etc/munin/munin.conf```adding your hostname or IP, and set the address parameter to the IP of the node where oscam_* are installed. Example, if the nodes are on the same host as the server :

```
# IP or hostname of the Oscam server (must be the same as the OSCAM_SERVER set in oscam_conf.php)
[192.168.0.1]
      address 127.0.0.1
      # this the address of the NODE containing the symlink to oscam_*
````


### Wildcard Plugin
**oscam_peer_online_** is a wilcard plugin. The expected fomat is: ```oscam_peer_online_PEERNAME``` 
the following  replacement will occur in the PEERNAME:
- '**--**' will be replaced by '/'
- '**-**' will be replaced by '_'
- '**XX**' will be replaced by '-'
