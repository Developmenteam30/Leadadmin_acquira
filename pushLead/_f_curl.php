<?php //_function_curl.php VER 1.5
//changelog
//ES 20110414 VER 1.5: Added $followlocation to parameters, defaults to false
//ES 20100624 VER 1.4: Got rid of a memory leak in the curl function. Bad matt!
//ES 20100623 VER 1.3: Set up the curl to return the error if there is one instead of nothing at all.
//ES 20100526 VER 1.2: Added $httpheader field, defaults to nothing. 
//ES 20100517 VER 1.1: Added more precise timeout reasons to results array

function PushLead($requestdata, $url, $post, $verifypeer = false, $returntransfer = true, $header = false, $httpheader = NULL, $followlocation = false) {
	
	ob_start();
	// echo $request;
	$ch=curl_init();
	
	curl_setopt($ch, CURLOPT_URL, $url);
	if($post) { //Post 
		curl_setopt($ch, CURLOPT_POST, true);
	}
	
	if(isset($requestdata) && $requestdata != "")
	{ //Post Fields in an array as opposed to getstring in the url. 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestdata);
	}
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifypeer);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returntransfer);
	curl_setopt($ch, CURLOPT_HEADER, $header);
	if(isset($httpheader))
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
	}
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, $followlocation );
	
	$timeout = 35;
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	
	$response = curl_exec ($ch);
	if (curl_errno($ch)!=0)
	{
		$response = "CURL Error: ".curl_error($ch);
	}
	curl_close($ch);
	//echo $process_result;
	ob_end_clean();
	return $response;
} 

?>