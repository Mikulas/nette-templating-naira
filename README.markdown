# Naira filter for Nette

* _Author_: Mikuláš Dítě
* _Copyright_: (c) Mikuláš Dítě 2011

# Save 28% of coding.
# Get a 100% result!

# Syntax

```html
<header></>		<!-- Naira -->
<header></header>	<!-- Output Html -->

<#main></>
<div id="main"></div>

<.even></>
<div class="even"></div>

<footer#bottom.gray></>
<footer id="bottom" class="gray"></div>
```

# Requirements

* Nette Version 2.0 Beta or newer - http://nette.org/

# Installation

Piece of cake: just put these lines to your ```BasePresenter``` (and possibly ```BaseControl```)

```php
public function templatePrepareFilters($template)
{
	$template->registerFilter(new Nette\Templating\Filters\Naira);
	$template->registerFilter(new Nette\Latte\Engine);
}
```

Order does not matter. Naira should be compiled before Latte for minimal performance improvement upon first uncached request, yet the result should be the same.

# Usage

The default tag is ```div```.

```html
<.container></>
<div class="container"></div>

<article.container></>
<article class="container"></article>
```

Multiple IDs are resolved as follows:

```html
<#used#ambiguous></>
<div id="used"></div>

<#used#ambiguous id="whatever"></>
<div id="used"></div>
```

Mixing standard html and Naira is allowed

```html
<.foo class="bar"></>
<div class="foo bar"></div>
```

# Integration

Naira might be precompiled on localhost or used on production server.

Localhost directory in the archive you downloaded holds a simple php script that compiles specified files or directories every time they are changed.

```bash
php naira.php --watch src/input.html --output templates/index.html
php naira.php --watch src/ --output templates/
```

The compiler itself is production ready. Even though it is designed for Nette Framework, it can easily be used in projects based on other frameworks:

```php
$naira = new \Nette\Templating\Filters\Naira();
$output = $naira($input);
```

# License - Original BSD

Copyright (c) Mikuláš Dítě, 2011
All rights reserved.

*Redistribution* and use in source and binary forms, with or without
modification, are *permitted* provided that the following conditions are met:

* Redistributions of source code *must retain* the above *copyright* notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
* All advertising materials mentioning features or use of this software must display the following acknowledgement: This product includes software developed by the author.
* Neither the name of the author nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

This software is *provided* by author *_as_* *_is_* and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
