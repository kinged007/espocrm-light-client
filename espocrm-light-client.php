<?php
namespace EspoCRMLightClient;

/****************************************************************************
 * Start class
 */
class Start {
	private $base_url = "";
	private $user = null;
	private $pass = null;
	
	public $excludeEntities = [];

	function __construct($url, $entities) {
		session_start();
		$this->views = new Views($this);
		$this->router = new Router($this->views);
		$this->excludeEntities = $entities;
		$this->base_url = $url;

		// Set routes
		$this->router->add('/', 'index');
		$this->router->add('/index', 'index');
		$this->router->add('/entity', 'entity');
		$this->router->add('/entity/:word:', 'item_list');
		$this->router->add('/entity/:word:/:hex:', 'item_single');
		$this->router->add('/sing_in', 'sing_in');
		$this->router->add('/addtask', 'addtask');

		// Start client
		$this->check_login();
	}



	public function call_api($url, $post = false, $data = ''){
		$curl = curl_init();
		$headers = [];

		curl_setopt($curl, CURLOPT_URL, $this->base_url . $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		
		array_push(
			$headers, 
			'Espo-Authorization: '.base64_encode(
				$this->user.':'.$this->pass
			)
		);
		
		if ($post) {
			curl_setopt($curl, CURLOPT_POST, true);
			array_push($headers, 'Content-Type: application/json');
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$curl_response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);
		
		curl_close($curl);

		if ($curl_response === false) {
			$this->views->render(
				'error',
				'<p><strong>Error occured during curl exec.</strong></p>
				<p>Additional info:</p>
				<pre>'. var_export($curl_info, true).'</pre>'
			);
			die();
		
		} else if($curl_info['http_code'] == '404'){
			$this->views->render(
				'error',
				'<p><strong>404 Not found.</strong></p>
				<p>Additional info:</p>
				<pre>'.var_export($curl_info, true).'</pre>'
			);
			die();

		}

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
				$this->login("Session expired.");
			
			// Current session
			} else {
				$this->router->start();
			}
		
		// There is no previous session
		} else {
			$this->login();
		}
	}


	private function login($msg = '') {
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
			$this->views->render('sing_in', $msg);
		}
	}
}



/****************************************************************************
 * Router
 */
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
				$this->views->render( $view, $matches );
				$match = true;
				break;
			} 
		}

		if(!$match) {
			$this->views->render(
				'error',
				'<p><strong>404 Route not found.</strong></p>
				<p>Route "'.$curr_route.'" is not defined</p>'
			);
			die();
		}
	}
}



/****************************************************************************
 * Views
 */
class Views {
	private $client = null;
	
	function __construct($espoclient) {
		$this->client = $espoclient;
	}



	public function render($view, $data = null) {
		$this->$view($data);
	}



	private function addtask() {
		$return = '';
		
		if(isset($_POST['addtask'])){
			$data = json_encode([
				'assignedUserId' => $_POST['assignedUserId'],
				'name' => $_POST['name'],
				'status' => $_POST['status'],
				'priority' => $_POST['priority'],
				'dateStart' => $_POST['year'].'-'.$_POST['month'].'-'.$_POST['day'].' 00:00:00',
				'description' => $_POST['description'],
			]);

			$api = $this->client->call_api('Task', true, $data);

			$return  = print_r(json_decode($api['response']),true);
			$return  = print_r($return, true);
			$return  = htmlentities($return);
			$return  = str_replace(" ", "&nbsp;", $return);
			$return  = nl2br($return);
			$return  = '<pre>'.$return.'</pre>';
		}

		$api = $this->client->call_api('App/user');
		$resp = json_decode($api['response']);
		$user_id = $resp->user->id;

		$tpl = new Template();
		$tpl->tpl('addtask');
		$tpl->breadcrumbs(['Add Task']);
		$tpl->data([ 
			'subtitle' => 'Add Task',
			'user_id' => $user_id,
			'day' => date('d'),
			'month' => date('m'),
			'year' => date('Y'),
			'return' => $return,
		]);

		$tpl->deploy();
	}



	private function error($msg = '') {
		$tpl = new Template();
		$tpl->data([ 
			'subtitle' => '<strong class="error">ERROR</strong>',
			'data' => $msg
		]);

		$tpl->deploy();
	}


	// *** Views ***

	private function entity() {
		$api = $this->client->call_api('Settings');
		$resp = json_decode($api['response']);

		$entities = $resp->tabList;
		$entities = array_filter($entities, function($var){
			return !in_array($var, $this->client->excludeEntities);
		});
		sort($entities);

		$data = '<ul class="list">';
		foreach ($entities as $entity) {
			$data .= '<li>';
			$data .= '<a href="?route=/entity/'.$entity.'">'.$entity.'</a>';
			$data .= '</li>';
		}
		$data .= '</ul>';		

		$tpl = new Template();
		$tpl->breadcrumbs(['Entities']);
		$tpl->data([ 'subtitle' => 'Entities', 'data' => $data ]);

		$tpl->deploy();
	}



