<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

/*
	Copyright: Soif, https://github.com/soif/
*/

# OSCam class **********************************************
class OSCam {
	var $server =OSCAM_SERVER;
	var $port	=OSCAM_PORT;
	var $login	=OSCAM_LOGIN;
	var $pass	=OSCAM_PASS;

	var $last_url	='';
	var $last_stats	='';
	var $last_xml	='';
	var $last_xml_cached	='';

	var $cache			=array();
	var $use_ram_cache	=true;
	var $use_file_cache	=true;
	var $time_cache		=60;
	var $path_cache		=''; //NO trailing slash
	
	var $peers_to_filter	=array('dreambox','jupcccam');


	// --------------------------------------------------------
	function OSCam(){
		if($this->use_file_cache and !$this->path_cache){
			$this->path_cache='/tmp/munin_oscam';
			if(!file_exists($this->path_cache)){
				mkdir($this->path_cache);
				@chmod($this->path_cache,0777);
			}
		}
	}

	// --------------------------------------------------------
	function debug($show_html=0){
		if($show_html) echo "<pre>\n";
		echo "URL: ".$this->last_url."\n";
		if($show_html){
			echo "XML: \n";
			echo htmlspecialchars($this->last_xml);
			echo "\nXML (cache): \n";
			echo htmlspecialchars($this->last_xml_cached);
		}
		else{
			echo "XML: \n";
			echo $this->last_xml;
			echo "\nXML (cache): \n";
			echo $this->last_xml_cached;
		}
		echo "\n\nStats: "; print_r($this->last_stats);
		echo "\n";
		if($show_html) echo "</pre>\n";
	}



	// ---------------------------------------------------------
	function muninGetMulti($base_name){
		global $argv;
		$out=preg_replace("#^".$base_name."#",'',basename($argv[0]));
		//munin multi accept only A-Za-z0-9-'
		$out=str_replace('--','/',$out);
		$out=str_replace('-','_',$out);
		$out=str_replace('XX','-',$out);
		return $out;
	}

	
	// ---------------------------------------------------------
	function muninPlugin($preset,$user=''){
		list($type,$mode)=explode('-',$preset);


		$def['online']['vtitle']	="Minutes"; //jours = 86400
		$def['online']['div']		=60;
		$def['online']['max']		=3600;

		$def['idle']['vtitle']		="Secondes";

		$def['ecmtime']['vtitle']	="Millisecondes";

		$def['count']['vtitle']		="Counts";
		
		$p['cat']		=ucfirst($type);
		$p['title']		='OSC '.ucfirst($type). ' ' .ucfirst($mode);
		$p['max']		=$def[$mode]['max'];
		$p['vtitle']	=$def[$mode]['vtitle'];
		$p['div']		=$def[$mode]['div']			or $p['div']		=1;
		$p['draw_all']	=$def[$mode]['draw_all']	or $p['draw_all']	='LINE1';
		$p['args']		=$def[$mode]['args']		or $p['args']		='--base 1000 -l 0';

		if		($type=='servers' and $mode)		{
			$p['values']=$this->getServersStatusValues($mode,1);
		}
		elseif	($type=='clients' and $mode)	{
			if($mode=='ecmtime'){$filter=0;}else{$filter=1;}
			$p['values']=$this->getClientsStatusValues($mode,$filter);
		}
		elseif	($preset=='peers-connected')	{
			$p['values']['Servers']=$this->_getStatusCounts('p');
			$p['values']['Clients']=$this->_getStatusCounts('c');
			$p['title']	='1) ALL Online';
		}
		elseif	($type=='peers' and $mode and $user){
			$p['values']=$this->getPeerStatusValues($user,$mode);
			$p['title']	="$user : ".ucfirst($mode);
			$p['draw']['server']="AREA";
			$p['draw']['client']="LINE2";
		}
		else{
			exit;
		}
		$this->muninBuild($p);
	}
	
