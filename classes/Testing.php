<?php

class Testing {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	public static function Paper1(string $answers) {
		$answers = substr($answers, 0, -1);
		
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

		return self::returnResults($score);
	}
	
	public static function scoreForSlider($score) {
		return ($score * 100);
	}
	
	public static function returnResults($score) {
		$summary = '';
		
		//заключение по координирующему комплексу
		if ($score == 0) {
			$summary = config::get('recommendation.1.perfect');
			$settings = ["good" => 1, "middle" => 0.3, "bad" => 0.3];
		}
		elseif (($score > 0) && ($score <= 0.33)) {
			$summary = config::get('recommendation.1.good');
			$settings = ["good" => 1, "middle" => 0.5, "bad" => 0.3];
		}
		elseif (($score > 0.33) && ($score <= 0.66)) {
			$summary = config::get('recommendation.1.middle');
			$settings = ["good" => 0.5, "middle" => 1, "bad" => 0.5];
		}
		elseif (($score > 0.66) && ($score <= 1)) {
			$summary = config::get('recommendation.1.bad');
			$settings = ["good" => 0.3, "middle" => 0.5, "bad" => 1];
		}
		
		return '
		<div>Индекс здоровья координирующего комплекса: ' . $score . '. </div>
		<div>Заключение: ' . $summary . '</div>
		<table width="100%">
			<tr>
				<td width="33%" style="color: greed; height: 30px; opacity: ' . $settings["good"] . '"></td>
				<td width="33%" style="color: yellow; height: 30px; opacity: ' . $settings["middle"] . '"></td>
				<td width="33%" style="color: red; height: 30px; opacity: ' . $settings["bad"] . '"></td>
			</tr>
		</table>
		<div>		
			<a href="index.php?page=research"><img src="images/another_tests.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;
			<a href="index.php?page=resume"><img src="images/to_reports.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;	
			<a href="index.php?page=results"><img src="images/to_results.jpg" width="140px" border="0" alt="" /></a>	
		</div>';
	}
}
