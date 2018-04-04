# iot-json_

Parse JSON information fron popular ESP8266 IT firmware like [**ESPeasy**](https://github.com/letscontrolit/ESPEasy/) or  [**Espurna**](https://github.com/xoseperez/espurna)
This is a **Virtual Node** plugin. This mean that it can run on any regular node (or on the Munin master), and each instance (symlink) can point to an external devices.
Here is a usefull link that explains [how to setup a virtual node](https://wiki.mikrotik.com/wiki/Munin_Monitoring	).

## Installation
- copy ```iot-json_``` to the main munin plugin directory, ie :```/usr/share/munin/plugins/```
- then symlink it from the ```/etc/munin/plugins/``` folder, carefully naming it as described below
- update the ```/etc/munin/munin.conf```adding your node hostanme or IP as the name (between brackets, and set the address parameter to the IP of the node where iot-json_ is installed. Example, if the node is on the same host as the server :

```
# address or name of the ESP device (must be the SAME as the name defined in the symlink name)
[192.168.0.1]
      address 127.0.0.1
      # this the address of the NODE containing the symlink to iot-json_
````

## Wilcard Naming
Link to this file with a symlink named as : ```iot-json_HOSTNAME_FIRMWARE_TYPE``` Where 
- **HOSTNAME** is the Host Name or IP address.
- **FIRMWARE** is either ```espeasy``` or ```espurna```
- **TYPE** is 
	Either one of the supported TYPES:
	- **free**		: Free RAM
	- **load**		: CPU Load
	- **rssi**		: Wifi strength
	- **uptime**	: Uptime in minutes
	either a custom TYPE, that will be the name of the JSON key to extract. 
	_ie if you want to extract a JSON field named "Temp", use "Temp" as TYPE, and optionally change the following env.xxx to better describe/draw it_

example: ```iot-json_192.168.0.1_espeasy_uptime```

## ENV Settings
You can add the following "env.xx" inside the ```[iot-json_HOSTNAME_FIRMWARE_TYPE]``` definitions in ```/etc/munin/plugins.d/*.conf```
- **url**			: use another API url  (ie for Espurna , be sure to end it with ?apikey=)
- **api_key**		: (espurna only) API Key
- **json_key**		: use a JSON key different than the custom TYPE set in the name (ie useful if there is a space in the key) 
- **graph_title**	: munin graph_title
- **graph_vlabel**	: munin graph_vlabel
- **graph_category**: munin graph_category
- **graph_scale**	: munin graph_scale
- **graph_args**	: munin graph_args
- **graph_info**	: munin graph_info
- **TYPE.label**	: munin line label (where TYPE is the TYPE used in the plugin name)
- **TYPE.info**		: munin line info (where TYPE is the TYPE used in the plugin name)