	// ---------------------------------------------------------
	function muninBuild($p){
		global $argv;
		//$p['div'] or $p['div']=1;
		
		if($argv[1]=='autoconf'){
			echo "yes\n";
			exit;
		}
		

		if($argv[1]=='config'){
			echo "host_name {$this->server}\n";
			if($p['cat'])		echo "graph_category OSCAM : {$p['cat']}\n";
			if($p['title'])		echo "graph_title {$p['title']}\n";
			if($p['vtitle'])	echo "graph_vlabel {$p['vtitle']}\n";
			if($p['args'])		echo "graph_args {$p['args']}\n";

			$munin_names	=array_map(array($this,'_cleanMuninName'),array_keys($p['values']));
			if( is_array($munin_names) and count($munin_names) ){
				echo "graph_order ".implode(' ',$munin_names)."\n";
			}

			foreach($p['values'] as $name => $v){
				$munin_name=$this->_cleanMuninName($name);
				echo "$munin_name.label $name\n";
				if($p['draw'][$name]){
					echo "$munin_name.draw {$p['draw'][$name]}\n";
				}
				elseif($p['draw_all']){
					echo "$munin_name.draw {$p['draw_all']}\n";					
				}
			}
			exit;
		}

		if(!$p['values']){
			exit;
		}
		
		foreach($p['values'] as $name => $v){
			$munin_name	=$this->_cleanMuninName($name);
			$v			=intval($v);
			if($p['max'] and $n=floor($v / $p['max'])){
				$v=$v - ($n * $p['max'] );
				if ($v < 10){$v=10;}
			}
			echo "$munin_name.value ". ($v / $p['div']) ."\n";
		}
		exit;
	}




	// ---------------------------------------------------------
	function getPeerStatusValues($name,$type){
		$servers=$this->getServersStatusValues($type);
		$clients=$this->getClientsStatusValues($type);
		$out=array();
		foreach($servers as $s_name => $value){
			//$s_name=strtolower($s_name);
			//$name=strtolower($name);
			//echo "$s_name / $name\n";
			if($s_name==$name){
				$out['server']=$value;
				$out['client']=$clients[$s_name];
			}
		}
		return $out;
	}



	// ---------------------------------------------------------
	function getServersStatusValues($children,$filter=0){
		$out=array();
		$parent =$this->_getStatusParent($children);
		if($items=$this->getServersStatus()){
			foreach($items as $item){
				//print_r ($item);
				$value=intval($item[$parent][$children]);
				if($item['connection']['value']=='ERROR'){
					$value=0;
				}
				$out[$item['name']]=$value;
			}
		}
		ksort($out);
		return $out;
	}

	// ---------------------------------------------------------
	function getClientsStatusValues($children,$filter=0){
		$out=array();
		$parent =$this->_getStatusParent($children);
		if($items=$this->getClientsStatus()){
			foreach($items as $item){
				if($filter and in_array($item['name'],$this->peers_to_filter)){continue;}
				$value=$item[$parent][$children];
				$value or $value=0;
				$out[$item['name']]=$value;
			}
		}
		ksort($out);
		return $out;
	}

	// ---------------------------------------------------------
	function getClientsCount(){
		return $this->_getStatusCounts('c');
	}
	// ---------------------------------------------------------
	function getServersCount($type="CONNECTED"){
		return $this->_getStatusCounts('p',$type);
	}

	// ---------------------------------------------------------
	function getServersStatus(){
		return $this->_getStatus('p');
	}
	// ---------------------------------------------------------
	function getClientsStatus(){
		return $this->_getStatus('c');
	}

	// ---------------------------------------------------------
	function getReaderStats($user,$mode){ //count, totalecm
		$stats=$this->_getReaderEcmsStats($user);
		return $stats[$mode];
	}	
	
	// ##########################################################

