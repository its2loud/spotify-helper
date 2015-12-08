<?php

$usrdata_file = "data/usrdata"; // needs write permissions!
$usrdata = array();

function get_redirect_uri($action) {
	if ($action!="") {
		$action_str="?action=".$action;
	}
	return "http://".$_SERVER["HTTP_HOST"].strtok($_SERVER["REQUEST_URI"],'?').$action_str;
}

function load_usrdata(&$usrdata, $usrdata_file) {
	$usrdata=unserialize(file_get_contents($usrdata_file));
    return (strlen($usrdata["user_id"])>5) && (strlen($usrdata["client_id"])>5) && (strlen($usrdata["client_secret"])>5) && (strlen($usrdata["refresh_token"])>5);
}

function save_usrdata(&$usrdata, $usrdata_file) {
	return file_put_contents($usrdata_file,serialize($usrdata));
}

if (load_usrdata($usrdata, $usrdata_file) && !isset($_GET["action"])) {

	if (!isset($_GET["limit"])) {
		$limit_str="?limit=5";
	} else {
		$limit_str="?limit=".$_GET["limit"];
	}

	$url = "https://accounts.spotify.com/api/token";
	$postdata = [
	    'client_id' => $usrdata["client_id"],
	    'client_secret' => $usrdata["client_secret"],
	    'grant_type' => 'refresh_token',
	    'refresh_token' => $usrdata["refresh_token"],
	];
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);
	$access_token = json_decode($response)->{'access_token'};

	$url = "https://api.spotify.com/v1/users/".$usrdata["user_id"]."/playlists".$limit_str;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$access_token));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);

	echo $response;

} else {

	?>
	<!DOCTYPE html>
	<html>
	<head>
		<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
		<style type="text/css">
			body {
				background: #eee; padding: 8%;
			}
			#setup_form {
				width: 500px; padding: 50px; background: #fff; position: absolute; left: 0; right: 0; margin: auto;	border-radius: 20px; box-shadow: 0px 0px 15px 0px rgba(0,0,0,0.1);
			}
			h1 {
				text-align: center;	margin-bottom: 50px;
			}
			#bar {
				background-color: #16be65; position: fixed;	height: 5px; width: 100%; top: 0; left: 0;
			}
			em {
				font-size: smaller;
			}
			.instruction {
				margin-bottom: 10px;
			}
		</style>
	</head>
	<body>
	<div id="bar"></div>
	<?php

	if ($_GET["action"]=="getauth") {

		$usrdata["user_id"] = $_GET["user_id"];
		$usrdata["client_id"] = $_GET["client_id"];
		$usrdata["client_secret"] = $_GET["client_secret"];
		save_usrdata($usrdata, $usrdata_file);

		$getdata = [
		        'client_id' => $usrdata["client_id"],
		        'response_type' => 'code',
		        'redirect_uri' => get_redirect_uri("callback"),
		        'scope' => 'user-read-private user-read-email'
		    ];

		header('Location: https://accounts.spotify.com/authorize/?'.http_build_query($getdata));

	} else if ($_GET["action"]=="callback") {

		$url = "https://accounts.spotify.com/api/token";
		$postdata = [
		    'client_id' => $usrdata["client_id"],
		    'client_secret' => $usrdata["client_secret"],
		    'grant_type' => 'authorization_code',
		    'code' => $_GET["code"],
		    'redirect_uri' => get_redirect_uri("callback"),
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		$usrdata["refresh_token"] = json_decode($response)->{'refresh_token'};
		save_usrdata($usrdata, $usrdata_file);

		?>
		<h1>Successfully authorized access to Spotify!</h1>
		<form role="form" id="setup_form">
		  <div class="instruction">
		  	<em>You can now use the URL to this site to get a JSON dictionary of your Spotify playlists:</em><br>
		  	<h5><a href="<?php echo get_redirect_uri();?>"><?php echo get_redirect_uri();?></a></h5>
		  	<br>
		  	<em>Use the "?limit=N" parameter to set another limit than the default value of 5 results:</em>
		  	<h5><a href="<?php echo get_redirect_uri()."?limit=3";?>"><?php echo get_redirect_uri()."?limit=3";?></a></h5>
		  	<br>
		  	<em>Once authorized, this script can of course be used for any other request to the Spotify API with a little modification.</em>
		  </div>
		</form>
		<?php

	} else {

		?>
		<h1>Workflow Spotify Helper</h1>
		<form role="form" id="setup_form">
		  <div class="instruction">
		  <em>
		  	1. Get your own Spotify ID: Log into your Acoount on spotify and goto "Set device password". Your ID is shown as "Your device username is:"<br>
		  	<a href="https://www.spotify.com/us/account/set-device-password/" target="_blank">https://www.spotify.com/us/account/set-device-password/</a>
		  </em>
		  </div>	
		  <div class="form-group">
			  <label for="user_id">Device Username / Owner ID:</label>
			  <input class="form-control" type='text' name='user_id' value=''>
		  </div>
		  <div class="instruction">
		  <em>
		  	2. Log into the Spotify developer website and create a new App. Choose a name like "Workflow Spotify Helper". Don't forget to set the correct Redirect URI to this site using the "?action=callback" parameter. Paste Client ID and Client Secret below.<br>
		  	<a href="https://developer.spotify.com/my-applications/#!/" target="_blank">https://developer.spotify.com/my-applications/#!/</a>
		  </em>
		  </div>
		  <div class="form-group">
			  <label for="client_id">Application's Client ID:</label>
			  <input class="form-control" type='text' name='client_id' value=''>
		  </div>
		  <div class="form-group">
			  <label for="client_secret">Application's Client Secret:</label>
			  <input class="form-control" type='text' name='client_secret' value=''>
		  </div>
		  <div class="form-group">
			  <label for="redirect_uri">Redirect URI:</label>
			  <input class="form-control" type='text' name='redirect_uri' value='<?php echo get_redirect_uri("callback"); ?>' disabled>
		  </div>
		  <input type='text' name='action' value='getauth' hidden><br>
		  <button type='submit' class="btn btn-default">Authorize access to Spotify</button>
		</form>
		<?php
	}

	?>
	</body>
	</html>
	<?php

}

?>