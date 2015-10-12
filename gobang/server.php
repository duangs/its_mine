<?php
error_reporting(E_ALL);
set_time_limit(0);

require('Ws.php');
$ws = new Ws('127.0.0.1', '8888', 10);
$ws->function['add'] = 'user_add_callback';
$ws->function['send'] = 'send_callback';
$ws->function['close'] = 'close_callback';
$ws->start_server();
//回调函数们
function user_add_callback($index,$ws) {	
	$wait_game_user = array_filter($ws->game, function($var){
		return is_null($var);
	});
	
	if(isset($wait_game_user[$index])){
		unset($wait_game_user[$index]);
	}	
	
	if($wait_game_user){
		while(($user_key = array_rand($wait_game_user)) != $index){
			$user_key = array_rand($wait_game_user);
			$ws->game[$index] = $user_key;
			unset($ws->game[$user_key]);
			send_match($user_key, [$user_key,$index,1], $ws);
			send_match($index, [$index,$user_key,2], $ws);
			break;
		}
	}else{
		$ws->game[$index] = NULL;
	}	
}

function send_match($index, $match, $ws){
	$res = array(
		'msg' => $match,
		'type' => 'match',
	);
	$res = json_encode($res);
	$res = $ws->frame($res);
	socket_write($ws->accept[$index], $res, strlen($res));
}

function close_callback($index, $ws) {
	if(isset($ws->game[$index])){
		if(is_null($ws->game[$index])){
			unset($ws->game[$index]);
		}else{
			$match = $ws->game[$index];
			unset($ws->game[$index]);
			$ws->game[$match] = NULL;
			if(isset($ws->accept[$match])){
				send_match_exit($match,$index,$ws);
			}
		}
	}else if(array_search($index, $ws->game) !== FALSE){
		$match = array_search($index, $ws->game);
		$ws->game[$match] = NULL;
		if(isset($ws->accept[$match])){
			send_match_exit($match,$index,$ws);
		}		
	}
}

function send_match_exit($match, $index, $ws){
	$res = array(
		'msg' => $index,
		'type' => 'close',
	);
	$res = json_encode($res);
	$res = $ws->frame($res);
	socket_write($ws->accept[$match], $res, strlen($res));
	user_add_callback($match,$ws);
}
function send_callback($data, $index, $ws) {
	send_to_match($data,'text', $index, $ws);
}
function send_to_match($data, $type, $index, $ws){	
	$res = array(
		'msg' => $data,
		'type' => $type,
		);
	$res = json_encode($res);
	$res = $ws->frame($res);

	if(isset($ws->game[$index])){
		$match = $ws->game[$index];
	}else{
		$match = array_search($index, $ws->game);
	}
	if(isset($ws->accept[$index]) && isset($ws->accept[$match])){
		socket_write($ws->accept[$index], $res, strlen($res));
		socket_write($ws->accept[$match], $res, strlen($res));
	}
}