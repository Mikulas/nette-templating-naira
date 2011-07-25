<?php

/**
 * @author Mikuláš Dítě
 */

namespace Nette\Utils\Parser;


class Parser extends Object implements \ArrayAccess
{

	/** @var Node */
	protected $tree;

	/** @var int */
	protected $level = 0;



	public function __construct()
	{
		$this->tree = new Node(NULL);
	}



	public function up()
	{
		$this->level++;
		return $this;
	}



	public function down()
	{
		$this->level--;
		return $this;
	}



	public function add($value)
	{
		$this->tree[$this->level]->add($value);
	}



	/**
	 * @return array
	 */
	public function getTree()
	{
		return $this->tree;
	}



	/** implements ArrayAccess */

	public function offsetSet($offset, $value)
	{
		$this->tree[$offset] = $value;
	}



	public function offsetExists($offset)
	{
		return isset($this->tree[$offset]);
	}



	public function offsetUnset($offset)
	{
		throw new \LogicException('Cannot unset node.');
	}



	public function offsetGet($offset)
	{
		return $this->tree[$offset];
	}

}