	// ---------------------------------------------------------
	function ToolImportProvid($path,$do_save=1){
		global $c440;
		if($lines=file($path)){
			$c440->odb->QueryRaw('SET NAMES UTF8');
			$out = "Processing Provid\n";
			foreach ($lines as $line){
				$line=trim($line);
				if(!preg_match('#^[A-Za-z\d]+#',$line)){continue;}
				$l++;
				//0D0A:00000C | Halozat TV (4W)
				list($caid_provid,$name)=explode('|',$line);
				list($caid,$provid)=explode(':',trim($caid_provid));
				$caid	=trim($caid);
				$provid	=trim($provid);
				$name	=trim($name);
				$orb	='';
				if(preg_match('#\((\d+[\.\dA-Z]+[^)]*)\)$#i',$name,$m)){
					$orb=$m[1];
				}
				if($do_save and $caid and $provid and $name){
					if(!$c440->odb->Query("SELECT * FROM oscam_provid WHERE prov_caid='$caid' and prov_provid='$provid' ")){
						$c440->odb->Insert("INSERT INTO oscam_provid SET prov_caid='$caid', prov_provid='$provid', prov_name='$name', prov_orb='$orb'");
						$i++;
					}
				}
				//$out .="$caid $provid '$name' $orb\n";
			}
			$out .="Found $l lines, inserted $i providers";
		}
		return $out;
		
	}

	// ---------------------------------------------------------
	function ToolGetMenuServers($url){
		$servers=$this->getServersStatus();
		foreach($servers as $s){
			$out[$s['name']]=$url.$s['name'];
		}
		return (array) $out;
	}

	// ---------------------------------------------------------
	function ToolReset(){
		global $c440;
		$c440->odb->QueryRaw('TRUNCATE oscam_ecms');
		$c440->odb->QueryRaw('TRUNCATE oscam_srvid');
		$c440->odb->QueryRaw('TRUNCATE oscam_channels');
		return "Reset oscam_ecms, oscam_srvid, oscam_channels";
	}	

	// ---------------------------------------------------------
	function ToolEcmsViewFromApi($user,$max=1000){
		$ecms =$this->_getReaderEcms($user);
		//echo "<pre>"; print_r($ecms); echo "</pre>"; exit;
		$x=count($ecms);

		foreach($ecms as $e){
			if($max){$i++; if($i > $max){break;}}

			$e['channelname']=htmlentities(utf8_decode($e['channelname']));
			$class='';if($e['rcs'] !='found'){$class .='not_ok ';}
			$OUT .="
		<tr clas='$class'>
			<td>{$e['value']}</td>
			<td>{$e['caid']}</td>
			<td>{$e['provid']}</td>
			<td>{$e['srvid']}</td>
			<td>{$e['channelname']}</td>
			<td>{$e['avgtime']}</td>
			<td>{$e['lasttime']}</td>
			<td>{$e['rc']}</td>
			<td>{$e['rcs']}</td>
			<td>{$e['lastrequest']}</td>
		</tr>
		";
		}
		
		$OUT =<<<EOF
<a href='?do=ecms_import&u=$user' class='osc440navMenu_a'>Import $user</a> ($x ecms)
<table cellspacing=0 class='osc440tableEcms'>
	<tr>
		<th>value</th>
		<th>caid</th>
		<th>provid</th>
		<th>srvid</th>
		<th>channelname</th>
		<th>avg</th>
		<th>last</th>
		<th>rc</th>
		<th>rcs</th>
		<th>lastr</th>
	</tr>
$OUT
	</table>
EOF;
		return $OUT;
	}


	// ----------------------------------------------------------
	function ToolListProviders(){
			return $this->_QueryToTable("SELECT 
			prov_caid 	as CaId, 
			prov_provid as ProvId, 
			prov_name 	as Provider, 
			srv_srvid 	as SrvId, 
			ch_name 	as Channel, 
			prov_orb 	as Orb,
			COUNT(ecm_id) as Req,
			SUM(ecm_count) as OK
			FROM oscam_provid p , oscam_srvid s, oscam_channels c, oscam_ecms
			WHERE 
				prov_caId 	= srv_caid AND 
				prov_provid = srv_provid AND 
				prov_caId 	= ecm_caid AND 
				prov_provid = ecm_provid AND 
				srv_srvid 	= ecm_srvid AND 
				srv_chid 	= ch_id
				
			GROUP BY srv_caid, prov_provid, srv_srvid
			ORDER BY ch_name	
			",
			'?do=view');			
	}


