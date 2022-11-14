<?php

require_once DOL_DOCUMENT_ROOT . '/core/class/utils.class.php';

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
		global $conf, $dolibarr_main_db_name;

		// Create backup of the database
		$utils = new Utils($this->db);
		if ($utils->dumpDatabase('gz') < 0) {
			$this->error = $utils->output;
			return -1;
		}

		// Create backup of documents folder (database backup will be included)
		$ret = $this->dumpDocuments($utils, $conf, $dolibarr_main_db_name);
		if (is_integer($ret)) {
			return -1;
		}

		return 0;
	}

	/**
	 * @param Utils $utils
	 * @param Conf $conf
	 * @param string $db_name
	 * @return int|string
	 */
	private function dumpDocuments($utils, $conf, $db_name)
	{
		// Create backup of the document folder (the database is inside documents folder so will be backup too)
		$archiveName = 'documents_' . $db_name . '_' . dol_sanitizeFileName(DOL_VERSION) . '_' . strftime("%Y%m%d%H%M") . '.tar.gz';
		$archivePath = $conf->admin->dir_output . '/documents/' . $archiveName;

		// We also exclude '/temp/' dir and 'documents/admin/documents'
		$cmd = "tar -czf " . $archivePath . " --exclude-vcs --exclude 'temp' --exclude 'dolibarr.log' --exclude 'dolibarr_*.log' --exclude 'documents/admin/documents' -C " . dirname(DOL_DATA_ROOT) . " " . basename(DOL_DATA_ROOT);

		$result = $utils->executeCLI($cmd, $conf->admin->dir_temp . '/out.tmp');

		if ($result['result'] || !empty($retval)) {
			$this->error = $utils->output;
			unlink($archivePath);
			return -1;
		}

		return $archivePath;
	}
}
