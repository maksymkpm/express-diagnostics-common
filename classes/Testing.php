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

		$answers = substr($answers, 0, -1);

		$scoreData = self::Database()
					->select($query)
					->binds(':answers', $answers)
					->execute()
					->fetch();
		
		if ($scoreData['score'] > 16) {
			$score = 0;
		} else {
			$score = (16 - $scoreData['score']) / 16;
		}

		return $score;
	}
}