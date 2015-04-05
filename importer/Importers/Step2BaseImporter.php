<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers;

/**
 * The starting point for the second step of any importer.
 * Step2 is usually used to recalculate statistics and "fix" any data that
 * may need adjustments.
 * This should only know about the destination and not about the source.
 */
abstract class Step2BaseImporter extends BaseImporter
{
}