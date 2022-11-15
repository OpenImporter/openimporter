<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace Importers;

/**
 * The starting point for the third step of any importer.
 *
 * Nothing fancy, just one last step that, if needed/wanted, allows for example
 * to store somewhere destination-specific the name of the import script.
 *
 * @todo Is a whole step even necessary just for that?
 */
abstract class Step3BaseImporter extends BaseImporter
{
	abstract public function run($import_script);
}
