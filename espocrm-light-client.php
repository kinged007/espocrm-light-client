<?php

class Views {
	private $client = null;
	private $templates = null;
	
	function __construct($espoclient) {
		$this->client = $espoclient;
		$this->templates = new Templates();
	}

	public function render($view, $data = null) {
		$this->$view($data);
	}

	private function replaceData($html, $data){
		$tmp = $html;

		foreach ($data as $key => $val) {
			$tmp = str_replace('{{'.$key.'}}', $val, $tmp);
		}
		
		return $tmp;
	}

	private function sing_in($msg) {
		$tpl = $this->templates->getTemplate('sing_in', true);
		echo $this->replaceData($tpl, ['msg' => '<p><strong class="error">'.$msg.'</strong></p>']);
	}
	/*
	private function index($data) {
		$api = $this->client->call_api('Settings');
		$resp = json_decode($api['response']);

		$menu = '<ul class="list">';
		foreach ($resp->tabList as $value) {
			$menu .= '<li><a href="?acc=view&exp=' . $value . '">' . $value . 
						'</a></li>';
		}
		$menu .= '</ul>';

		//$this->render('index', null, $menu);
	}


	private function entity() {
		$api = $this->call_api('Settings');
		$resp = json_decode($api['response']);

		$menu = '<ul class="list">';
		foreach ($resp->tabList as $value) {
			$menu .= '<li><a href="?acc=view&exp=' . $value . '">' . $value . 
							'</a></li>';
		}
		$menu .= '</ul>';

		//$this->render('entity', null, $menu);
	}

	private function view($exp) {
		$sort = 'sortBy=name&asc=true';

		if ($exp == 'Email') {
			$sort = 'sortBy=dateSent&asc=false';
		
		} else if ($exp == 'Receipts') {
			$sort = 'sortBy=createdAt&asc=false';
		}
		
		$api = $this->call_api($exp.'?'.$sort);
		$resp = json_decode($api['response']);

		$list = '<ul class="list">';
		foreach ($resp->list as $value) {
			$show = true;
			
			if ($exp == 'Lead') { 
				if (in_array(
						$value->status, 
						['Converted', 'Recycled', 'Dead']
					)){
					$show = false;
				}
			
			} else if ($exp == 'Opportunity') {
				if (in_array($value->stage, ['Closed Won', 'Closed Lost'])){
					$show = false;
				}
			} else if ($exp == 'Project') {
				if (
					$value->status != 'Not Started' && 
					$value->status != 'Started'
				){
					$show = false;
				}
			} else if ($exp == 'Meeting' || $exp == 'Call') {
				if ($value->status != 'Planned'){
					$show = false;
				}
			} else if ($exp == 'Task') {
				if (in_array($value->status, ['Completed', 'Canceled'])){
					$show = false;
				}
			} else if ($exp == 'Email') {
				if ($value->status != 'Archived'){
					$show = false;
				}
			}
			

			$showName = $value->name;
			if ($exp == 'Project') {
				$showName = $value->accountName. "<br />&gt;&gt;&gt; " . 
					$value->typeofprojectName;
			
			} else if ($exp == 'Receipts') {
				$showName = $value->name . " (".$value->typeofreceipt.") $". 
					$value->amount . "<br />&gt;&gt;&gt; " . $value->date . 
					" " . $value->accountName;
			
			} else if ($exp == 'Email') {
				$showName = $value->fromString . "<br />&gt;&gt;&gt; " . 
				$value->name;
			}
			
			if ($show) {
				$list .= '<li><a href="?acc=item&exp=' . $exp . '/' .
					$value->id . '">' . $showName . '</a></li>';
			}
		}
		$list .= '</ul>';

		//$this->render('list', $exp, $list);
	}

	private function item($exp) {
		echo "item";
		$api = $this->client->call_api($exp.'?sortBy=name&asc=true');
		$resp = json_decode($api['response']);

		$item = '<pre>';
		$item .= htmlentities(print_r($resp, true));
		$item .= '</pre>';

		//$this->client->render('item', $exp, $item);
	}
	*/


}







class Templates {
	public function getTemplate($tpl, $wrap = false){
		if ($wrap) {
			return $this->header.$this->$tpl.$this->footer;
		} else {
			return $this->$tpl;
		}
	}


	private $header = '<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="Content-Type" content="text/html; 
		charset=UTF-8">
	<title>EspoCRM - Mobile</title>
	<meta name="viewport" content="width=device-width, 
		user-scalable=no, initial-scale=1, maximum-scale=1">

