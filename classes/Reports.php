<?php

class Reports {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}
	
	public $data = [];
	
	private function __construct(array $data) {
		if (empty($data)) {
			throw new \RuntimeException('Incorrect data');
		}

		$this->data = $data;
	}

	public static function getReport(int $member_id, $attempt = 0) {
		if ($attempt == 0) {
			$attempt = $_SESSION['attempt'];
		}

		$query = "SELECT paper_id, paper_name, research_id FROM papers ORDER BY paper_id ASC";

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

		$physical = self::calculatePhysicalStatus($data);
		$psychical = self::calculatePsychicalStatus($data);
		$social = self::calculateSocialStatus($data);

		$results = [
			'social_status' => $social,
			'psychical_status' => $psychical,
			'physical_status' => $physical,
		];

		$results['index'] = self::calculateIndexStatus($results);

		if ($results['index'] == null) {
			return $data;
		}
		
		return self::returnResolution($results);
	}

	private static function calculatePhysicalStatus(array $data) {
		$physicalStatus = null;

		if (($data[1]['done'] == true) && ($data[2]['done'] == true)) {
			$physicalStatus = 0.6 * $data[1]['score'] + 0.4 * $data[2]['score'];
		}

		return $physicalStatus;
	}

	private static function calculateSocialStatus(array $data) {
		$socialStatus = null;

		if (($data[3]['done'] == true) && ($data[4]['done'] == true)) {
			$socialStatus = 0.5 * $data[1]['score'] + 0.5 * $data[2]['score'];
		}

		return $socialStatus;
	}

	private static function calculatePsychicalStatus(array $data) {
		$psychicalStatus = null;
		$intellect = null;
		$thinking = null;

		//thinking
		if (isset($data[7]['done']) && isset($data[9]['done']) ) {
			$thinking = 0.5 * $data[7]['score'] + 0.5 * $data[7]['score'];
		}

		//intellect
		if (isset($data[8]['done']) && isset($data[10]['done']) && isset($data[11]['done']) && $thinking != null) {
			$intellect = 0.25 * $data[8]['score'] + 0.25 * $data[10]['score'] + 0.25 * $data[11]['score'] + 0.25 * $thinking;
		}

		//psychicalStatus - emotional + character + intellect
		if (isset($data[5]['done']) && isset($data[6]['done']) && $intellect != null) {
			$psychicalStatus = ($data[5]['score'] + $data[5]['score'] + $intellect) / 3;
		}

		return [
			'thinking' => $thinking,
			'intellect' => $intellect,
			'psychical_status' => $psychicalStatus
		];
	}

	private static function calculateIndexStatus(array $data) {
		$indexStatus = null;

		if (($data['physical_status'] != null) && ($data['psychical_status'] != null) && ($data['social_status'] != null)) {
			$indexStatus = ($data['physical_status'] + $data['psychical_status']['psychical_status'] + $data['social_status']) / 3;
		}

		return $indexStatus;
	}
	
	private static function returnResolution($array) {
		$data = [];
		
		foreach ($array as $part => $score) {
			if ($part == 'psychical_status') {
				foreach ($score as $part => $score2) {
					$data['psychical_status'][$part] = self::prepareResult('psychical_status.' . $part, $score2);
				}
			} else {
				$data[$part] = self::prepareResult($part, $score);
			}			
		}
		
		return new self($data);
	}
	
	private function setData($part, $data) {
		$this->data[$part] = $data;
	}
	
	private static function prepareResult($part, $score) {
		$summary = '';
		$settings = [];
		
		if ($score == 0) {
			$summary = config::get('recommendation.' . $part . '.perfect');
			$settings = ["good" => ":)", "middle" => "", "bad" => ""];
		}
		elseif (($score > 0) && ($score <= 0.33)) {
			$summary = config::get('recommendation.' . $part . '.good');
			$settings = ["good" => ":)", "middle" => "", "bad" => ""];
		}
		elseif (($score > 0.33) && ($score <= 0.66)) {
			$summary = config::get('recommendation.' . $part . '.middle');
			$settings = ["good" => "", "middle" => ":|", "bad" => ""];
		}
		elseif (($score > 0.66) && ($score <= 1)) {
			$summary = config::get('recommendation.' . $part . '.bad');
			$settings = ["good" => "", "middle" => "", "bad" => ":("];
		}
		
		return [
			'summary' => $summary,
			'settings' => $settings,
			'score' => $score,
		];
	}
}
