<?php

class PHPBoost3 extends AbstractSourceImporter
{
	public function getName()
	{
		return 'PHPBoost3';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $boost_database, $boost_prefix;

		return '`' . $boost_database . '`.' . $boost_prefix;
	}

	public function getTableTest()
	{
		return 'member';
	}
}

/**
 * Utility functions
 */
function boost_replace_bbc($content)
{
	$content = preg_replace(
		array(
			'~<strong>~is',
			'~</strong>~is',
			'~<em>~is',
			'~</em>~is',
			'~<strike>~is',
			'~</strike>~is',
			'~\<h3(.+?)\>~is',
			'~</h3>~is',
			'~\<span stype="text-decoration: underline;">(.+?)</span>~is',
			'~\<div class="bb_block">(.+?)<\/div>~is',
			'~\[style=(.+?)\](.+?)\[\/style\]~is',
		),
		array(
			'[b]',
			'[/b]',
			'[i]',
			'[/i]',
			'[s]',
			'[/s]',
			'',
			'',
			'[u]%1[/u]',
			'%1',
			'%1',
		),
		trim($content)
	);

	return $content;
}