# exim_rbls

Graph count of emails rejected by each RBLs configured in Exim.

** (The included Perl shebang is directly compatible with latest Centos-Cpanel versions )**

## Installation
- Copy ```exim_rbls``` to the main Munin plugin directory, ie :```/usr/share/munin/plugins/```
- Then symlink it from the ```/etc/munin/plugins/``` folder


## ENV Settings
You can add the following "env.xx" inside the ```[exim_rbls]``` definitions in ```/etc/munin/plugin-conf.d/```
	- env.rbls    : (optional) list of RBL sites, separated by space, ie "www.barracudanetworks.com www.spamhaus.org www.spamcop.net"
	- env.exim    : (optional) path to exim bin
	- env.logdir  : (optional) path to exim log dir
	- env.logname : (optional) exim logfile name
