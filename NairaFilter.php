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



class NairaParser extends Parser
{

	public function getCloseTag()
	{
		$tag = $this->tree[$this->level]->value;
		$match = String::match($tag, '~^<(?P<name>[a-z0-9]+)(>|[ \t\n])~i');
		return '</' . $match['name'] . '>';
	}

}



class NairaException extends \Nette\Templating\FilterException {}