	private function index() {
		$tpl = new Template();
		$tpl->tpl('index');
		$tpl->data([ 'subtitle' => 'Home']);

		$tpl->deploy();
	}



	private function item_list($data) {
		$entity = $data[1];

		// Sort
		$sort = 'sortBy=name&asc=true';
		if ($entity == 'Email') {
			$sort = 'sortBy=dateSent&asc=false';
		
		} else if ($entity == 'Receipts') {
			$sort = 'sortBy=createdAt&asc=false';
		}
		
		// Call API
		$api = $this->client->call_api($entity.'?'.$sort);
		$resp = json_decode($api['response']);

		// Filter items
		$items = $resp->list;
		$filter = function($var){ return true; };

		if ($entity == 'Lead') { 
			$filter = function($var){ return !in_array(
				$var->status, 
				['Converted', 'Recycled', 'Dead']
			); };
		
		} else if ($entity == 'Opportunity') {
			$filter = function($var){ return !in_array(
				$var->stage,
				['Closed Won', 'Closed Lost']
			); };
		
		} else if ($entity == 'Project') {
			$filter = function($var){ return in_array(
				$var->status,
				['Not Started', 'Started']
			); };
		
		} else if ($entity == 'Meeting' || $entity == 'Call') {
			$filter = function($var) {return in_array(
				$var->status,
				['Planned']
			);};
		
		} else if ($entity == 'Task') {
			$filter = function($var) {return !in_array(
				$var->status,
				['Completed', 'Canceled']
			);};

		} else if ($entity == 'Email') {
			$filter = function($var) {return in_array(
				$var->status,
				['Archived']
			);};
		}

		$items = array_filter($items, $filter);


		// Create list
		$list = '<ul class="list">';
		foreach ($items as $value) {

			// Show name
			$showName = $value->name;

			if ($entity == 'Project') {
				$showName  = $value->accountName.'<br />';
				$showName .= '&nbsp;&#8594;&nbsp;';
				$showName .= $value->typeofprojectName;
			
			} else if ($entity == 'Receipts') {
				$showName  = $value->name;
				$showName .= ' ('.$value->typeofreceipt.') ';
				$showName .= '$'.$value->amount.'<br />';
				$showName .= "&nbsp;&#8594;&nbsp;";
				$showName .= $value->date.' ';
				$showName .= $value->accountName;
			
			} else if ($entity == 'Email') {
				$showName = '';
				if ($value->isImportant == 1) { 
					$showName .= '<span style="color: #FB0;">&#9733;</span> '; 
				}
	
				$showName .= preg_replace ( 
					'#\ *<[^>]*>#' , 
					'', 
					$value->fromString
				);
				$showName .= "<br />&nbsp;&#8594;&nbsp;";
				$showName .= $value->name;
				
				if ($value->isRead == '') { 
					$showName = '<strong>'.$showName.'</strong>'; 
				}
			}
			
			$list .= '
				<li><a href="?route=/entity/'.$entity.'/'.$value->id.'">
					'.$showName.'
				</a></li>';
		}
		$list .= '</ul>';

		$tpl = new Template();
		$tpl->breadcrumbs([
			'<a href="?route=/entity">Entities</a>',
			$entity
		]);
		$tpl->data([ 
			'subtitle' => $entity,
			'data' => $list,
		]);

		$tpl->deploy();
	}



	private function item_single($data) {
		$route = $data[1].'/'.$data[2];
		$entity = $data[1];

		$api = $this->client->call_api($route.'?sortBy=name&asc=true');
		$resp = json_decode($api['response']);

		$single  = $resp;
		$single  = print_r($single, true);
		$single  = htmlentities($single);
		$single  = str_replace(" ", "&nbsp;", $single);
		$single  = nl2br($single);
		$single  = '<pre>'.$single.'</pre>';

		$tpl = new Template();
		$tpl->breadcrumbs([
			'<a href="?route=/entity">Entities</a>',
			'<a href="?route=/entity/'.$entity.'">'.$entity.'</a>',
			$resp->name
		]);
		$tpl->data([
			'subtitle' => $resp->name,
			'data' => $single,
		]);

		$tpl->deploy();
	}



	private function sing_in($msg) {
		$tpl = new Template();
		$tpl->tpl('sing_in');
		$tpl->data([ 
			'subtitle' => 'Access to EspoCRM',
			'msg' => '<p><strong class="error">'.$msg.'</strong></p>'
		]);

		$tpl->deploy();
	}
}



/****************************************************************************
 * Templates
 */
class Template {
	private $data = [];
	private $breadcrumbs = [];
	private $minify = true;
	private $wrap = true;
	private $html = '';
	private $tpl = 'base';

