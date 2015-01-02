<?php

class DummyConfig
{
	public function __call($name, $args)
	{
		return 'something';
	}

	public function __get($name)
	{
		return 'something';
	}
}