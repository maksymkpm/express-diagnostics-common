<?php

class Reports {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}
	
	public static function getReport(int $member_id) {
		$query = "	SELECT * 
					FROM personal_results
					WHERE member_id = :member_id";

		$data = self::Database()
					->select($query)
					->binds('member_id', $member_id)
					->execute()
					->fetch_all();
		
		return $data;
	}
}