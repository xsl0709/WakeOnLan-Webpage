<?php
require_once('config.php');

function error(){
	$message = func_get_arg(0);
	$errorCode = func_get_arg(1);
	if (!$errorCode) $errorCode = 500;
	http_response_code($errorCode);
	throw new Exception($message, $errorCode);
}

function authorize($rawBasicAuth){
	if (substr( $rawBasicAuth, 0, 6 ) !== "Basic ") error("Use Basic Authentification to provide username and password!", 401);
	$encodedBasicAuth = substr( $rawBasicAuth, 6, strlen($rawBasicAuth) );
	$basicAuth = base64_decode($encodedBasicAuth);
	$splittedBasicAuth = explode(":", $basicAuth);
	$user = $splittedBasicAuth[0];
	$password = $splittedBasicAuth[1];
	if (Config::$hashAlgorithm) $password = hash(Config::$hashAlgorithm ,$password);
	if (Config::$users[$user] !== $password) error("Wrong username or password", 403);
}

function handlePost($header, $body){
	if (Config::$authLevel >=1) authorize($header["AUTHORIZATION"]);
	if ($body->id === null) error("device id need to be provided!", 400);
	$hardwareAddress = Config::$devices[$body->id]["hardwareAddress"];
	exec("wakeonlan -i " . Config::$broadcastIpAddress . " " . $hardwareAddress, $output, $errorCode);
	if ($errorCode == 0) return $output[0];
	else error("could not wakeonlan " . Config::$devices[$body->id]["name"] . "!", 500);
}

function handleGet($header, $body){
	if (Config::$authLevel >=2) authorize($header["AUTHORIZATION"]);
	$devices = Config::$devices;
	for ($i=0; $i<count($devices); $i++){
		$devices[$i]["id"] = $i;
		unset($devices[$i]["hardwareAddress"]);
	}
	header('Content-Type: application/json');
	return json_encode($devices);
}

function getHttpHeader(){
	$header;
	foreach($_SERVER as $key=>$value) {
		if (substr( $key, 0, 5 ) === "HTTP_")
			$header[substr($key, 5, strlen($key))] = $value;
	}
	return $header;	
}

function handleRequest(){
	try {
		$rawBody = file_get_contents('php://input');
		$header = getHttpHeader();
		$body = json_decode($rawBody);
		switch($_SERVER['REQUEST_METHOD']){
			case "GET":
				echo(handleGet($header, $body));
				break;
			case "POST":
				echo(handlePost($header, $body));
				break;
			default:
				error("Method not allowed!", 405);
				break;
		}
	} catch(Exception $e) {
		echo($e->getMessage());
		http_response_code($e->getCode());
	}
}

handleRequest();
?>
