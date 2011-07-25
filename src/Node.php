<?php

/**
 * @author Mikuláš Dítě
 */

namespace Nette\Utils\Parser;

use Nette\Object;


class Node extends Object implements \ArrayAccess
{

	/** @var Node */
	public $parent;

	/** @var string */
	public $value;

	/** @var array of Node */
	public $children;



	/**
	 * @param string $value
	 * @param Node $parent
	 */
	public function __construct($value, Node & $parent = NULL)
	{
		$this->value = $value;
		$this->parent = $parent === NULL ? $this : $parent;
	}



	public function addNode(Node $child)
	{
		$this->children[] = $child;
		return $this;
	}



	public function add($value)
	{
		$this->addNode(new Node($value, & $this));
	}



	public function getLast()
	{
		return end($this->children);
	}



	/**
	 * @return string html
	 */
	public function toHtml()
	{
		$html = '';

		$html .= $this->value;
		$this->children = $this->children ?: array();
		foreach ($this->children as $node) {
			$html .= $node->toHtml();
		}

		return $html;
	}



	/**
	 * @return string html
	 */
	public function __toString()
	{
		return $this->toHtml();
	}



	/** implements ArrayAccess */

	public function offsetSet($offset, $value)
	{
		$this[$offset] = $value;
	}



	public function offsetExists($offset)
	{
		if ($offset === 0) {
			return TRUE;
		}

		$found = FALSE;
		foreach ($this->children as $node) {
			$found = isset($node[$offset - 1]);
			if ($found) break;
		}

		return $found;
	}



	public function offsetUnset($offset)
	{
		throw new \LogicException('Cannot unset node.');
	}



	public function offsetGet($offset)
	{
		if ($offset === 0) {
			return $this;
		}

		if ($this->children === NULL) {
			throw new \Nette\Templating\Filters\NairaException("Unmatched closing tag.");
		}

		foreach (array_reverse($this->children) as $node) {
			return $node[$offset - 1];
		}

		throw new \Nette\Templating\Filters\NairaException("Node not found.");
	}

}
