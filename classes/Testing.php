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

			case 3: 
			case 5:
			case 6:
			$score = self::CalculateComplexPaper($paper_id, self::substrString($answers)); break;

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
		return [1,2,4,7];
	}

	// тесты с вложенностью
	private static function complexPapers() {
		return [3,5,6];
	}

	/*
	* returns summary to customer

	*/
	private static function returnSummary($score, $summary, $settings) {
		return '
			<div>Результаты тестирования: ' . $score . '. </div>
			<div>Заключение: ' . $summary . '</div>
			<table width="100%">
				<tr>
					<td width="100px" style="text-align: center; color: white; font-size: 36px; background-color: green; height: 30px;">' . $settings['good'] . '</td>
					<td width="100px" style="text-align: center; color: black; font-size: 36px; background-color: yellow; height: 30px;">' . $settings['middle'] . '</td>
					<td width="100px" style="text-align: center; color: white; font-size: 36px; background-color: red; height: 30px;">' . $settings['bad'] . '</td>
				</tr>
			</table>
			<div>
				<a href="index.php?page=research"><img src="images/another_tests.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;
				<a href="index.php?page=resume"><img src="images/to_reports.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;
				<a href="index.php?page=results"><img src="images/to_results.jpg" width="140px" border="0" alt="" /></a>
			</div>';
	}
}
