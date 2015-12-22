<?php

require "vendor/papac/snoopframework/src/ApplicationAutoload.php";
\System\ApplicationAutoload::register();

use System\Database\DB;
use System\Support\Util;
use System\Support\Logger;
use System\Support\Resource;
use System\Support\Security;

$app = \System\Core\Application::loader(require "configuration/init.php");

global $response;
global $request;

$response = \System\Http\Response::load($app);
$request = \System\Http\Request::load($app);

DB::loadConfiguration(require "configuration/db.php");
Resource::configure(require "configuration/resource.php");


if (!function_exists("db")) {
	function db() {
		return DB::connection();
	}
}

if (!function_exists("view")) {
	function view($template, $data = []) {
		global $response;
		$response->view($template, $data);
	}
}

if (!function_exists("table")) {
	function table($tableName) {
		return DB::table($tableName);
	}
}

if (!function_exists("querymaker")) {
	function querymaker($sql, $data, $method) {

		$db = db();

		if (method_exists($db, $method)) {
			return $db::$method($sql, $data);
		}

		return null;
	}
}

if (!function_exists("lastinsertid")) {
	function lastinsertid() {

		$db = db();

		return $db::lastInsertId();
	}
}

if (!function_exists("queryresponse")) {
	function queryresponse($method, $param) {

		global $response;
		$param = array_slice(func_get_args(), 1);

		if (method_exists($response, $method)) {
			return call_user_func_array([$response, $method], $param);
		}

		return null;
	}
}

if (!function_exists("show_error")) {
	function show_error() {

		$db = db();

		return $db::getLastErreur();
	}
}

if (!function_exists("select")) {
	function select($sql, array $data = []) {
		return querymaker($sql, $data, "select");
	}
}

if (!function_exists("insert")) {
	function insert($sql, array $data = []) {
		return querymaker($sql, $data, "insert");
	}
}

if (!function_exists("delete")) {
	function delete($sql, array $data = []) {
		return querymaker($sql, $data, "delete");
	}
}

if (!function_exists("update")) {
	function update($sql, array $data = []) {
		return querymaker($sql, $data, "update");
	}
}

if (!function_exists("statement")) {
	function statement($sql, array $data = []) {
		return querymaker($sql, $data, "statement");
	}
}

if (!function_exists("kill")) {
	function kill($message = null, $log = false) {

		if ($log === true) {
			log($message, $log=0);
		}

		die($message);
	}
}

if (!function_exists("mailto")) {
	function mailto($to, $message, $header) {

	}
}

if (!function_exists("body")) {
	function body() {
        global $request;
        return $request->body();
	}
}

if (!function_exists("files")) {
	function files() {
        global $request;
        return $request->files();
	}
}

if (!function_exists("query")) {
	function query() {
        global $request;
        return $request->query();
	}
}

if (!function_exists("log")) {
	function log($message, $type) {
		Logger::run([/** chargement de la config */]);
		switch ($type) {
			case 1:
			case "err":
				Logger::error($message);
				break;
			case 2:
			case "warn":
				Logger::warning($message);
				break;
			case 3:
			case "info":
				Logger::info($message);
				break;
			default:
				Logger::log($message);
				break;
		}
	}
}

if (!function_exists("error")) {
	function error($message) {
		 log($message, $err=1);
	}
}

if (!function_exists("warn")) {
	function warn($message) {
		 log($message, $warn=2);
	}
}

if (!function_exists("info")) {
	function info($message) {
		 log($message, $info=3);
	}
}

if (!function_exists("debug")) {
	function debug() {
		Util::debug(func_get_args());
	}
}

if (!function_exists("c_csrf")) {
	function c_csrf() {
		return Security::generateTokenCsrf();
	}
}

if (!function_exists("store")) {
	function store(array $file, $filename = null, $dirname = null) {
		if (!is_null($filename) && is_string($filename)) {
			\System\Support\Resource::setUploadFileName($filename);
			if (!is_null($dirname)) {
				\System\Support\Resource::setUploadDir($dirname);
			}
		}
		\System\Support\Resource::uploadFile($file);
	}
}

if (!function_exists("json")) {
	function json($data) {
		queryresponse("json", $data);
	}
}

if (!function_exists("statuscode")) {
	function statuscode($code) {
		if (is_int($code)) {
			queryresponse("setCode", $code);
		}
	}
}

if (!function_exists("sanitaze")) {
	function sanitaze($data) {
		if (is_int($data)) {
			return $data;
		} else {
			return Security::sanitaze($data);
		}
	}
}

if (!function_exists("secure")) {
	function secure($data) {
		if (is_int($data)) {
			return $data;
		} else {
			return Security::sanitaze($data, true);
		}
	}
}

if (!function_exists("response")) {
	function response($template = null, $data = null, $code = 200) {
		if (is_null($template)) {
			global $response;
			return $response;
		}
		statuscode($code);
		return queryresponse("render", $template, $data);
	}
}

if (!function_exists("setheader")) {
	function setheader($key, $value) {
		queryresponse("setHeader", $key, $value);
	}
}

if (!function_exists("send")) {
	function send($data) {
		queryresponse("send", $data);
	}
}

if (!function_exists("query")) {
	function query($option) {
		return DB::query($option);
	}
}

if (!function_exists("switch_to")) {
	function switch_to($name, $cb = null) {
		DB::switchTo($name, $cb);
	}
}

if (!function_exists("curljson")) {
	function curljson($url) {

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = curl_exec($ch);
		curl_close($ch);

		setheader("content-type", "application/json; charset=utf-8");
		send($data);

	}
}