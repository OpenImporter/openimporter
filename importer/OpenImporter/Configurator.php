<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * The configurator is just a class holding the common configuration
 * info such as the paths (to/from), prefixes, etc.
 * Basically a getter/setter
 *
 * @property string $lang_dir
 * @property string $importers_dir
 * @property string $action
 * @property \OpenImporter\Importers\destinations\DestinationImporterInterface $destination
 * @property \OpenImporter\Importers\SourceImporterInterface $source
 * @property string $to_prefix
 * @property string $from_prefix
 * @property OpenImporter\Core\Configurator $progress
 */
class Configurator extends ValuesBag
{
	/**
	 * Data stored here will be saved in the $_SESSION array to allow pass them
	 * from page to page.
	 *
	 * @var mixed[]
	 */
	public $store = array();
}