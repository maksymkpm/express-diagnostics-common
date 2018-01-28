<?php

class ValidationException extends Exception {
	/**
	 * Proposition of values to user
	 * @type array
	 */
	private $proposal = [];
	/**
	 * @var array
	 */
	private $messages = [];

	/**
	 * @param array $messages
	 * @param array $proposal
	 * @param \Exception $previous
	 */
	public function __construct(array $messages, array $proposal = null, Exception $previous = null) {
		if ($proposal !== null) {
			$this->setProposal($proposal);
		}

		$this->setMessages($messages);
		parent::__construct('Provided data are invalid', 0, $previous);
	}

	/**
	 * Propose variants to user
	 * @param array $proposal
	 *
	 * @return $this
	 */
	public function setProposal(array $proposal) {
		$this->proposal = $proposal;

		return $this;
	}

	/**
	 * Propose variant to user
	 * @return array
	 */
	public function getProposal(): array {
		return $this->proposal;
	}

	/**
	 * @param array $messages
	 *
	 * @return $this
	 */
	public function setMessages(array $messages) {
		$this->messages = $messages;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getMessages(): array {
		return $this->messages;
	}
}
