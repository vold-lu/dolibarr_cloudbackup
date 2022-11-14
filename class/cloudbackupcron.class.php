<?php

class CloudBackupCron
{
	/** @var DoliDB $db */
	private $db;
	/** @var string */
	public $error;
	/** @var string[] */
	public $errors;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @return int
	 */
	public function backupInstance()
	{
		return -1;
	}
}
