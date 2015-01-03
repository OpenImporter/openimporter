<?php

class DummyConfig
{
	public function __call($name, $args)
	{
		return 'something';
	}

	public function __get($name)
	{
		if ($name == 'config' || $name == 'destination' || $name == 'source')
			return new DummyConfig();
		if ($name == 'path_from' || $name == 'path_to')
			return '';

		return 'something';
	}
}