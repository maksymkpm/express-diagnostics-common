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

	//personal results for results page
	public static function listPersonalResults(int $member_id) {
		$query = "	SELECT p.paper_name, pr.score, pr.result_text, pr.date, r.research_name, r.image
					FROM personal_results pr
					LEFT JOIN papers p ON p.paper_id = pr.paper_id
					LEFT JOIN researches r ON r.research_id = pr.research_id
					WHERE pr.member_id = :member_id
					ORDER BY pr.date DESC LIMIT 20";

		$data = self::Database()
					->select($query)
					->binds('member_id', $member_id)
					->execute()
					->fetch_all();

		return $data;
	}
}