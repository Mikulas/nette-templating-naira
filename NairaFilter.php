<?php

/**
 * @author Mikuláš Dítě
 */

namespace Nette\Templating\Filters;

use Nette\Object;
use Nette\Utils\Strings as String;
use Nette\Utils\Html as Html;
use Nette\Utils\Tokenizer;


class Naira extends Object
{

	/** @var string */
	protected $template;

	/** @var parser */
	protected $parser;

	/** @var \Nette\Utils\Html */
	protected $defaultContainer;

	/** @var array of string => regex snippet */
	protected static $patterns = array(
		'string' => '\'[^\'\n]*\'|"(?:\\\\.|[^"\\\\\n])*"',
		'tag-inline' => '<(?:[!][a-z0-9]|img|hr|br|input|meta|area|embed|keygen|source|base|col|link|param|basefont|frame|isindex|wbr|command)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'tag-close' => '</(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'tag-open' => '<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'indent' => '\n[\t ]*',
		'whitespace' => '?:[\t ]+',
		'text' => '[^<]+',
	);



	public function __construct()
	{
		$this->defaultContainer = Html::el('div');
	}



	/**
	 * @param string $template
	 * @throws Nette\Latte\Filters\NairaException
	 */
	public function __invoke($template)
	{
		return $this->parse($template);
	}



	/**
	 * @param string $template
	 * @throws Nette\Latte\Filters\NairaException
	 * @return string filtered template
	 */
	public function parse($template)
	{
		if (trim($template) === '') {
			return $template;
		}

		$this->template = $template;
		$this->buildTree();

		return $this->toHtml();
	}



	/**
	 * @throws Nette\Latte\Filters\NairaException
	 * @return array
	 */
	protected function buildTree()
	{
		$t = new Tokenizer(self::$patterns, 'mi');
		$res = $t->tokenize($this->template);

		$this->parser = new NairaParser();

		foreach ($res as $node) {
			switch ($node['type']) {
				case 'tag-open':
					$this->parser->add($this->processTag($node['value']));
					$this->parser->up();
					break;

				case 'tag-close':
					$this->parser->add($this->parser->getCloseTag());
					$this->parser->down();
					break;

				default:
					$this->parser->add($node['value']);
					break;
			}
		}
	}



	/**
	 * @param string $tag
	 * @return string html
	 */
	protected function processTag($tag)
	{
		$original_id = String::match($tag, '~id=["\']?(?P<id>.*)["\']?~im');
		$tag = String::replace($tag, '~id=["\']?(?P<id>.*)["\']?~im');
		$match = String::match($tag, '~^<(?P<tag>[a-z0-9]*)(?P<meta>[#a-z0-9_.-]*)(?P<attr1>[^>]*)(?:class=["\']?(?P<class>.*)["\']?)?(?P<attr2>[^>]*)>$~im');
		$classes = String::matchAll($match['meta'], '~\.[a-z0-9_-]+~im');
		$id = String::match($match['meta'], '~#[a-z0-9_-]+~im');

		$tag = $match['tag'] ?: 'div';

		$c = array();
		foreach ($classes as $class) {
			$c[] = substr($class[0], 1);
		}
		if (trim($match['class']) !== '') {
			$c[] = $match['class'];
		}
		$class = implode(' ', $c);

		$id = $id ? substr($id[0], 1) : $original_id;

		$attr = trim("$match[attr1] $match[attr2]");

		$html = "<$tag" . ($id ? " id=\"$id\"" : '') . ($c ? " class=\"$class\"" : '') . ($attr ? " $attr" : '') . ">";
		return $html;
	}



	/**
	 * @return string html
	 */
	public function toHtml()
	{
		return $this->parser->tree->toHtml();
	}



	/**
	 * @return string html
	 */
	public function __toString()
	{
		return $this->parser->tree->toHtml();
	}

}



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



class NairaParser extends Parser
{

	public function getCloseTag()
	{
		$tag = $this->tree[$this->level]->value;
		$match = String::match($tag, '~^<(?P<name>[a-z0-9]+)(>|[ \t\n])~i');
		return '</' . $match['name'] . '>';
	}

}



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

		foreach (array_reverse($this->children) as $node) {
			return $node[$offset - 1];
		}

		throw new \Exception('not found'); // todo improve
	}

}

class NairaException extends \Nette\Templating\FilterException {}
