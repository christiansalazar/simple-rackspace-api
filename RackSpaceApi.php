<?php
require_once("IQueuePush.php");
/**
 * RackSpaceApi
 *  enable the conversation with a Rackspace Cloud API.
 * 
 * @uses IQueuePush
 * @author Cristian Salazar H. <christiansalazarh@gmail.com> @salazarchris74 
 * @license FreeBSD {@link http://www.freebsd.org/copyright/freebsd-license.html}
 */
class RackSpaceApi implements IQueuePush {
	public $service_url = "https://identity.api.rackspacecloud.com/v2.0/tokens";
	public $username = "";
	public $apikey = "";
	public $cloudQueueRegion = "ORD";
	public $cloudQueueUsePublicUrl = true;

	private $_authdata;
	private $_error;
	private $_verbose;

	//interface
	public function iqueuepush_doauth($verbose=false){
		printf(__METHOD__.", init. verbose={$verbose}\n");
		$this->_verbose = $verbose;
		if($this->_verbose) printf(__METHOD__.", called.\n");
		if(!$this->_authdata = $this->getAuthData($verbose))
			return false;
		return true;
	}

	public function test(){
		$ad = json_decode($this->_authdata);
		printf("current time is: %s\n",date("Y-m-d H:i:s t",time()));
		printf("token expires: %s, isExpired ? %s\n",
			$this->getTokenExpirationDate(), 
				$this->isTokenExpired() ? "EXPIRED" : "NOT_EXPIRED");

		printf("\nservices:\n%s\n",print_r($this->enumServices(),true));
		$cloudQueues = $this->getService("cloudQueues");
		print_r($cloudQueues);
		$regions = $this->enumRegions($cloudQueues);
		print_r($regions);
		$endpoint = $this->getServiceEndpoint($cloudQueues,"ORD");
		print_r($endpoint);
	}

	// interface
	public function iqueuepush_push($queuename, $payload, $uuid='autogen'){
		if('autogen' == $uuid) $uuid = $this->uuid();
		$cloudQueues = $this->getService("cloudQueues");
		$endpoint = $this->getServiceEndpoint(
			$cloudQueues,$this->cloudQueueRegion);
		$endpoint_url = ($this->cloudQueueUsePublicUrl) ? 
			$endpoint->publicURL : $endpoint->internalURL;
		$defaultTTL = 300;
		if(is_array($payload)){
			if($this->_verbose) printf(__METHOD__.", {$queuename}, push Array\n");
			$items = array();
			foreach($payload as $_item)
				$items[] = is_string($_item) ? 
					array($defaultTTL, $_item) : $_item;
			return 
			$this->pushMessageInQueue($uuid,$endpoint_url, $queuename, $items);
		}else{
			$items = array(array($defaultTTL, $payload));
			return 
			$this->pushMessageInQueue($uuid, $endpoint_url, $queuename, $items);
		}
	}

	//interface
	public function iqueuepush_read($queuename, $marker="",$uuid='autogen'){
		if('autogen' == $uuid) $uuid = $this->uuid();
		$cloudQueues = $this->getService("cloudQueues");
		$endpoint = $this->getServiceEndpoint(
			$cloudQueues,$this->cloudQueueRegion);
		$endpoint_url = ($this->cloudQueueUsePublicUrl) ? 
			$endpoint->publicURL : $endpoint->internalURL;
		return $this->readMessagesFromQueue(
			$uuid, $endpoint_url, $queuename, $marker);
	}

