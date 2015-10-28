<?php
/**
 *
 *
 ****************************  $Common Functions  ***************************
 */
function call_api($url, $user = null, $pass = null){
	global $base_url;

	if (!isset($user)){ $user = $_SESSION['espo_user']; }
	if (!isset($pass)){ $pass = $_SESSION['espo_token']; }

	$service_url = $base_url.$url;

	$curl = curl_init($service_url);

	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		'Espo-Authorization: ' . base64_encode($user . ':' . $pass)
	));

	$curl_response = curl_exec($curl);
	$curl_info = curl_getinfo($curl);

	if ($curl_response === false) {
		$info = curl_getinfo($curl);
		curl_close($curl);
		die('error occured during curl exec. Additioanl info: ' . var_export($info));
	
	} else if($curl_info['http_code'] == '404'){
		curl_close($curl);
		die('404 Not found.');

	}

	curl_close($curl);

	return array(
		'response' => $curl_response,
		'info' => $curl_info,
		);
}



function check_login() {
	if (!isset($_SESSION['espo_token'])) {
		if(!isset($_GET['acc']) || $_GET['acc'] != 'sing_in'){
			header('Location: ?acc=sing_in');
			die();
		}
	} else {
		return true;
	}

}



function render($page, $title = '', $data = '') {
	echo get_part('header');
	echo get_part($page, $title, $data);
	echo get_part('footer');
}



/**
 *
 *
 **********************************  $Router  *******************************
 */
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
		index();

	} else if ($acc == 'entity') {
		entity();
	
	} else if ($acc == 'sing_in') {
		sing_in();
	
	} else if ($acc == 'view') {
		view($exp);
	
	} else if ($acc == 'item') {
		item($exp);
	
	} else if ($acc == 'addtask') {
		addtask();
	
	} else {
		die('Error: Unrecognized expression.');
	}
}








/**
 *
 *
 **********************************  $Views  ********************************
 */
function index() {
	$api = call_api('Settings');
	$resp = json_decode($api['response']);

	$menu = '<ul class="list">';
	foreach ($resp->tabList as $value) {
		$menu .= '<li><a href="?acc=view&exp=' . $value . '">' . $value . '</a></li>';
	}
	$menu .= '</ul>';

	render('index', null, $menu);
}

function entity() {
	$api = call_api('Settings');
	$resp = json_decode($api['response']);

	$menu = '<ul class="list">';
	foreach ($resp->tabList as $value) {
		$menu .= '<li><a href="?acc=view&exp=' . $value . '">' . $value . '</a></li>';
	}
	$menu .= '</ul>';

	render('entity', null, $menu);
}

function view($exp) {
	$sort = 'sortBy=name&asc=true';

	if ($exp == 'Email') {
		$sort = 'sortBy=dateSent&asc=false';
	
	} else if ($exp == 'Receipts') {
		$sort = 'sortBy=createdAt&asc=false';
	}
	
	$api = call_api($exp.'?'.$sort);
	$resp = json_decode($api['response']);

	$list = '<ul class="list">';
	foreach ($resp->list as $value) {
		//print_r($value);
		$show = true;
		
		if ($exp == 'Lead') { 
			if (in_array($value->status, ['Converted', 'Recycled', 'Dead'])){
				$show = false;
			}
		
		} else if ($exp == 'Opportunity') {
			if (in_array($value->stage, ['Closed Won', 'Closed Lost'])){
				$show = false;
			}
		} else if ($exp == 'Project') {
			if ($value->status != 'Not Started' && $value->status != 'Started'){
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
			$showName = $value->accountName. "<br />&gt;&gt;&gt; " . $value->typeofprojectName;
		
		} else if ($exp == 'Receipts') {
			$showName = $value->name . " (".$value->typeofreceipt.") $". $value->amount . "<br />&gt;&gt;&gt; " . $value->date . " " . $value->accountName;
		
		} else if ($exp == 'Email') {
			$showName = $value->fromString . "<br />&gt;&gt;&gt; " . $value->name;
		}
		
		if ($show) {
			$list .= '<li><a href="?acc=item&exp=' . $exp . '/' .$value->id . '">' . $showName . '</a></li>';
		}
	}
	$list .= '</ul>';

	render('list', $exp, $list);
}

function item($exp) {
	$api = call_api($exp.'?sortBy=name&asc=true');
	$resp = json_decode($api['response']);

	$item = '<pre>';
	$item .= htmlentities(print_r($resp, true));
	$item .= '</pre>';

	render('item', $exp, $item);
}




function sing_in(){
	$msg = '';

	if(isset($_POST['user']) && isset($_POST['pass'])){
		$api = call_api('App/user', $_POST['user'], $_POST['pass']);
		$resp = json_decode($api['response']);
		$status = $api['info']['http_code'];

		if($status == '401') {
			$msg = '<strong class="error">User or password incorrect.</strong>';
		
		} else if($status == '200') {
			$_SESSION['espo_user'] = $_POST['user'];
			$_SESSION['espo_token'] = $resp->token;
		}
	}

	if(check_login()){
		header('Location: ?exp=index');
		die();
	}

	render('sing_in');

	echo $msg;
}






/**
 *
 *
 ******************************  $Templates  ****************************
 */
function get_part($part, $title = '', $data = ''){
	if ($part == 'header'){
		return '
			<!DOCTYPE html>
			<html lang="es">
			<head>
				<meta charset="utf-8">
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
				<title>EspoCRM - Mobile</title>
				<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1, maximum-scale=1">

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
	
	} else if ($part == 'footer') {
		return '</body></html>';
	
	} else if ($part == 'sing_in') {
		return '
			<h1>Access to EspoCRM</h1>
			<form method="POST">
				<input type="text" placeholder="Username" name="user" />
				<input type="password" placeholder="Password" name="pass" />
				<input type="submit" value="Sing In" />
			</form>
		';
	
	} else if ($part == 'index') {
		return '
			<h1>Index</h1>
			<ul class="list">
				<li><a href="?acc=entity">Entities</a></li>
				<li><a href="?acc=addtask">Add Task</a></li>
			</ul>
		';
	
	} else if ($part == 'entity') {
		return '
			<h1>Entities</h1>
			'.$data.'
		';
	
	} else if ($part == 'list') {
		return '
			<h1>'.$title.'</h1>
			'.$data.'
		';
	
	} else if ($part == 'item') {
		return '
			<h1>'.$title.'</h1>
			'.$data.'
		';
	}


}















// Start App
session_start();

check_login();

router();

?>