	// ----------------------------------------------------------
	function _QueryToTable($query,$url=''){
		global $c440;
		if($sql=$c440->odb->Query($query)){
			$html .="<table cellspacing=0 class='osc440tableEcms'>\n";
			while($row=mysql_fetch_assoc($sql)){
				$i++;
				$line ="";
				foreach($row as $k => $v){
					if(!$header_done){
						$header .="<th>$k</th>";
					}
					$vv=substr($v,0,30);
					if (strlen($vv) < strlen($v)){$vv="<a href='#' title='$v'>$vv</a>";}
					$line .="<td>$vv</td>";
				}
				if(!$header_done){
					$html .="<tr>$header</tr>\n";
				}
				$html.="<tr>$line</tr>\n";
				$header_done=1;
			}
			$html .="</table>\n";
		}
		$html="<div>$i Found</div><br>$html";
		return $html;	
	}


	// ----------------------------------------------------------
	function ToolEcmsImport($user,$max=0,$do_reset=1){
		
		global $c440;
		$c440->odb->QueryRaw('SET NAMES UTF8');
		
		$ecms =$this->_getReaderEcms($user);
		if( $do_reset){
			$c440->odb->Delete("DELETE FROM oscam_ecms WHERE ecm_user='$user' ");
		}

		foreach($ecms as $ecm){
			if($max){$x++; if($x > $max){break;}}
			$e++;
			$save=array();
			$save['ecm_user']		=$user;
			$save['ecm_count']		=$ecm['value'];
			$save['ecm_caid']		=$ecm['caid'];
			$save['ecm_provid']		=$ecm['provid'];
			$save['ecm_srvid']		=$ecm['srvid'];
			$save['ecm_name']		=$ecm['channelname'];
			$save['ecm_avgtime']	=$ecm['avgtime'];
			$save['ecm_lasttime']	=$ecm['lasttime'];
			$save['ecm_rc']			=$ecm['rc'];
			$save['ecm_rcs']		=$ecm['rcs'];
			$save['ecm_last']		=$c440->ofunc->UnixTimeFormated(strtotime($ecm['lastrequest']),'sqldatetime');
			
			//if ecm exist
			if(!$do_reset and $sql=$c440->odb->Query("SELECT ecm_id FROM oscam_ecms WHERE ecm_user='{$save['ecm_user']}' AND ecm_caid='{$save['ecm_caid']}' AND ecm_provid='{$save['ecm_provid']}' AND ecm_srvid='{$save['ecm_srvid']}' AND ecm_rcs='{$save['ecm_rcs']}' ")){
				$ecm_dup++;
				$out .=implode('	',$save)."\n";
				
				$row=mysql_fetch_array($sql);
				if($c440->odb->UpdateArray('oscam_ecms',$save," WHERE ecm_id={$row['ecm_id']}")){
					$ecm_upd++;
				
					//someting as changed, so Update the channel and srvid for name changes
					if($sql=$c440->odb->Query("SELECT srv_id, srv_chid, srv_name FROM oscam_srvid WHERE srv_caid='{$save['ecm_caid']}' AND srv_provid='{$save['ecm_provid']}' AND srv_srvid='{$save['ecm_srvid']}' ")){
						$row=mysql_fetch_array($sql);
						if($save['ecm_name'] != $row['srv_name']){
							$c440->odb->Update("UPDATE oscam_srvid 		SET srv_name=\"{$save['ecm_name']}\" WHERE srv_id={$row['srv_id']} ");
							$c440->odb->Update("UPDATE oscam_channels 	SET ch_name=\"{$save['ecm_name']}\" WHERE ch_id={$row['srv_chid']} ");
							$ch_upd++;
						}
						
					}
				}
			}
			else{
				//ecm dont exist, so create
				if($id=$c440->odb->InsertArray('oscam_ecms',$save)){
					$ecm_ins++;
					// srv not exist?
					if(!$c440->odb->Query("SELECT srv_chid FROM oscam_srvid WHERE srv_caid='{$save['ecm_caid']}' AND srv_provid='{$save['ecm_provid']}' AND srv_srvid='{$save['ecm_srvid']}' ")){

						//channel exists
						if($sql=$c440->odb->Query("SELECT ch_id FROM oscam_channels WHERE ch_name=\"{$save['ecm_name']}\" ")){
							$row=mysql_fetch_array($sql);
							$chid=$row['ch_id'];
							$ch_dup++;
						}
						else{
							$chid=$c440->odb->Insert("INSERT INTO oscam_channels SET ch_name=\"{$save['ecm_name']}\" ");
							$ch_ins++;							
						}

						// insert srv
						if($chid){
							$c440->odb->Insert("INSERT INTO oscam_srvid SET srv_caid='{$save['ecm_caid']}', srv_provid='{$save['ecm_provid']}', srv_srvid='{$save['ecm_srvid']}', srv_chid='{$chid}' , srv_name=\"{$save['ecm_name']}\" ");
							$serv_ins++;
						}		
					}
				}				
			}
				


		}
		$out .="<hr><b>$user</b>:
ECM:	 Read: $e,	Ins: $ecm_ins,	Dup: $ecm_dup,	Upd: $ecm_upd,
Channels: 		Ins: $ch_ins,	Dup: $ch_dup,	Upd: $ch_upd,
Servid:			Ins: $serv_ins";
		return $out;	
	}



