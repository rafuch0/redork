<?php

define('TABLE', 'redork');
define('DB', 'redork');
define('SERVER', 'localhost');

define('USER', 'redork-ro');
define('PASSWORD', 'redorkpassword-ro');

define('ROOTUSER', 'redork');
define('ROOTPASSWORD', 'redorkpassword');

define('DEBUG', false);
//error_reporting(-1);
//initdb();

function getHTTPHeaders($contenttype)
{
	header('content-type: '.$contenttype);

	header('HTTP/1.0 200 OK');
	header('Status: 200 OK');

	header('Last-Modified: Mon, 01 Jan 1970 12:00:00 GMT');
	header('Expires: Sun, 17 Jan 2038 19:14:07 GMT');
	header('ETag: '.md5($_SERVER['REQUEST_URI']));

	if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && isset($_SERVER['HTTP_IF_NONE_MATCH']))
	{
		header('HTTP/1.0 304 Not Modified');
		die();
	}
}

function initdb()
{
	$link = mysql_connect(SERVER, ROOTUSER, ROOTPASSWORD);
	if ($link) mysql_select_db(DB, $link);
	else die('Could not connect: '.mysql_error());

	$sqlQuery = 'DROP TABLE '.TABLE.';';
	if(DEBUG) echo $sqlQuery;
	mysql_query($sqlQuery, $link);

	$sqlQuery = 'CREATE TABLE '.TABLE.' (id BIGINT AUTO_INCREMENT, k VARBINARY(8), v VARCHAR(7680), PRIMARY KEY (id)) Engine=MyISAM;';
	if(DEBUG) echo $sqlQuery;
	$result = mysql_query($sqlQuery, $link);

	$sqlQuery = 'INSERT INTO '.TABLE.' (k, v) VALUES(\'a\',\'Pass a URL in your URL and get a hash.  Pass a hash in your URL and get a URL.\');';
	if(DEBUG) echo $sqlQuery;
	$result = mysql_query($sqlQuery, $link);

	mysql_close($link);
}

function keyExists($key, &$link)
{
	$key = mysql_real_escape_string($key, $link);
	$sqlQuery = 'SELECT v FROM '.TABLE.' WHERE k=\''.$key.'\' LIMIT 1;';
	if(DEBUG) echo $sqlQuery;
	$result = mysql_query($sqlQuery, $link);
	if(mysql_num_rows($result) == 1)
	{
		$row = mysql_fetch_assoc($result);
		return $row['v'];
	}

	return false;
}

function valueExists($value, &$link)
{
	$value = mysql_real_escape_string($value, $link);
	$sqlQuery = 'SELECT k FROM '.TABLE.' WHERE v=\''.$value.'\' LIMIT 1;';
	if(DEBUG) echo $sqlQuery;
	$result = mysql_query($sqlQuery, $link);
	if(mysql_num_rows($result) == 1)
	{
		$row = mysql_fetch_assoc($result);
		return $row['k'];
	}

	return false;
}


// 0-9 A-Z a-z
//function base62_encode($n){$s="";do{$t=$n%62;$s=(($t>9)?($t>35)?chr($t+61):chr($t+55):$t).$s;$n/=62;}while((int)$n/62>0);return $s;}
//function base62_decode($s){$n=0;for($i=0;$i<strlen($s);$i++){$t=ord($s{$i});$n=$n*62+($t>64?$t>96?$t-61:$t-55:$t-48);}return $n;}

// a-z A-Z 0-9
function base62_encode($n){$s="";do{$t=$n%62;$s=(($t>25)?($t>51)?$t-52:chr($t+39):chr($t+97)).$s;$n/=62;}while((int)$n/62>0);return $s;}
function base62_decode($s){$n=0;for($i=0;$i<strlen($s);$i++){$t=ord($s{$i});$n=$n*62+($t>64?$t>96?$t-97:$t-39:$t+4);}return $n;}

function setKey($value, &$link)
{
	$value = mysql_real_escape_string($value, $link);

	mysql_query('LOCK TABLES '.TABLE.' WRITE;', $link);

	$sqlQuery = 'SELECT k FROM '.TABLE.' ORDER BY id DESC LIMIT 1;';
	if(DEBUG) echo $sqlQuery;
	$result = mysql_query($sqlQuery, $link);
	$row = mysql_fetch_assoc($result);

	$key = $row['k'];

	$key = base62_decode($key);
	$key++;
	$key = base62_encode($key);

	$key = mysql_real_escape_string($key, $link);

	$sqlQuery = 'INSERT INTO '.TABLE.' (k, v) VALUES(\''.$key.'\', \''.$value.'\');';
	if(DEBUG) echo $sqlQuery;
	mysql_query($sqlQuery, $link);
	mysql_query('UNLOCK TABLES;', $link);

	return $key;
}

$subdir = strstr(ltrim($_SERVER['SCRIPT_NAME'], '/'), '/', true);
$uri = preg_replace('/^\/'.str_replace('/', '', $subdir).'\//', '', $_SERVER['REQUEST_URI']);

if(strlen($uri) > 7680)
{
	header('Content-Description: Content Too Big!');
	echo 'Content Too Big!';
}
else if((strlen($uri) > 8))
{
	getHTTPHeaders('text/plain');

	$link = mysql_connect(SERVER, ROOTUSER, ROOTPASSWORD);
	if ($link) mysql_select_db(DB, $link);
	else die('Could not connect: '.mysql_error());

	$value = $uri;
	$host = 'http://'.$_SERVER['HTTP_HOST'].'/'.$subdir.'/';

	$keyExists = valueExists($value, $link);

	if($keyExists == false)
	{
		$keyExists = $host.setKey($value, $link);
		mysql_close($link);
		header('Content-Description: '.$keyExists);
		echo $keyExists;
	}
	else
	{
		$keyExists = $host.$keyExists;
		header('Content-Description: '.$keyExists);
		echo $keyExists;
	}
}
else if(strlen($uri) > 0)
{
	$link = mysql_connect(SERVER, USER, PASSWORD);
	if ($link) mysql_select_db(DB, $link);
	else die('Could not connect: '.mysql_error());

	$key = $uri;
	$valueExists = keyExists($key, $link);
	mysql_close($link);

	if($valueExists)
	{
		header('Content-Description: '.$valueExists);
		if(preg_match('/^http(s?):\/\//', $valueExists))
		{
			header('Location: '.$valueExists);
		}
		else
		{
			getHTTPHeaders('text/plain');
			echo rawurldecode($valueExists);
		}
	}
	else
	{
		header('Content-Description: Key Does Not Exist!');
		echo 'Key Does Not Exist!';
	}
}
else
{
	header('Content-Description: Pass a URL in your URL and get a hash.  Pass a hash in your URL and get a URL');
	echo 'Pass a URL in your URL and get a hash.  Pass a hash in your URL and get a URL.';
}