	<style>
		* {
			font-family: sans-serif;
			font-size: 18px;
		}
		input {
			width: 96%;
			display: block;
			margin: 0 0 20px 0;
			padding: 0 2%;
			height: 40px;
			line-height: 40px;
			border: 1px solid #8CBA18;
		}
		input[type="submit"] {
			border: none;
			background-color: #8CBA18;
			color: #fff;
			padding: 0;
			width: 100%;
		}
		.error {
			color: red;
		}
		.list {
			list-style: none;
			margin: 0;
			padding: 0;
			border-top: 1px solid #8CBA18;
		}
		.list li {
			border-bottom: 1px solid #8CBA18;
		}
		.list li a {
			line-height: 26px;
			padding: 15px 20px;
			color: #000;
			display: block;
			text-decoration: none;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
		}
		pre {
			font-family: mono;
			font-size: 14px;
		}
	</style>
</head>
<body>
';
		
	private $footer = '</body></html>';
	
	private $sing_in = '
<h1>Access to EspoCRM</h1>
<form method="POST">
	<input type="text" placeholder="Username" name="user" />
	<input type="password" placeholder="Password" name="pass" />
	{{msg}}
	<input type="submit" value="Sing In" />
</form>
';

	private $index = '
<h1>Index</h1>
<ul class="list">
	<li><a href="?acc=entity">Entities</a></li>
	<li><a href="?acc=addtask">Add Task</a></li>
</ul>
';
		
	private $entity = '
<h1>Entities</h1>
{{data}}
';
	
	private $list = '
<h1>{{title}}</h1>
{{data}}
';

	private $item = '
<h1>{{title}}</h1>
{{data}}
';

}














class EspoCRMLightClient {
	private $base_url = "";
	private $user = null;
	private $pass = null;
	private $views = null;



	function __construct($url) {
		session_start();
		$this->views = new views($this);
		$this->base_url = $url;

		$this->check_login();
	}



	public function call_api($url){
		$service_url = $this->base_url.$url;

		$curl = curl_init($service_url);

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt(
			$curl, 
			CURLOPT_HTTPHEADER, 
			array(
				'Espo-Authorization: '.base64_encode(
					$this->user.':'.$this->pass
				)
			)
		);

		$curl_response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);

		if ($curl_response === false) {
			$info = curl_getinfo($curl);
			curl_close($curl);
			die(
				'Error occured during curl exec. Additioanl info: ' . 
				var_export($info)
			);
		
		} else if($curl_info['http_code'] == '404'){
			curl_close($curl);
			die('404 Not found.'. print_r($curl_info,true));

		}

		curl_close($curl);

		return array(
			'response' => $curl_response,
			'info' => $curl_info,
			);
	}

	private function check_login() {
		// Is there a previous session?
		if(isset($_SESSION['espo_user']) && isset($_SESSION['espo_token']) ){
			$this->user = $_SESSION['espo_user'];
			$this->pass = $_SESSION['espo_token'];
		
			// Is valid session?
			$api = $this->call_api('App/user');
			$resp = json_decode($api['response']);
			$status = $api['info']['http_code'];

			// Invalid session (Unauthorized)
			if($status == '401') { 
				$msg = "Session expired.";
				$this->login();
			
			// Current session
			} else {
				$this->router();
			}
		
		// There is no previous session
		} else {
			$this->login();
		}
	}


	private function login() {
		// Send username and password?
		if(isset($_POST['user']) && isset($_POST['pass'])){
			
			// Save user and pass
			$this->user = $_POST['user'];
			$this->pass = $_POST['pass'];

			// Checks if the data are correct
			$api = $this->call_api('App/user');
			$resp = json_decode($api['response']);
			$status = $api['info']['http_code'];

			// Incorrect data
			if($status == '401') {
				$this->views->render('sing_in', 'User or password incorrect.');
			
			// Correct data
			} else if($status == '200') {
				$_SESSION['espo_user'] = $_POST['user'];
				$_SESSION['espo_token'] = $resp->token;
				$this->check_login();
			}

		} else {
			//$msg = '<strong class="error">'.$_GET["msg"].'</strong>';
			$this->views->render('sing_in');
		}
	}



	function router() {
		if(!isset($_GET['acc']) || $_GET['acc'] == '' ){
			header('Location: ?acc=index');
			die();

		} else {
			$acc = $_GET['acc'];
			
		}

		if(isset($_GET['exp'])){
			$exp = $_GET['exp'];
		}

		if ($acc == 'index') {
			$this->views->render('index');

		} else if ($acc == 'entity') {
			$this->views->render('entity');
		
		} else if ($acc == 'sing_in') {
			$this->views->render('sing_in');
		
		} else if ($acc == 'view') {
			$this->views->render('item');
		
		} else if ($acc == 'item') {
			$this->views->render('item');
		
		} else if ($acc == 'addtask') {
			$this->views->render('addtask');
		
		} else {
			die('Error: Unrecognized expression.');
		}
	}
}

?>