	// ##########################################################
	function _cleanMuninName($txt){
		$txt = preg_replace('/^[^A-Za-z_]/', '_', $txt);
		$txt = preg_replace('/[^A-Za-z0-9_]/', '_', $txt);
		return $txt;
	}

	// ---------------------------------------------------------
	function _getStatusParent($children){
		if		($children=="online")		{return "times";}
		elseif	($children=="idle")			{return "times";}
		elseif	($children=="caid")			{return "request";}
		elseif	($children=="srvid")		{return "request";}
		elseif	($children=="ecmtime")		{return "request";}
		elseif	($children=="ecmhistory")	{return "request";}
		elseif	($children=="answered")		{return "request";}
	}




	// --------------------------------------------------------
	function _getReaderEcms($user) {
		$this->time_cache=600;
		if($x	=$this->_getXmlParsed('readerstats&label='.urlencode($user))){
			foreach($x->reader->ecmstats->ecm as $c) {
					$i++;
					$out[$i]	=$this->_simpleXMLToArray($c,null,null,'value');
			}
			return $out;
		}
	}

	// --------------------------------------------------------
	function _getReaderEcmsStats($user) {
		$this->time_cache=600;
		if($x	=$this->_getXmlParsed('readerstats&label='.urlencode($user))){
			//$out=$this->_simpleXMLToArray($x->reader->ecmstats,null,null,'value');
			$out=(array) $x->reader->ecmstats->attributes();
			return $out['@attributes'];
		}
	}


	// --------------------------------------------------------
	// type=	p:servers, c:client , r:readers (h:server, h:http) 
	function _getStatus($type='c') {
		if($x	=$this->_getXmlParsed('status')){
			foreach($x->status->client as $c) {
				if(!empty($c['type']) && $c['type'] == $type){
					$i++;
					$out[$i]	=$this->_simpleXMLToArray($c,null,null,'value');
				}
			}
			return $out;
		}
	}


