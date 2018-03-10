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

	private static function CalculateScore($paper_id, $scoreRaw) {
		switch ($paper_id) {
			case 1: $score_max = 16; break;
			case 2: $score_max = 13; break;
			case 4: $score_max = 35; break;
			case 7: $score_max = 7; break;
		}

		if ($scoreRaw > $score_max ) {
			$score = 0;
		} else {
			$score = ($score_max  - $scoreRaw) / $score_max ;
		}

		return $score;
	}

	private static function returnResults($paper_id, $score) {
		$summary = '';

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

		return self::returnSummary($score, $summary, $settings);
	}

	private static function substrString(string $string) {
		return substr($string, 0, -1);
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
