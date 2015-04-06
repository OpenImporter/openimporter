<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\destinations;

/**
 * This is the interface that any Destination class should implement.
 */
interface DestinationImporterInterface
{
	public function setUtils($db, $config);

	public function getName();

	public function getDestinationURL($path);

	public function getFormFields($path_to = '', $scriptname = '');

	public function verifyDbPass($pwd_to_verify);

	public function dbConnectionData();

	public function getDbPrefix();

	public function checkSettingsPath($path);
}