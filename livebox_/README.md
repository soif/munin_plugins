# Livebox Munin Plugin

Parse JSON information fron Orange/Sosh Livebox ADSL Modem (works with latest firmwares & V4 hardware)

*This is a **Virtual Node** plugin. This mean that it can run on any regular node (or on the Munin master), and each instance (symlink) will point to the Livebox device.
Here is a usefull link that explains [how to setup a virtual node](https://wiki.mikrotik.com/wiki/Munin_Monitoring	).*

### Installation
- Copy ```livebox_``` to the main Munin plugin directory, ie :```/usr/share/munin/plugins/```
- Then symlink it from the ```/etc/munin/plugins/``` folder, carefully naming it as described below
- Update the ```/etc/munin/munin.conf``` adding your node hostname or IP between brackets, and set the address parameter to the IP of the node where livebox_ is installed. 

Example, if the node is on the same host as the server :
```
# address or name of the Livebox (must be the same as the host defined in the ENV)
[192.168.1.1]
      address 127.0.0.1
      # this the address of the NODE containing the symlink to livebox_
````

### Wilcard Naming
Link to this file with a symlink named as : ```livebox_PRESET``` where **PRESET** is the one of the followings:

- **rate_up**		: Upstream Rate
- **rate_down**		: Downstream Rate
- **errors_fec**	: FEC Errors
- **errors_hec**	: HEC Errors
- **errors_crc**	: CRC Errors
- **errors_err**	: "Errored" Errors
- **traffic**		: Up/Down Traffic
- **levels_down**	: DownStream Line Levels
- **levels_up** 	: UpStream Line Levels

example: ```livebox_rate_down```

You can also use **KEY_TYPE.KEY_NAME** instead of *PRESET*.
	ie: if you want to extract a JSON field named "Uptime", from the 'mib' JSON object, use "**livebox_mib.Uptime**"

### ENV Settings
You must add the following "env.xx" inside the [livebox_*] definitions in /etc/munin/plugins.d/*.conf

- **env.pass**		: (required)  the admin password
- **env.host**		: (optionnal) IP or hostname of the livebox (default to 192.168.1.1)
- **env.fqdn**		: (optionnal) the hostname used by munin in the html page (default to the 'host' value)
