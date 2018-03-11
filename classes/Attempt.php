<?php

class Attempt {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	
}