<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class SMF1_1_Importer extends \OpenImporter\Importers\AbstractSourceSmfImporter
{
	protected $smf_attach_folders = null;

	public function getName()
	{
		return 'SMF1_1';
	}

	public function getVersion()
	{
		return '1.0';
	}

	protected function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . '/Settings.php');

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	public function getAttachmentDirs()
	{
		if ($this->smf_attach_folders === null)
		{
			$request = $this->db->query("
				SELECT value
				FROM {$this->config->from_prefix}settings
				WHERE variable='attachmentUploadDir';");
			list ($smf_attachments_dir) = $this->db->fetch_row($request);

			$this->smf_attach_folders = str_replace('\\', '/', $smf_attachments_dir);
		}

		return $this->smf_attach_folders;
	}

	public function getAttachDir()
	{
		return $this->smf_attach_folders;
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$ext = strtolower(substr(strrchr($row['filename'], '.'), 1));
			if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png', 'bmp')))
				$ext = '';

			$row['fileext'] = $ext;
			$row['mime_type'] = '';
			$row['id_folder'] = 0;
			$row['full_path'] = $this->getAttachDir();

			$rows[] = $row;
		}

		return $rows;
	}
}