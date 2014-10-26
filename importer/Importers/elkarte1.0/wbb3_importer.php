<?php

class wbb3_1 extends AbstractSourceImporter
{
	protected $setting_file = '/wc/config.inc.php';

	public function getName()
	{
		return 'Woltlab Burning Board 3.1';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $dbName;

		return '`' . $dbName . '`.';
	}

	// @todo why $wbb_prefix is not in getPrefix?
	public function getTableTest()
	{
		global $wbb_prefix;

		return $wbb_prefix . 'user';
	}
}

/**
 * Utility functions
 */
function wbb_replace_bbc($message)
{
	$message = preg_replace(
		array(
			'~\[size=(.+?)\]~is',
			'~\[align=left\](.+?)\[\/align\]~is',
			'~\[align=right\](.+?)\[\/align\]~is',
			'~\[align=center\](.+?)\[\/align\]~is',
			'~\[align=justify\](.+?)\[\/align\]~is',
			'~.Geneva, Arial, Helvetica, sans-serif.~is',
			'~.Tahoma, Arial, Helvetica, sans-serif.~is',
			'~.Arial, Helvetica, sans-serif.~is',
			'~.Chicago, Impact, Compacta, sans-serif.~is',
			'~.Comic Sans MS, sans-serif.~is',
			'~.Courier New, Courier, mono.~is',
			'~.Georgia, Times New Roman, Times, serif.~is',
			'~.Helvetica, Verdana, sans-serif.~is',
			'~.Impact, Compacta, Chicago, sans-serif.~is',
			'~.Lucida Sans, Monaco, Geneva, sans-serif.~is',
			'~.Times New Roman, Times, Georgia, serif.~is',
			'~.Trebuchet MS, Arial, sans-serif.~is',
			'~.Verdana, Helvetica, sans-serif.~is',
			'~\[list=1\]\[\*\]~is',
			'~\[list\]\[\*\]~is',
			'~\[\*\]~is',
			'~\[\/list\]~is',
			'~\[attach\](.+?)\[\/attach\]~is'
		),
		array(
			'[size=$1pt]',
			'[left]$1[/left]',
			'[right]$1[/right]',
			'[center]$1[/center]',
			'$1',
			'Geneva',
			'Tahoma',
			'Arial',
			'Chicago',
			'Comic Sans MS',
			'Courier New',
			'Georgia',
			'Helvetica',
			'Impact',
			'Lucida Sans',
			'Times New Roman',
			'Trebuchet MS',
			'Verdana',
			'[list type=decimal][li]',
			'[list][li]',
			'[/li][li]',
			'[/li][/list]',
			'',
		),
		trim($message)
	);
	return $message;
}