	// --------------------------------------------------------
	// type		= p:servers, c:client , r:readers (h:server, h:http) 
	// status	= ERROR / CONNECTED / BOTH
	function _getStatusCounts($type='c',$status='CONNECTED') {
		if( $x=$this->_getXmlParsed('status') ){
			
			$r=$r_c=$r_e=0;
			foreach($x->status->client as $c) {
				if(!empty($c['type']) && $c['type'] == $type){
					if($type=='p'){
						if($status=='BOTH'){
							if($c->connection == "CONNECTED"){
								$r_c++;
							}
							if($c->connection == "ERROR"){
								$r_e++;
							}
							$r++;
						}
						elseif($c->connection == $status){
							$r++;
						}
					}
					else{
						$r++;
					}
				}
			}
			if($status=='BOTH'){
				return "$r_c/$r";
			}
			return $r;
		}
	}


	// --------------------------------------------------------
	function _getXmlParsed($part){
		if($xml=$this->_getXml($part)){
			return @new SimpleXMLElement($xml);
		}
		return FALSE;
	}

	// --------------------------------------------------------
	function _getXml($part) {
		$url='http://'.$this->server.":".$this->port.'/oscamapi.html?part='.$part;
		$this->last_url		=$url;
		
		if($this->use_file_cache){
			$cache_file=$this->path_cache.'/'.$this->server.'_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $part);
			if($time=@filemtime($cache_file) and (time() - $time) < $this->time_cache ){
				$this->cache[$part]=json_decode(file_get_contents($cache_file));
			}
			$this->last_xml_cached	=$this->cache[$part];
		}
		
		if(!$this->cache[$part] or !$this->use_ram_cache){
			$this->cache[$part]=$this->_getUrl($url);
			if($this->use_file_cache){
				file_put_contents($cache_file,json_encode($this->cache[$part]));
				@chmod($cache_file,0777);
			}
		}
		return $this->cache[$part];
	}

	// --------------------------------------------------------
	function _getUrl($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
		//curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true); 
		if($this->login){
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); //CURLAUTH_BASIC CURLAUTH_ANY
			curl_setopt($ch, CURLOPT_USERPWD, "{$this->login}:{$this->pass}");
		}
		$output				= curl_exec($ch);
		$this->last_stats 	= curl_getinfo($ch);
		curl_close($ch);
		$this->last_xml		=$output;
		return $output;
	}

	// --------------------------------------------------------
	//http://www.php.net/manual/en/book.simplexml.php#105697
	function _simpleXMLToArray(SimpleXMLElement $xml,$attributesKey=null,$childrenKey=null,$valueKey=null){ 
	
		if($childrenKey && !is_string($childrenKey)){$childrenKey = '@children';} 
		if($attributesKey && !is_string($attributesKey)){$attributesKey = '@attributes';} 
		if($valueKey && !is_string($valueKey)){$valueKey = '@values';} 
	
		$return = array(); 
		$name = $xml->getName(); 
		$_value = trim((string)$xml); 
		if(!strlen($_value)){$_value = null;}; 
	
		if($_value!==null){ 
			if($valueKey){$return[$valueKey] = $_value;} 
			else{$return = $_value;} 
		} 
	
		$children = array(); 
		$first = true; 
		foreach($xml->children() as $elementName => $child){ 
			$value = $this->_simpleXMLToArray($child,$attributesKey, $childrenKey,$valueKey); 
			if(isset($children[$elementName])){ 
				if(is_array($children[$elementName])){ 
					if($first){ 
						$temp = $children[$elementName]; 
						unset($children[$elementName]); 
						$children[$elementName][] = $temp; 
						$first=false; 
					} 
					$children[$elementName][] = $value; 
				}else{ 
					$children[$elementName] = array($children[$elementName],$value); 
				} 
			} 
			else{ 
				$children[$elementName] = $value; 
			} 
		} 
		if($children){ 
			if($childrenKey){$return[$childrenKey] = $children;} 
			else{$return = array_merge($return,$children);} 
		} 
	
		$attributes = array(); 
		foreach($xml->attributes() as $name=>$value){ 
			$attributes[$name] = trim($value); 
		} 
		if($attributes){ 
			if($attributesKey){$return[$attributesKey] = $attributes;} 
			else{
				if(!is_array($return)){
					$return=array();
				}
				$return = array_merge($return, $attributes);
			} 
		} 
	
		return $return; 
	}

}
?>
