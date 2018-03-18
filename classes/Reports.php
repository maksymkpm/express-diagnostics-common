<?php

class Reports {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	public static function getReport(int $member_id, $attempt = 0) {
		if ($attempt == 0) {
			$attempt = $_SESSION['attempt'];
		}

		$query = "SELECT paper_id, paper_name, research_id FROM papers";

		$papers = self::Database()
					->select($query)
					->execute()
					->fetch_all();

		$query = "	SELECT member_id, research_id, paper_id, result_text, score, date, attempt
					FROM personal_results
					WHERE member_id = :member_id
						AND attempt = :attempt ORDER BY date ASC";

		$answers = self::Database()
					->select($query)
					->binds('member_id', $member_id)
					->binds('attempt', $attempt)
					->execute()
					->fetch_all();

		$data = [];
		foreach ($papers as $paper) {
			$data[$paper['paper_id']] = $paper;

			foreach ($answers as $answer) {

				if ($paper['paper_id'] == $answer['paper_id']) {
					$data[$paper['paper_id']]['member_id'] = $answer['member_id'];
					$data[$paper['paper_id']]['result_text'] = $answer['result_text'];
					$data[$paper['paper_id']]['score'] = $answer['score'];
					$data[$paper['paper_id']]['date'] = $answer['date'];
					$data[$paper['paper_id']]['attempt'] = $answer['attempt'];
					$data[$paper['paper_id']]['done'] = true;
				}
			}
		}

		return $data;
	}

	public static function leftTest(array $data) {
		$response = [
			'next' => 0,
			'counter' => 0,
		];

		foreach ($data as $paper) {
			if (!isset($paper['done'])) {
				$response['counter']++;

				if ($response['counter'] == 1) {
					$response['next'] = $paper['paper_id'];
				}
			}
		}

		return $response;
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

	public static function calculateIndexes(int $attempt = 0) {
		$data = self::getReport($_SESSION['member']['member_id'], $attempt);

		$results = [
			'research1_index' => '',
			'research2_index' => '',
			'research3_index' => '',
			'index' => '',
		];

		return $data;
	}
}
