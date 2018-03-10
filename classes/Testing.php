<?php

class Testing {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	public static function CountResults($paper_id, string $questions, string $answers) {
		switch ($paper_id) {
			case 1:
			case 2:
			case 4:
			case 7: $score = self::CalculateCommonPaper($paper_id, self::substrString($answers)); break;

			case 8: $score = self::CalculatePerception($paper_id, self::substrString($answers)); break;
			case 9: $score = self::CalculateAbstract($paper_id, self::substrString($answers)); break;
			case 10: $score = self::CalculateMemory($paper_id, self::substrString($answers)); break;

			case 3:
			case 5:
			case 6: $score = self::CalculateComplexPaper($paper_id, self::substrString($answers)); break;

			default: break;
		}

		return self::returnResults($paper_id, $score);
	}

	private static function CalculateCommonPaper($paper_id, string $answers) {
		$query = "	SELECT SUM(weight) AS score
					FROM variants
					WHERE variant_id IN (" . $answers . ")";

		$scoreData = self::Database()
					->select($query)
					->execute()
					->fetch();

		return self::CalculateScore($paper_id, $scoreData['score']);
	}

	private static function CalculateComplexPaper($paper_id, string $answers) {
		$group = [];
		$group_number = 0;
		$answer = explode(',', $answers);

		foreach ($answer as $key => $value) {
			foreach ((config::get('recommendation.' . $paper_id . '.groups')) as $group_id => $group_array) {
				if (in_array($key, $group_array)) {
					$group_number = $group_id;
				}
			}

			$group[$group_number][$key] = $value;
		}

		$score = [];
		for ($i = 1; $i <= config::get('recommendation.' . $paper_id . '.parts'); $i++) {
			$part = implode(',', $group[$i]);

			$query = "	SELECT SUM(weight) AS score
						FROM variants
						WHERE variant_id IN (" . $part . ")";

			$result = self::Database()
						->select($query)
						->execute()
						->fetch();

			$score[$i] = $result['score'];
		}

		return self::CalculateScore($paper_id, $score);
	}

	private static function CalculateAbstract($paper_id, string $answers) {
		$correct_answers = [21,6,3,29,4];

		$answers = explode(',', $answers);
		$score = 0;

		foreach ($answers as $key => $value) {
			if ($value == $correct_answers[$key]) {
				$score++;
			}
		}

		return self::CalculateScore($paper_id, $score);
	}

	private static function CalculateMemory($paper_id, string $answers) {
		$correct_answers = [23,34,53,72,48,36,85,17,16,84];

		$answers = array_unique(explode(',', $answers));
		$score = 0;

		foreach ($answers as $key => $value) {
			if (in_array($value, $correct_answers)) {
				$score++;
			}
		}

		return self::CalculateScore($paper_id, $score);
	}

	private static function CalculatePerception($paper_id, string $answers) {
		$score = 0;

		if ($answers < 5) {
			$score = 1;
		}

		if (($answers >= 5) && ($answers < 9.5)){
			$score = (9.5 - $answers)/4.5;
		}

		if ($answers == 9.5) {
			$score = 0;
		}

		if (($answers > 9.5) && ($answers < 10.5)) {
			$score = 0;
		}

		if ($answers == 10.5) {
			$score = 0;
		}

		if (($answers > 10.5) && ($answers <= 15)){
			$score = ($answers - 10.5)/4.5;
		}

		if ($answers > 15) {
			$score = 1;
		}

		return $score;
	}

	private static function CalculateScore($paper_id, $scoreRaw) {
		if (in_array($paper_id, self::simplePapers())) {
			$score = self::countScore($scoreRaw, config::get('recommendation.' . $paper_id . '.score_max'));
		} else if (in_array($paper_id, self::complexPapers())) {
			$score = [];

			for ($i = 1; $i <= config::get('recommendation.' . $paper_id . '.parts'); $i++) {
				if (($paper_id == 5) && (in_array($i, [3,4]))) {
					$score[$i] = self::countSpecialScore($scoreRaw[$i]);
				} else {
					$score[$i] = self::countScore($scoreRaw[$i], config::get('recommendation.' . $paper_id . '.' . $i . '.score_max'));
				}
			}
		}

		return $score;
	}

