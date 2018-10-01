# Webtime Munin Plugin

Plugin to graph HTTP response time of a specific page

*This is a **Virtual Node** plugin. This mean that it can run on any regular node (or on the Munin master), and each instance (symlink) can point to an external devices.
Here is a usefull link that explains [how to setup a virtual node](https://wiki.mikrotik.com/wiki/Munin_Monitoring	).*

### Installation
- Copy ```webtime_``` to the main Munin plugin directory, ie :```/usr/share/munin/plugins/```
- Then symlink it from the ```/etc/munin/plugins/``` folder, carefully naming it as described below
- Update the ```/etc/munin/munin.conf``` adding your node hostname between brackets, and set the address parameter to the IP of the node where webtime_ is installed. Example, if the node is on the same host as the server :

```
# hostname fetched by the node (must be the SAME as the HOSTNAME defined in the symlink name)
[www.apple.com]
      address 127.0.0.1
      # this the address of the NODE containing the symlink to webtime_
````

### Wilcard Naming
Link to this file with a symlink named as ```webtime_HOSTNAME_PAGENAME``` where: 
- **HOSTNAME** is the HostName to fetch.
- **PAGENAME** is the name of the page ('-' are replaced by spaces)

example: ```webtime_www.apple.com_HomePage```

### ENV Settings
You can add the following "env.xx" inside the ```[iot-json_HOSTNAME_FIRMWARE_TYPE]``` definitions in ```/etc/munin/plugin-conf.d/```
- **env.url**    : the relative URL  from the host (defaults to '/')
- **env.host**   : use a different hostname than the one defined from the symlink name
- **env.name**   : use a different pagename than the one defined from the symlink name
- **env.scheme** : scheme to use (defaults to 'http://')
- **env.agent**  : UserAgent (defaults to 'Mozilla/5.0 (Linux; Munin; http://www.github/soif/munin_plugins) webtime_/1.0')
