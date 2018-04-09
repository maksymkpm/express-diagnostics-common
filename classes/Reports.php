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

	public static function calculateIndexes(int $attempt = 0) {
		$data = self::checkReport($_SESSION['member']['member_id'], $attempt);

		$results['index'] = [];
		$results['physical_status'] = self::calculatePhysicalStatus($data);
		$results['psychical_status'] = self::calculatePsychicalStatus($data);
		$results['social_status'] = self::calculateSocialStatus($data);

		$results['index'] = self::calculateIndexStatus($results);

		if ($results['index'] == null) {
			return $data;
		}

		return new self($results);
	}

	public static function checkReport(int $member_id, $attempt = 0) {
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

	private static function calculatePhysicalStatus(array $data) {
		$physicalStatus = [];

		if ((isset($data[1]['done']) && $data[1]['done'] == true) && (isset($data[2]['done']) && $data[2]['done'] == true)) {
			$score = 0.6 * $data[1]['score'] + 0.4 * $data[2]['score'];
			$result = self::prepareResult('physical_status', $score);

			$physicalStatus['general'] = [
				'score' => $score,
				'result_text' => $result['summary'],
			];

			$physicalStatus[1] = [
				'score' => $data[1]['score'],
				'result_text' => $data[1]['result_text'],
			];

			$physicalStatus[2] = [
				'score' => $data[2]['score'],
				'result_text' => $data[2]['result_text'],
			];
		}

		return $physicalStatus;
	}

	private static function calculateSocialStatus(array $data) {
		$socialStatus = [];

		if ((isset($data[3]['done']) && $data[3]['done'] == true) && (isset($data[4]['done']) && $data[4]['done'] == true)) {
			$score = 0.5 * $data[3]['score'] + 0.5 * $data[4]['score'];
			$result = self::prepareResult('social_status', $score);

			$socialStatus['general'] = [
				'score' => $score,
				'result_text' => $result['summary'],
			];

			$socialStatus[3] = [
				'score' => $data[3]['score'],
				'result_text' => $data[3]['result_text'],
			];

			$socialStatus[4] = [
				'score' => $data[4]['score'],
				'result_text' => $data[4]['result_text'],
			];
		}

		return $socialStatus;
	}

	private static function calculatePsychicalStatus(array $data) {
		$psychicalStatus = [
			'general' => []
		];

		//thinking
		if (isset($data[7]['done']) && isset($data[9]['done']) ) {
			$score = 0.5 * $data[7]['score'] + 0.5 * $data[9]['score'];
			$result = self::prepareResult('psychical_status.thinking', $score);

			$psychicalStatus['thinking'] = [
				'score' => $score,
				'result_text' => $result['summary'],
			];

			$psychicalStatus[7] = [
				'score' => $data[7]['score'],
				'result_text' => $data[7]['result_text'],
			];

			$psychicalStatus[9] = [
				'score' => $data[9]['score'],
				'result_text' => $data[9]['result_text'],
			];
		}

		//intellect
		if (isset($data[8]['done']) && isset($data[10]['done']) && isset($data[11]['done']) && !empty($psychicalStatus['thinking'])) {
			$score = 0.25 * $data[8]['score'] + 0.25 * $data[10]['score'] + 0.25 * $data[11]['score'] + 0.25 * $psychicalStatus['thinking']['score'];
			$result = self::prepareResult('psychical_status.intellect', $score);

			$psychicalStatus['intellect'] = [
				'score' => $score,
				'result_text' => $result['summary'],
			];

			$psychicalStatus[8] = [
				'score' => $data[8]['score'],
				'result_text' => $data[8]['result_text'],
			];

			$psychicalStatus[10] = [
				'score' => $data[10]['score'],
				'result_text' => $data[10]['result_text'],
			];

			$psychicalStatus[11] = [
				'score' => $data[11]['score'],
				'result_text' => $data[11]['result_text'],
			];
		}

		//psychicalStatus - emotional + character + intellect
		if (isset($data[5]['done']) && isset($data[6]['done']) && !empty($psychicalStatus['intellect'])) {
			$score = ($data[5]['score'] + $data[5]['score'] + $psychicalStatus['intellect']['score']) / 3;
			$result = self::prepareResult('psychical_status.psychical_status', $score);

			$psychicalStatus[5] = [
				'score' => $data[5]['score'],
				'result_text' => $data[5]['result_text'],
			];

			$psychicalStatus[6] = [
				'score' => $data[6]['score'],
				'result_text' => $data[6]['result_text'],
			];

			$psychicalStatus['general'] = [
				'score' => $score,
				'result_text' => $result['summary'],
			];
		}

		return $psychicalStatus;
	}

	private static function calculateIndexStatus(array $data) {
		$indexStatus = null;

		if (isset($data['physical_status']['general']) && isset($data['psychical_status']['general']) && isset($data['social_status']['general']) &&
			($data['physical_status']['general'] != null) && ($data['psychical_status']['general'] != null) && ($data['social_status']['general'] != null)) {
			$score = ($data['physical_status']['general']['score'] + $data['psychical_status']['general']['score'] + $data['social_status']['general']['score']) / 3;
			$result = self::prepareResult('index', $score);

			$indexStatus['general'] = [
				'score' => $score ,
				'result_text' => $result['summary'],
				'settings' => $result['settings'],
			];
		}

		return $indexStatus;
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
