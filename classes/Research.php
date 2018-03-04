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
		$query = "	SELECT research_id, paper_id, paper_name FROM papers
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
	
	public static function GetPaper(int $paper_id): ?Research {
		$query = "	SELECT q.question_id, q.question, v.variant_id, v.variant_text 
					FROM `questions` q
					LEFT JOIN variants v ON q.question_id = v.question_id 
					WHERE q.paper_id = :paper_id
					ORDER BY q.question_id ASC";

		$questionsData = self::Database()
					->select($query)
					->binds(':paper_id', $paper_id)
					->execute()
					->fetch_all();
		
		$query = "	SELECT p.paper_name, p.paper_description, r.image
					FROM `papers` p
					LEFT JOIN researches r ON r.research_id = p.research_id
					WHERE p.paper_id = :paper_id";
		
		$paperData = self::Database()
					->select($query)
					->binds(':paper_id', $paper_id)
					->execute()
					->fetch();
		
		$researchData = [];
		$researchData['paper'] = $paperData;
		$researchData['general'] = [];
		
		$i = 0;
		foreach ($questionsData as $question) {
			$i++;
			
			if ($i == 1) {
				$researchData['general']['paper_start_question'] = $question['question_id'];
			}
			
			$researchData['questions'][$question['question_id']] = [
				'question' => $question['question'],
				'answers' => [],
			];
		}

		foreach ($questionsData as $variant) {
			$researchData['questions'][$variant['question_id']]['answers'][$variant['variant_id']] = $variant['variant_text'];
		}
		
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