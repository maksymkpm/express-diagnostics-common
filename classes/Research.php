<?php
/**
 * Class Research
 */
class Research {
    public $data;

    /**
     * Research constructor.
     * @param array $data
     */
    private function __construct(array $data) {
		if (empty($data)) {
			throw new \RuntimeException('Incorrect researches data');
		}

		$this->data = $data;
	}
	
		public static function GetResearch(): ?Research {
		$query = "	SELECT * FROM papers
					WHERE online = :online";

		$researchData = self::Database()
					->select($query)
					->binds(':online', 1)
					->execute()
					->fetch_all();

		if (empty($researchData)) {
			return null;
		}

		return new self($researchData);
	}
	
    /**
     * @return db
     */
    public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');
		
		return $db;
	}
}