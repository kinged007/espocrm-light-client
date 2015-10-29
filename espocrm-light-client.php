<?php

class Router {
	private $routes = [];
	private $views = [];

	function __construct($views) {
		$this->views = $views;
	}

	public function add($route, $view) {
		$this->routes[$route] = $view;
	}

	public function start(){
		$curr_route = '/';
		if (isset($_GET['route']) && $_GET['route'] != ''){
			$curr_route = $_GET['route'];
		}
		
		$match = false;
		
		foreach ($this->routes as $route => $view) {
			$route = str_replace(':word:', '(\w+)', $route);
			$route = str_replace(':hex:', '([0-9a-fA-F]+)', $route);

			preg_match ( '#^'.$route.'$#' , $curr_route, $matches);
			if (count($matches) > 0) {
				$this->views->render(
					$view,
					$matches
				);
				$match = true;
				break;
			
			} 
		}
		if(!$match) {
			die('404 Route Not Found.');
		}
	}
}







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
	
	private function index() {
		$tpl = $this->templates->getTemplate('index', true);
		echo $this->replaceData($tpl, [
			'breadcrumbs' => '',
			'subtitle' => 'Home',
			]);
		//
	}


	private function entity() {
		$api = $this->client->call_api('Settings');
		$resp = json_decode($api['response']);

		sort($resp->tabList);

		$data = '<ul class="list">';
		foreach ($resp->tabList as $value) {
			if (!in_array($value, $this->client->excludeEntities)) {
				$data .= '<li><a href="?route=/entity/'.$value.'">
							' . $value . '
						</a></li>';
			}
		}
		$data .= '</ul>';

		$tpl = $this->templates->getTemplate('entity', true);
		echo $this->replaceData($tpl, [
			'subtitle' => 'Entities',
			'breadcrumbs' => '<p class="breadcrumbs"><a href="?route=/">Home</a> > Entities</p>',
			'data' => $data,

		]);
	}

	private function view($data) {
		$exp = $data[1];
		$sort = 'sortBy=name&asc=true';

		if ($exp == 'Email') {
			$sort = 'sortBy=dateSent&asc=false';
		
		} else if ($exp == 'Receipts') {
			$sort = 'sortBy=createdAt&asc=false';
		}
		
		$api = $this->client->call_api($exp.'?'.$sort);
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
				$showName = '';
				if ($value->isRead == '') { $showName .= '<strong>'; }
				if ($value->isImportant == 1) { $showName .= '&#9733; '; }
	
				if (isset($value->personStringData)){
					$showName .= $value->personStringData;
				
				} else {
					$showName .= $value->fromString;
				}
				
				if ($value->isRead == '') { $showName .= '</strong>'; }
				$showName .= "<br />&gt;&gt;&gt; ".$value->name;
			}
			
			if ($show) {
				$list .= '
					<li><a href="?route=/entity/'.$exp.'/'.$value->id.'">
						'.$showName.'
					</a></li>';
			}
		}
		$list .= '</ul>';

		$tpl = $this->templates->getTemplate('list', true);
		echo $this->replaceData($tpl, [
			'data' => $list,
			'subtitle' => $exp,
			'breadcrumbs' => '<p class="breadcrumbs">
				<a href="?route=/">Home</a> > 
				<a href="?route=/entity">Entities</a> > 
				'.$exp.'
			</p>',
		]);
	}

	private function item($data) {
		$exp = $data[1].'/'.$data[2];
		$ent = $data[1];
		$itemAA = $data[2];

		$api = $this->client->call_api($exp.'?sortBy=name&asc=true');
		$resp = json_decode($api['response']);

		$item = '<pre>';
		$item .= htmlentities(print_r($resp, true));
		$item .= '</pre>';

		$tpl = $this->templates->getTemplate('item', true);
		echo $this->replaceData($tpl, [
			'data' => $item,
			'subtitle' => $itemAA,
			'breadcrumbs' => '<p class="breadcrumbs">
				<a href="?route=/">Home</a> > 
				<a href="?route=/entity">Entities</a> > 
				<a href="?route=/entity/'.$ent.'">'.$ent.'</a> > 
				'.$itemAA.'
			</p>',
		]);
	}
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
		body {
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
		h1 {
			text-align: center;
			font-size: 1.5em;
		}
		h2 {
			text-align: center;
			font-size: 1.25em;
			font-weight: 400;
		}
		.breadcrumbs {
			font-size: .8em;
		}
	</style>
</head>
<body>
<h1>EspoCRM Light Client</h1>
<h2>{{subtitle}}</h2>
{{breadcrumbs}}
';
		
	private $footer = '</body></html>';
	
	private $sing_in = '
<form method="POST">
	<input type="text" placeholder="Username" name="user" />
	<input type="password" placeholder="Password" name="pass" />
	{{msg}}
	<input type="submit" value="Sing In" />
</form>
';

	private $index = '
<ul class="list">
	<li><a href="?route=/entity">Entities</a></li>
	<li><a href="?route=/addtask">Add Task</a></li>
</ul>
';
		
	private $entity = '{{data}}';
	
	private $list = '{{data}}';

	private $item = '{{data}}';
}














class EspoCRMLightClient {
	private $base_url = "";
	private $user = null;
	private $pass = null;
	private $views = null;
	
	public $excludeEntities = [];



	function __construct($url, $entities) {
		session_start();
		$this->views = new views($this);
		$this->router = new Router($this->views);
		$this->excludeEntities = $entities;
		$this->base_url = $url;

		$this->router->add('/', 'index');
		$this->router->add('/index', 'index');
		$this->router->add('/entity', 'entity');
		$this->router->add('/entity/:word:', 'view');
		$this->router->add('/entity/:word:/:hex:', 'item');
		$this->router->add('/sing_in', 'sing_in');

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
				$this->router->start();
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
}

?>