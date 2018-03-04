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
	
	public static function returnResults($score) {
		return '<table width="100%" cellpadding="0" cellspacing="0">	
			<tr>		
				<td style="vertical-align: top; padding: 10px">
					<span style="font-weight: bold; color: #181260">Индекс здоровья координирующего комплекса: .</span>
					<br/><span style="color: #ff8900; font-weight: bold">Заключение: </span>
				</td>
			</tr>	
			<tr>
				<td style="padding-bottom: 20px">
					<form method="get" name="demoForm">
						<input name="sliderValue" id="score" type="hidden" size="3" value="' . $score . '">
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
				</td>
			</tr>	
			<tr>		
				<td style="vertical-align: top; text-align: center; padding: 10px">			
					<a href="index.php?page=research"><img src="images/another_tests.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;
					<a href="index.php?page=resume"><img src="images/to_reports.jpg" width="140px" border="0" alt="" /></a>&nbsp;&nbsp;&nbsp;	
					<a href="index.php?page=results"><img src="images/to_results.jpg" width="140px" border="0" alt="" /></a>	
				</td>
			</tr>
		</table>';
	}
}