	public function wrap($value=true) {
		$this->wrap = $value;
	}
	
	public function breadcrumbs($value=[]) {
		$this->breadcrumbs = $value;
	}

	public function data($value=[]) {
		$this->data = $value;
	}

	public function minify($value=true) {
		$this->minify = $value;
	}
	
	public function html($value='') {
		$this->html = $value;
	}
	
	public function tpl($value='') {
		$this->tpl = $value;
	}

	public function deploy() {
		$html = $this->templates[$this->tpl];
		
		if ($this->wrap) { 
			$wrap  = $this->templates['header'];
			$wrap .= $html;
			$wrap .= $this->templates['footer']; 

			$html = $wrap;
		}

		if (!empty($this->data)){
			$html = $this->replaceData($html);
		}
		
		if ($this->minify){
			$html = $this->doMinify($html);
		}
		
		echo $html;
	}



	private function replaceData($html){
		$tmp = $html;

		foreach ($this->data as $key => $val) {
			$tmp = str_replace('{{'.$key.'}}', $val, $tmp);
		}
		
		if (!empty($this->breadcrumbs)){
			$tmp = str_replace(
				'{{breadcrumbs}}', 
				$this->doBreadCrumbs($this->breadcrumbs),
				$tmp
			);
		} else {
			$tmp = str_replace(	'{{breadcrumbs}}', '', $tmp);
		}
		
		return $tmp;
	}



	private function doBreadCrumbs($items){
		$html  = '<p class="breadcrumbs">';
		$html .= '<a href="?route=/">Home</a> > ';
		
		$len = count($items);
		$i = 0;
		foreach ($items as $value) {
			$html .= $value;
			if ($i != $len - 1) { $html .= ' > '; }
			$i++;
		}
		
		$html .= '</p>';

		return $html;
	}



	private function doMinify($html){
		// http://stackoverflow.com/questions/6225351/how-to-minify-php-page-html-output
		$search = array(
			'/\>[^\S ]+/s',  // strip whitespaces after tags, except space
			'/[^\S ]+\</s',  // strip whitespaces before tags, except space
			'/(\s)+/s',       // shorten multiple whitespace sequences
			'/(\t)+/s'       // shorten multiple tab sequences
		);

		$replace = array(
			'>',
			'<',
			'\\1',
			''
		);

		$html = preg_replace($search, $replace, $html);

		return $html;
	}



	private $templates = [
		'base' => '{{data}}',

		'addtask' => '
			<form method="POST">
				<input type="hidden" name="assignedUserId" value="{{user_id}}" />
				<input type="hidden" name="addtask" value="1" />
				<input type="text" placeholder="Subject" name="name" />
				<select name="status"> 
					<option value="Not Started">Sin Empezar</option>
					<option value="Started">Started</option>
					<option value="Completed">Completed</option>
					<option value="Canceled">Canceled</option>
					<option value="Deferred">Deferred</option>
				</select>
				
				<select name="priority"> 
					<option value="Low">Low</option>
					<option value="Normal" selected="">Normal</option>
					<option value="High">High</option>
					<option value="Urgent">Urgent</option>
				</select>
				<div class="group">
					<input type="text" placeholder="Day" name="day" size="2" value="{{day}}" />
					<input type="text" placeholder="Month" name="month" size="2" value="{{month}}" />
					<input type="text" placeholder="Year" name="year" size="2" value="{{year}}" />
				</div>
				<textarea placeholder="Description" name="description"></textarea>
				<input type="submit" value="Add Task" />
			</form>
			{{return}}
		',

		'header' => '
			<!DOCTYPE html>
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
					input, textarea, select {
						width: 96%;
						display: block;
						margin: 0 0 20px 0;
						padding: 0 2%;
						height: 40px;
						line-height: 40px;
						border: 1px solid #8CBA18;
						font-size: 1em;
					}
					textarea {
						height: 120px;
					}
					select {
						width: 100%;
						background-color: #fff;
						border-radius: 0;
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
					.group input {
						width: 26.5%;
						display: inline-block;
						text-align: center;
						margin-left: 1%;
						margin-right: 1%;
					}
				</style>
			</head>
			<body>
			<h1>EspoCRM Light Client</h1>
			<h2>{{subtitle}}</h2>
			{{breadcrumbs}}
		',

		'footer' => '</body></html>',

		'index' => '
			<ul class="list">
				<li><a href="?route=/entity">Entities</a></li>
				<li><a href="?route=/addtask">Add Task</a></li>
			</ul>
		',

		'sing_in' => '
			<form method="POST">
				<input type="text" placeholder="Username" name="user" />
				<input type="password" placeholder="Password" name="pass" />
				{{msg}}
				<input type="submit" value="Sing In" />
			</form>
		'
	];
}

?>