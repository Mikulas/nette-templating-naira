<?php

minify(__DIR__ . '/..', __DIR__ . "/../minified/naira.min.php", TRUE);

/**
 * Generates minified version.
 *
 * @param  string  path to Nette
 * @param  string  path to Nette.minified
 * @param  bool    use namespaces
 * @return void
 * @author David Grudl
 *
 * @depend $project->php
 */
function minify($source, $dest, $useNamespaces = TRUE) {
	$d = __DIR__ . '/..';
	$files = array(
		"$d/src/Node.php",
		"$d/src/Parser.php",
		"$d/src/NairaFilter.php",
	);

	// shrink PHP
	$shrink = new ShrinkPHP;
	$shrink->useNamespaces = $useNamespaces;

	foreach ($files as $file) {
		$shrink->addFile($file);
	}

	$shrink->addContent("<?php $loader[1]");

	$content = $shrink->getOutput();
	$content = substr_replace($content, "<?php //netteloader=Nette\\Framework\n", 0, 5);
	$content = str_replace("\r\n", "\n", $content);
	$content = trim(preg_replace("#[\t ]+(\r?\n)#", '$1', $content)); // right trim

	// save minified file
	file_put_contents($dest, $content);
};



class ShrinkPHP
{
	public $firstComment = NULL;

	public $useNamespaces = FALSE;

	private $output = '';

	private $uses = array();

	private $inPHP;

	private $namespace;

	private $files;



	public function addFile($file)
	{
		$this->files[realpath($file)] = TRUE;
		$content = file_get_contents($file);
		// special handling for Connection.php && Statement.php
		$content = preg_replace('#class (N?Connection|N?Statement) extends.+#s', "if (class_exists('PDO')){ $0 }", $content);
		$this->addContent($content, dirname($file));
	}


	public function addContent($content, $dir = NULL)
	{
		$tokens = token_get_all($content);

		if ($this->useNamespaces) { // find namespace
			$hasNamespace = FALSE;
			foreach ($tokens as $num => $token)	{
				if ($token[0] === T_NAMESPACE) {
					$hasNamespace = TRUE;
					break;
				}
			}
			if (!$hasNamespace) {
				$tokens = token_get_all(preg_replace('#<\?php#A', "<?php\nnamespace;", $content)); // . '}');
			}
		}

		if ($this->inPHP) {
			if (is_array($tokens[0]) && $tokens[0][0] === T_OPEN_TAG) {
				// trick to eliminate ?><?php
				unset($tokens[0]);
			} else {
				$this->output .= '?>';
				$this->inPHP = FALSE;
			}
		}


		$pending = FALSE;
		reset($tokens);
		while (list($num, $token) = each($tokens))
		{
			if (is_array($token)) {
				$name = $token[0];
				$token = $token[1];
			} else {
				$name = NULL;
			}

			if ($name === T_CLASS || $name === T_INTERFACE) {
				for ($i = $num + 1; @$tokens[$i][0] !== T_STRING; $i++);

			} elseif ($name === T_COMMENT) {
				if (substr($token, -1) === "\n") {
					$this->output .= "\n";
				}
				continue;

			} elseif ($name === T_PUBLIC && ($tokens[$num + 2][0] === T_FUNCTION || $tokens[$num + 4][0] === T_FUNCTION)) {
				next($tokens);
				continue;

			} elseif ($name === T_DOC_COMMENT) {
				if (!$this->firstComment) {
					$this->firstComment = $token;
					$this->output .= $token . "\n";
					continue;

				} elseif (preg_match('# @[A-Z]#', $token)) { // phpDoc annotations leave unchanged

				} else {
					continue;
				}

			} elseif ($name === T_INCLUDE || $name === T_INCLUDE_ONCE || $name === T_REQUIRE || $name === T_REQUIRE_ONCE) {
				$pending = $name;
				$reqToken = $token;
				$expr = '';
				continue;

			} elseif ($name === T_NAMESPACE || $name === T_USE) {
				$pending = $name;
				$expr = '';
				continue;

			} elseif ($pending && ($name === T_CLOSE_TAG || ($name === NULL && ($token === ';' || $token === '{' || $token === ',') || ($pending === T_USE && $token === '(')))) { // end of special
				if ($pending === T_NAMESPACE) {
					if ($this->namespace !== $expr) {
						if ($this->namespace !== NULL) {
							$this->output .= "}\n\n";
						}
						$this->output .= "namespace $expr {\n\n";
						$this->uses = array();
						$this->namespace = $expr;
					}

				} elseif ($pending === T_USE) {
					if ($token === '(') {
						$this->output .= "use(";

					} elseif (!isset($this->uses[$expr])) {
						$this->uses[$expr] = TRUE;
						$this->output .= "use $expr;";
					}

				} else { // T_REQUIRE_ONCE, T_REQUIRE, T_INCLUDE, T_INCLUDE_ONCE
					$newFile = strtr($expr, array(
						'__DIR__' => "'" . addcslashes($dir, '\\\'') . "'",
						'dirname(__FILE__)' => "'" . addcslashes($dir, '\\\'') . "'",
					));
					$newFile = @eval('return ' . $newFile . ';');

					if ($newFile && realpath($newFile)) {
						$oldNamespace = $this->namespace;

						if ($pending !== T_REQUIRE_ONCE || !isset($this->files[realpath($newFile)])) {
							$this->addFile($newFile);
						}

						if (!$this->inPHP && $name !== T_CLOSE_TAG) {
							$this->output .= '<?php ';
							$this->inPHP = TRUE;
						}

						if ($this->namespace !== $oldNamespace) {
							if ($this->namespace !== NULL) {
								$this->output .= "}\n";
							}
							$this->namespace = $oldNamespace;
							$this->output .= "namespace $oldNamespace{";
							if ($this->uses && $oldNamespace) {
								$this->output .= "use " . implode(',', array_keys($this->uses)) . ";";
							}
						}
					} else {
						$this->output .= " $reqToken $expr;";
					}
				}
				if ($token !== ',') {
					$pending = FALSE;
				}
				$expr = '';
				continue;

			} elseif ($name === T_OPEN_TAG || $name === T_OPEN_TAG_WITH_ECHO) { // <?php <? <% <?=  <%=
				$this->inPHP = TRUE;

			} elseif ($name === T_CLOSE_TAG) { // ? > %>
				if ($num === count($token-1)) continue; // eliminate last close tag
				$this->inPHP = FALSE;

			} elseif ($token === ')' && substr($this->output, -1) === ',') {  // array(... ,)
				$this->output = substr($this->output, 0, -1);

			} elseif ($pending) {
				if ($name !== T_WHITESPACE) {
					$expr .= $token;
				}
				continue;
			}

			$this->output .= $token;
		}
	}



	public function getOutput()
	{
		if ($this->namespace !== NULL) {
			$this->output .= "}";
			$this->namespace = NULL;
		}
		return preg_replace('#([ \t]*\r?\n){2,}#', "\n\n", $this->output);
	}

}
