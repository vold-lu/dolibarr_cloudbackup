<?php

require_once DOL_DOCUMENT_ROOT . '/core/class/utils.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/cloudbackup/vendor/autoload.php';

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

		// Validate required configuration is present
		$config = ['CLOUDBACKUP_S3_ENDPOINT', 'CLOUDBACKUP_S3_REGION', 'CLOUDBACKUP_S3_ACCESS_KEY', 'CLOUDBACKUP_S3_SECRET_KEY', 'CLOUDBACKUP_S3_BUCKET'];
		foreach ($config as $key) {
			if (empty($conf->global->$key)) {
				$this->error = sprintf('Configuration %s is required.', $key);
				return -1;
			}
		}

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

		// Finally, upload the document on the S3 bucket
		$s3 = new Aws\S3\S3Client([
			'endpoint' => $conf->global->CLOUDBACKUP_S3_ENDPOINT,
			'bucket_endpoint' => true,
			'region' => $conf->global->CLOUDBACKUP_S3_REGION,
			'version' => 'latest',
			'credentials' => [
				'key' => $conf->global->CLOUDBACKUP_S3_ACCESS_KEY,
				'secret' => $conf->global->CLOUDBACKUP_S3_SECRET_KEY,
			]
		]);

		$result = $s3->putObject([
			'Bucket' => $conf->global->CLOUDBACKUP_S3_BUCKET,
			'Key' => basename($ret),
			'SourceFile' => $ret,
		]);

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
