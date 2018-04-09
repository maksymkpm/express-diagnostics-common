<?php

class Attempt {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	//get the last attempt with status
	public static function getLastAttempt(int $member_id) {
		$query = "	SELECT attempt, status, started
					FROM member_to_attempt
					WHERE member_id = :member_id
					ORDER BY started DESC LIMIT 1";

		$data = self::Database()
					->select($query)
					->binds('member_id', $member_id)
					->execute()
					->fetch();

		return $data;
	}

	//creates new attempt
	public static function createAttempt(int $member_id, int $attempt) {
		$data = [
			'member_id' => $member_id,
			'attempt' => $attempt,
			'status' => 'progress',
			'started' => \db::expression('UTC_TIMESTAMP()'),
		];

		return self::Database()->insert('member_to_attempt')
			->values($data)
			->execute();
	}

	//updates status of attempt
	public static function updateAttempt(int $member_id, int $attempt) {
		$result = self::Database()
			->update('member_to_attempt')
			->values([
				'status' => 'archived',
			])
			->where('member_id = :member_id AND attempt = :attempt')
			->binds('member_id', $member_id)
			->binds('attempt', $attempt)
			->execute();

		return $result;
	}

	//finish attempt
	public static function finishAttempt(int $member_id, int $attempt, $report) {
		$result = self::Database()
			->update('member_to_attempt')
			->values([
				'report' => $report,
				'status' => 'done',
				'finished' => \db::expression('UTC_TIMESTAMP()'),
			])
			->where('member_id = :member_id AND attempt = :attempt')
			->binds('member_id', $member_id)
			->binds('attempt', $attempt)
			->execute();

		return $result;
	}
}