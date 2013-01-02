<?php

class XMLHttpRequest
{
	private $curl;
	private $responseHeaders;
	private $headers;
	private $properties = array();
	public function __set($property, $value) {
		switch (strtolower($property)) {
			case "maxredirects":
				if(is_int($value)) {
					$this->properties["maxredirects"] = $value;
				}else{
					throw new Exception("cannot implicitly convert type in the property \"$property\" to int");
				}
				break;
			case "curl":
			case "error":
			case "readystate":
			case "responsetext":
			case "responsexml":
			case "status":
			case "statustext":
				throw new Exception("property \"$property\" cannot be assigned to -- it is read only");
				break;
			default:
				throw new Exception("class \"".__CLASS__."\" does not contain a definition for \"$property\"");
		}
	}
	
  public  function __get($property) {
		switch (strtolower($property)){
			case "curl":
				return $this->curl;
			case "error":
				return curl_error($this->curl);
			case "maxredirects":
			case "readystate":
			case "responsetext":
			case "status":
			case "statustext":
				$property = strtolower($property);
				if(isset($this->properties[$property])){
					return $this->properties[$property];
				}else return null;
			case "responsexml":
				if(!isset($this->properties["responsexml"])){
					if(isset($this->properties["responsetext"]) && !empty($this->properties["responsetext"])){
						$xml = DOMDocument::loadXML($this->properties["responsetext"], LIBXML_ERR_NONE | LIBXML_NOERROR);
						if($xml){
							$this->properties["responsexml"] = $xml;
							return $xml;
						}
					}
				}else{
					return $this->properties["responsexml"];
				}
				return null;
			default:
				throw new Exception("class \"".__CLASS__."\" does not contain a definition for \"$property\"");
		}
  }
	
	public function __construct(){
		if(function_exists("curl_init")){
			$this->curl = curl_init();
			if(isset($_SERVER['HTTP_USER_AGENT'])){
				curl_setopt($this->curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
			}else{
				curl_setopt($this->curl, CURLOPT_USERAGENT, "XMLHttpRequest/1.0");
			}
			curl_setopt($this->curl, CURLOPT_HEADER, true);
			curl_setopt($this->curl, CURLOPT_AUTOREFERER, true);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_HTTPHEADER,array('Content-type: text/plain', 'Content-length: 100'));
		}else{
			throw new Exception("Could not initialize cURL library");
		}
	}
	
	public function __destruct() {
		curl_close($this->curl);
	}
	
	public function __toString(){
		return __CLASS__;
	}
	
	public function open($method, $url, $async = false, $user = "", $password = ""){
		$this->properties = array("readystate" => 0);
		$this->responseHeaders;
		$this->headers = array();
		
		if(!empty($method) && !empty($url)){
			$method = strtoupper(trim($method));
			if(!preg_match("/^(GET|POST|HEAD|TRACE|PUT|DELETE|OPTIONS)$/", $method)){
				throw new Exception("Unknown HTTP method \"$method\"");
			}
			$referer = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
			if(!empty($referer) ){
				curl_setopt($this->curl, CURLOPT_REFERER, $referer);
			}elseif(isset($_SERVER['HTTP_REFERER'])){
				curl_setopt($this->curl, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
			}
			curl_setopt($this->curl, CURLOPT_URL, $url);
			if($method == "POST"){
				curl_setopt($this->curl, CURLOPT_POST, 1);
			}elseif($method == "GET"){
				curl_setopt($this->curl, CURLOPT_POST, 0);
			}else{
				curl_setopt($this->curl, CURLOPT_POST, 0);
				curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method); 
			}
			if(preg_match("/^(https)/", $url)){
				curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
			}
			if(!empty($user)){
				curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
				curl_setopt($this->curl, CURLOPT_USERPWD, $user.":". $password);
			}
		}
	}
	
	public function setRequestHeader($label, $value){
		$this->headers[] = "$label: $value";
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
	}
	
	public function getAllResponseHeaders(){
		return $this->responseHeaders;
	}
	
	public function getResponseHeader($label){
		$value = array();
		//preg_match_all('/(?s)'.$label.': (.*?)\s\n/i', $this->responseHeaders, $value);
		preg_match_all('/^(?s)'.$label.': (.*?)\s\n/im', $this->responseHeaders, $value);
		if(count($value ) > 0){
			return implode(', ', $value[1]);
		}
		return null;
	}
	
	public function send($data=null){
		if(isset($this->properties["maxredirects"]) && $this->properties["maxredirects"] && !ini_get('safe_mode')){
			curl_setopt($this->curl, CURLOPT_MAXREDIRS, $this->properties["maxredirects"]);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
		}
		if($data){
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
		}
		$response = curl_exec($this->curl);
		$header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
		$raw_header  = substr($response, 0, $header_size - 4);
		$headerArray = explode("\r\n\r\n", $raw_header);
		$header = $headerArray[count($headerArray) - 1];
		$this->properties["responsetext"] = substr($response, $header_size);
		$sT = array();
		preg_match('/^HTTP\/\d\.\d\s+(\d{3}) (.*)\s\n/i',$header ,$sT);
		if(count($sT ) > 2){
			//echo $sT[0]; die;
			$this->responseHeaders = preg_replace("~$sT[0]~","",$header)."\r\n\r\n";
			$this->properties["status"] = $sT[1];
			$this->properties["statustext"] = $sT[2];
		}
		$this->properties["readystate"] = 4;
	}
}

?>