	private static function returnResults($paper_id, $score) {
		$summary = '';
		$settings = [];

		if (in_array($paper_id, self::simplePapers())) {
			if ($score == 0) {
				$summary = config::get('recommendation.' . $paper_id . '.perfect');
				$settings = ["good" => ":)", "middle" => "", "bad" => ""];
			}
			elseif (($score > 0) && ($score <= 0.33)) {
				$summary = config::get('recommendation.' . $paper_id . '.good');
				$settings = ["good" => ":)", "middle" => "", "bad" => ""];
			}
			elseif (($score > 0.33) && ($score <= 0.66)) {
				$summary = config::get('recommendation.' . $paper_id . '.middle');
				$settings = ["good" => "", "middle" => ":|", "bad" => ""];
			}
			elseif (($score > 0.66) && ($score <= 1)) {
				$summary = config::get('recommendation.' . $paper_id . '.bad');
				$settings = ["good" => "", "middle" => "", "bad" => ":("];
			}

		} else if (in_array($paper_id, self::complexPapers())) {
			$final_score = 0;

			for ($i = 1; $i <= config::get('recommendation.' . $paper_id . '.parts'); $i++) {
				if ($score[$i] == 0) {
					$summary .= config::get('recommendation.' . $paper_id . '.' . $i . '.perfect');
				}
				elseif (($score[$i] > 0) && ($score[$i] <= 0.33)) {
					$summary .= config::get('recommendation.' . $paper_id . '.' . $i . '.good');
				}
				elseif (($score[$i] > 0.33) && ($score[$i] <= 0.66)) {
					$summary .= config::get('recommendation.' . $paper_id . '.' . $i . '.middle');
				}
				elseif (($score[$i] > 0.66) && ($score[$i] <= 1)) {
					$summary .= config::get('recommendation.' . $paper_id . '.' . $i . '.bad');
				}

				$final_score = $final_score + $score[$i];
			}
//var_dump($score);
			$final_score = $final_score / config::get('recommendation.' . $paper_id . '.parts');
//var_dump($final_score);
			if ($final_score == 0) {
				$summary = config::get('recommendation.' . $paper_id . '.general.perfect') . $summary;
				$settings = ["good" => ":)", "middle" => "", "bad" => ""];
			}
			elseif (($final_score > 0) && ($final_score <= 0.33)) {
				$summary = config::get('recommendation.' . $paper_id . '.general.good') . $summary;
				$settings = ["good" => ":)", "middle" => "", "bad" => ""];
			}
			elseif (($final_score > 0.33) && ($final_score <= 0.66)) {
				$summary = config::get('recommendation.' . $paper_id . '.general.middle') . $summary;
				$settings = ["good" => "", "middle" => ":|", "bad" => ""];
			}
			elseif (($final_score > 0.66) && ($final_score <= 1)) {
				$summary = config::get('recommendation.' . $paper_id . '.general.bad') . $summary;
				$settings = ["good" => "", "middle" => "", "bad" => ":("];
			}

			$score = $final_score;

		}

		return self::returnSummary($score, $summary, $settings);
	}

	private static function substrString(string $string) {
		return substr($string, 0, -1);
	}

	private static function countScore($scoreRaw, $score_max) {
		if ($scoreRaw > $score_max) {
			$score = 0;
		} else {
			$score = ($score_max  - $scoreRaw) / $score_max ;
		}

		return $score;
	}

	private static function countSpecialScore($scoreRaw) {
		if ($scoreRaw <= 5) {
			$score = (5 - $scoreRaw)/5;
		}
		elseif (($scoreRaw > 5) && ($scoreRaw <= 7)) {
			$score = 0;
		}
		elseif (($scoreRaw > 7) && ($scoreRaw <= 12)){
			$score = ($scoreRaw - 7)/5;
		}

		return $score;
	}

	// тесты без вложенностей
	private static function simplePapers() {
		return [1,2,4,7,8,9,10,11];
	}

	// тесты с вложенностью
	private static function complexPapers() {
		return [3,5,6];
	}

	/*
	* returns summary to customer

	*/
	private static function returnSummary($score, $summary, $settings) {
		$replace = [
			'{score}' => $score,
			'{summary}' => $summary,
			'{good}' => $settings['good'],
			'{middle}' => $settings['middle'],
			'{bad}' => $settings['bad'],
		];

		$response = file_get_contents('../templates/test_result.html', true);

		foreach ($replace as $placeholder => $value) {
			$response = str_replace($placeholder, $value, $response);
		}

		return $response;
	}
}
