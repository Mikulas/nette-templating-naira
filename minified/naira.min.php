<?php


/**
 * @author Mikuláš Dítě
 */

namespace Nette\Utils\Parser {

use Nette\Object;

class Node extends Object implements \ArrayAccess
{

	public $parent;

	public $value;

	public $children;

	function __construct($value, Node & $parent = NULL)
	{
		$this->value = $value;
		$this->parent = $parent === NULL ? $this : $parent;
	}

	function addNode(Node $child)
	{
		$this->children[] = $child;
		return $this;
	}

	function add($value)
	{
		$node = new Node($value, $this);
		$this->addNode($node);
	}

	function getLast()
	{
		return end($this->children);
	}

	function toHtml()
	{
		$html = '';

		$html .= $this->value;
		$this->children = $this->children ?: array();
		foreach ($this->children as $node) {
			$html .= $node->toHtml();
		}

		return $html;
	}

	function __toString()
	{
		return $this->toHtml();
	}

	function offsetSet($offset, $value)
	{
		$this[$offset] = $value;
	}

	function offsetExists($offset)
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

	function offsetUnset($offset)
	{
		throw new \LogicException('Cannot unset node.');
	}

	function offsetGet($offset)
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

class Parser extends Object implements \ArrayAccess
{

	protected $tree;

	protected $level = 0;

	function __construct()
	{
		$this->tree = new Node(NULL);
	}

	function up()
	{
		$this->level++;
		return $this;
	}

	function down()
	{
		$this->level--;
		return $this;
	}

	function add($value)
	{
		$this->tree[$this->level]->add($value);
	}

	function getTree()
	{
		return $this->tree;
	}

	function offsetSet($offset, $value)
	{
		$this->tree[$offset] = $value;
	}

	function offsetExists($offset)
	{
		return isset($this->tree[$offset]);
	}

	function offsetUnset($offset)
	{
		throw new \LogicException('Cannot unset node.');
	}

	function offsetGet($offset)
	{
		return $this->tree[$offset];
	}

}

}

namespace Nette\Templating\Filters {

use Nette\Object;
use Nette\Utils\Strings as String;
use Nette\Utils\Html as Html;
use Nette\Utils\Tokenizer;
use Nette\Utils\Parser\Parser;

class Naira extends Object
{

	protected $template;

	protected $parser;

	protected $defaultContainer;

	protected static $patterns = array(
		'string' => '\'[^\'\n]*\'|"(?:\\\\.|[^"\\\\\n])*"',
		'comment' => '<(?:[!])(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'tag-inline' => '<(?:img|hr|br|input|meta|area|embed|keygen|source|base|col|link|param|basefont|frame|isindex|wbr|command)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'tag-close' => '</(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'tag-open' => '<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>',
		'indent' => '\n[\t ]*',
		'whitespace' => '?:[\t ]+',
		'text' => '[^<]+',
	);

	function __construct()
	{
		$this->defaultContainer = Html::el('div');
	}

	function __invoke($template)
	{
		return $this->parse($template);
	}

	function parse($template)
	{
		if (trim($template) === '') {
			return $template;
		}

		$this->template = $template;
		$this->buildTree();

		return $this->toHtml();
	}

	protected function buildTree()
	{
		$t = new Tokenizer(self::$patterns, 'mi');
		$res = $t->tokenize($this->template);

		$this->parser = new NairaParser();

		foreach ($res as $node) {
			try {
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
			} catch (NairaException $e) {
				$e->sourceLine = $node['line'];
				throw $e;
			}
		}
	}

	protected function processTag($tag)
	{
		$original_id = String::match($tag, '~id=(?:"([^"]*)"|\'([^\']*)\'|([^"\'> \t\n]*))~im');
		$original_class = String::match($tag, '~class=(?:"([^"]*)"|\'([^\']*)\'|([^"\'> \t\n]*))~im');
		$original_id = isset($original_id[1]) ? $original_id[1] : (isset($original_id[2]) ? $original_id[2] : $original_id[3]);
		$original_class = isset($original_class[1]) ? $original_class[1] : (isset($original_class[2]) ? $original_class[2] : $original_class[3]);

		$tag = String::replace($tag, '~(id|class)=("[^"]*"|\'[^\']*\'|[^"\'> \t\n]*)?~im');

		$match = String::match($tag, '~^<(?P<tag>[a-z0-9]*)(?P<meta>[#.a-z0-9_-]*)(?P<attr>("[^"]*"|\'[^\']\'|[^"\'>]*)*)>$~im');

		$classes = String::matchAll($match['meta'], '~\.[a-z0-9_-]+~im');
		$id = String::match($match['meta'], '~#[a-z0-9_-]+~im');

		$tag = $match['tag'] ?: 'div';

		$c = array();
		foreach ($classes as $class) {
			$c[] = substr($class[0], 1);
		}

		if ($original_class !== NULL && trim($original_class) !== '') {
			$c[] = $original_class;
		}
		$class = implode(' ', $c);

		$id = $id ? substr($id[0], 1) : $original_id;

		$attr = String::replace(trim($match["attr"]), '~[ ]{2,}~', ' ');

		$html = "<$tag" . ($id ? " id=\"$id\"" : '') . ($c ? " class=\"$class\"" : '') . ($attr ? " $attr" : '') . ">";
		return $html;
	}

	function toHtml()
	{
		return $this->parser->tree->toHtml();
	}

	function __toString()
	{
		return $this->parser->tree->toHtml();
	}

}

class NairaParser extends Parser
{

	function getCloseTag()
	{
		$tag = $this->tree[$this->level]->value;
		$match = String::match($tag, '~<(?P<name>[a-z0-9]+)(>|[ \t\n])~i');
		return '</' . $match['name'] . '>';
	}

}

class NairaException extends \Nette\Templating\FilterException {}
}

namespace  {

 }
