<?php

class Testing {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');
		
		return $db;
	}
	
	public static function Paper1(string $answers) {
		$query = "	SELECT SUM(weight) AS score
					FROM variants
					WHERE variant_id IN (:answers)";

		$scoreData = self::Database()
					->select($query)
					->binds(':answers', $answers)
					->execute()
					->fetch();
		
		$score = (16 - $scoreData['score']) / 16;
		
		return $score;
	}
}