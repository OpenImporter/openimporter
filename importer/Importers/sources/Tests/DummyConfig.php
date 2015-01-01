<?php

class DummyConfig
{
	public function __call($name, $args)
	{
		return 'something';
	}
}