	//interface
	public function iqueuepush_delete($queuename, $ids){
		$uuid = $this->uuid();
		$cloudQueues = $this->getService("cloudQueues");
		$endpoint = $this->getServiceEndpoint(
			$cloudQueues,$this->cloudQueueRegion);
		$url = ($this->cloudQueueUsePublicUrl) ? 
			$endpoint->publicURL : $endpoint->internalURL;
		$_ids = ""; $c=""; foreach($ids as $id){ $_ids .= $c.$id;$c=","; }
		$token = $this->getAccessToken();
		$q_url = sprintf("%s/queues/%s/messages",$url,$queuename);
		$q_url .= "?ids={$_ids}";
		if($this->_verbose)printf("[DELETE][%s]\n",$q_url);
		$ch = curl_init($q_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-Auth-Token: {$token}",
			"Client-ID: {$uuid}",
		));
    	$response = curl_exec($ch);
    	$error = curl_error($ch);
    	$errno = curl_errno($ch);
    	curl_close($ch);
		return (0===$errno) ? $response : null;
	}

	//interface
	public function iqueuepush_getbody($msg){
		if(isset($msg->body))	
			return $msg->body;
		return "";
	}

	//interface
	public function iqueuepush_getid($msg){
		if(preg_match_all('/(.*)\/(.*)\/(.*)\/(.*)\/(.*)/',
			$msg->href,$match))
				return $match[5][0];
		return "";
	}

	private function readMessagesFromQueue($uuid, $url, $queuename, $marker=''){
		$token = $this->getAccessToken();
		$q_url = sprintf("%s/queues/%s/messages",$url,$queuename);
		if("" != $marker)
			$q_url .= "?marker={$marker}";
		if($this->_verbose)printf("[%s]\n",$q_url);
		$ch = curl_init($q_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-Auth-Token: {$token}",
			"Client-ID: {$uuid}",
		));
    	$response = curl_exec($ch);
    	$error = curl_error($ch);
    	$errno = curl_errno($ch);
    	curl_close($ch);
		return (0===$errno) ? $response : null;
	}
	
	private function pushMessageInQueue($uuid, $url, $queuename, $items){
		$token = $this->getAccessToken();
		$q_url = sprintf("%s/queues/%s/messages",$url,$queuename);
		$data = "[";$s="";
		foreach($items as $item){
			list($ttl, $message) = $item;
			$ttl = 1*$ttl;
			$data .= $s."{ \"ttl\" : {$ttl} , \"body\" : \"{$message}\" }";
			$s=",";
		}
		$data .= "]";
		if($this->_verbose) printf(__METHOD__
			.", {$queuename}, push data begins\n%s\npush data end\n",$data);
		if($this->_verbose)printf("push to: [%s]\n",$q_url);
		$ch = curl_init($q_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-Auth-Token: {$token}",
			"Client-ID: {$uuid}",
		));
    	$response = curl_exec($ch);
    	$error = curl_error($ch);
    	$errno = curl_errno($ch);
    	curl_close($ch);
		if($this->_verbose) printf(__METHOD__.", {$queuename}, curl response:\n%s\n",$response);
		return 0===$errno;
	}

	private function isTokenExpired(){
		if($dt = $this->getTokenExpirationDate()){
			return strtotime($dt) <= time();
		}else
		return true;
	}

	private function getTokenExpirationDate(){
		if($data = json_decode($this->_authdata)){
			return $data->access->token->expires;
		}else
		return null;
	}

	private function getAccessToken(){
		if($data = json_decode($this->_authdata)){
			return $data->access->token->id;
		}else
		return null;
	}

	private function getServiceEndpoint($service, $regionName){
		if($service)
			foreach($service->endpoints as $endpoint)
				if($endpoint->region == $regionName)
					return $endpoint;
		return null;
	}

	private function enumRegions($service){
		$regions=array();
		if($service)
			foreach($service->endpoints as $endpoint)
				$regions[] = $endpoint->region;
		return $regions;
	}

	private function getService($byname){
		if($data = json_decode($this->_authdata))
		foreach($data->access->serviceCatalog as $service)
			if($byname == $service->name)
				return $service;
		return null;
	}

	private function enumServices(){
		$servicenames = array();
		if($data = json_decode($this->_authdata))
		foreach($data->access->serviceCatalog as $service)
			$servicenames[] = $service->name;
		return $servicenames;
	}

	private function getAuthData($verbose=false,$no_cache=false){
		if($verbose) printf(__METHOD__." begins\n");
		$cache = sys_get_temp_dir()."/rackspace-auth.cache";
		if($verbose) printf(__METHOD__." cache file: %s\n",$cache);
		$cachehrs = 5;
		$authdata=null;
		if(!file_exists($cache) || $no_cache
			|| ((time() - filemtime($cache)) > 3600 * $cachehrs)){
			if($verbose) printf("cache invalid or expired, requestNewToken..\n");
			if($authdata = $this->requestNewToken($verbose)){
				$f = fopen($cache,"w");	fwrite($f, $authdata); fclose($f);
				if($verbose) printf("cache file is created\n");
			}else{
				if($verbose) printf(
					"cant obtain a new access token. last error was: %s\n",
					$this->_error);
			}
		}else{
			if($verbose) printf("reading authdata from cache file.\n");
			$f = fopen($cache,"r");
			$authdata = fgets($f); 
			fclose($f);
		}
		if($authdata){
			if($data = json_decode($authdata)){
				if(!isset($data->access)){
					@unlink($cache);
					die("please authenticate first by calling do_auth(). {$authdata}\n");
				}
				if(strtotime($data->access->token->expires) <= time()){
					//token expired
					if($verbose) printf("token is expired, requesting a new one..\n");
					if($authdata = $this->requestNewToken($verbose)){
						$f = fopen($cache,"w");	fwrite($f, $authdata); fclose($f);
						if($verbose) printf("cache file was updated\n");
					}else{
						if($verbose) printf(
							"cant obtain a new access token. last error was: %s\n",
							$this->_error);
					}
				}else
				if($verbose) printf("token is up to date\n");
			}
		}
		if($verbose) printf(__METHOD__." ends. authdata: %s\n",$authdata ? "[is ok]" : "[has errors]");
		return $authdata;
	}

	private function requestNewToken($verbose=false){
		if($verbose) printf(__METHOD__." begins\n");
		$username = $this->username;
		$apikey = $this->apikey;
		$data = "{
				\"auth\":
				{
					\"RAX-KSKEY:apiKeyCredentials\":
					{
						\"username\": \"{$username}\",
						\"apiKey\": \"{$apikey}\"
					}
				}
		}";
		if($verbose) printf("requestNewToken from: %s\n",$this->service_url);
		$ch = curl_init($this->service_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
		));
    	$response = curl_exec($ch);
    	$error = curl_error($ch);
    	$errno = curl_errno($ch);
    	curl_close($ch);
		if($verbose) printf("curl_error info is: %s,%s\n",$errno,$error);
		if($errno){
			$this->_error = $error.", ".$response;
			Yii::log(__METHOD__.", errno={$errno}, error=".$error,"error");
			if($verbose) printf(__METHOD__." ends. error.\n%s\n",$response);
			return null;
		}else{
			$this->_error = "success";
			if($verbose) printf(__METHOD__." ends. success.\n");
			return $response;
		}
	}

	public function uuid($namespace = '') {    
		$guid = '';
		$uid = uniqid("", true);
		$data = $namespace;
		$data .= date("Y-m-d H:i:s T",time());
		$data .= microtime(true);
		$data .= rand(1000,9999);
		$hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
		$guid = '{' .  
		substr($hash,  0,  8) .
		'-' .
		substr($hash,  8,  4) .
		'-' .
		substr($hash, 12,  4) .
		'-' .
		substr($hash, 16,  4) .
		'-' .
		substr($hash, 20, 12) .
		'}';
		return $guid;
	}
}
