<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

/**
 * The class contains code that allows the Importer to obtain settings
 * from the ElkArte installation.
 */
class smf2_0_importer extends Importers\SmfCommonSource
{
	/**
	 * @var string
	 */
	public $attach_extension = 'dat';

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'SMF 2.0';
	}
}

class smf2_0_importer_step1 extends Importers\SmfCommonSourceStep1
{
}

class smf2_0_importer_step2 extends Importers\SmfCommonSourceStep2
{
	/**
	 * Repair any wrong number of personal messages
	 */
	public function substep0()
	{
		$to_prefix = $this->config->to_prefix;

	}	
}

class smf2_0_importer_step3 extends Importers\SmfCommonSourceStep3
{
}