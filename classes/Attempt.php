<?php

class Attempt {
	public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');

		return $db;
	}

	//get the last attempt with status
	public static function getLastAttempt(int $member_id) {
		$query = "	SELECT attempt, status, date
					FROM member_to_attempt
					WHERE member_id = :member_id
					ORDER BY date DESC LIMIT 1";

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
			'date' => \db::expression('UTC_TIMESTAMP()'),
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
}