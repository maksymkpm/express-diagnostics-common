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

		return self::returnResults($score * 100);
	}
	
	public static function scoreForSlider($score) {
		return int ($score * 100);
	}
	
	public static function returnResults($score) {
		$summary = '';
		
		//заключение по координирующему комплексу
		if ($score == 0) {
			$summary = config::get('recommendation.1.perfect');
		}
		elseif (($score > 0) && ($score <= 0.33)) {
			$summary = config::get('recommendation.1.good');
		}
		elseif (($score > 0.33) && ($score <= 0.66)) {
			$summary = config::get('recommendation.1.middle');
		}
		elseif (($score > 0.66) && ($score <= 1)) {
			$summary = config::get('recommendation.1.bad');
		}
		
		return '
		<div>Индекс здоровья координирующего комплекса: ' . $score . '. </div>
		<div>Заключение: ' . $summary . '</div>
		<form method="get" name="demoForm">
			<input name="sliderValue" id="score" type="hidden" size="3" value="' . self::scoreForSlider($score) . '">
				<script language="JavaScript">
					var A_TPL = {
						"b_vertical" : false,
						"b_watch": true,
						"n_controlWidt": 555,
						"n_controlHeight": 16,
						"n_sliderWidth": 5,
						"n_sliderHeight": 25,
						"n_pathLeft" : 1,
						"n_pathTop" : 1,
						"n_pathLength" : 550,
						"s_imgControl": "images/blueh_bg.gif",
						"s_imgSlider": "images/blueh_sl.gif",
						"n_zIndex": 1
					}

					var A_INIT1 = {
						"s_form" : 0,
						"s_name": "score",
						"n_minValue" : 0,
						"n_maxValue" : 100,
						"n_value" : 0,
						"n_step" : 1
					}
					new slider(A_INIT1, A_TPL);
				</script>
			</form>
			<div>		
				<a href="index.php?page=research"><img src="images/another_tests.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;
				<a href="index.php?page=resume"><img src="images/to_reports.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;	
				<a href="index.php?page=results"><img src="images/to_results.jpg" width="140px" border="0" alt="" /></a>	
			</div>';
	}
}
s