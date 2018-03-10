<?php

class Testing {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	public static function CountResults($paper_id, string $questions, string $answers) {
		switch ($paper_id) {
			case 1: $score = self::CalculatePaper1(self::substrString($answers)); break;
			
			default: break;
		}

		return self::returnResults($paper_id, $score);
	}

	private static function returnResults($paper_id, $score) {
		$summary = '';

		if ($score == 0) {
			$summary = config::get('recommendation.1.perfect');
			$settings = ["good" => ":)", "middle" => "", "bad" => ""];
		}
		elseif (($score > 0) && ($score <= 0.33)) {
			$summary = config::get('recommendation.1.good');
			$settings = ["good" => ":)", "middle" => "", "bad" => ""];
		}
		elseif (($score > 0.33) && ($score <= 0.66)) {
			$summary = config::get('recommendation.1.middle');
			$settings = ["good" => "", "middle" => ":|", "bad" => ""];
		}
		elseif (($score > 0.66) && ($score <= 1)) {
			$summary = config::get('recommendation.1.bad');
			$settings = ["good" => "", "middle" => "", "bad" => ":("];
		}

		return self::returnSummary($score, $summary, $settings);
	}

	private static function CalculatePaper1(string $answers) {
		$query = "	SELECT SUM(weight) AS score
					FROM variants
					WHERE variant_id IN (" . $answers . ")";

		$scoreData = self::Database()
					->select($query)
					->execute()
					->fetch();

		if ($scoreData['score'] > 16) {
			$score = 0;
		} else {
			$score = (16 - $scoreData['score']) / 16;
		}

		return $score;
	}

	private static function substrString(string $string) {
		return substr($string, 0, -1);
	}

	/*
	* returns summary to customer

	*/
	private static function returnSummary($score, $summary, $settings) {
		return '
			<div>Индекс здоровья координирующего комплекса: ' . $score . '. </div>
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
