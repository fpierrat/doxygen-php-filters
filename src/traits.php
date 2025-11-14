<?php
/**
 * Project: doxygen-php-filters
 * Author:  Alex Schickedanz (AbcAeffchen)
 * Date:    05.03.2015
 * License: GPL v2.0
 */

// Get the input
$source = file_get_contents($argv[1]);

// make traits to classes and add "trait MyTraitName." in the comment so that the doc mentions somewhere that it is a trait
// (Feel free to enhance if there's a better place to put this information, like this it will appear in the "detailed description" section)
$regexp = '#(?:/\*\*((?:\*(?!/)|[^*])*?)\s*\*/\s*)?trait(\s+\S+\s*){#';
$replace = "/**\n * trait $2.\n$1\n */\nclass$2{";
$source = preg_replace($regexp, $replace, $source);

// use traits by extending the class using them
// enhancement: now allows:
// - to have more than one "use" statement in the class using the trait
// - to have comments between the class opening brace { and the "use" statement, or between "use" statements
$regexp = '#class(\s+\S+\s*)(extends[\s]+[\S]+[\s]*)?{((?:\s*(?:(?:\s*/[^\n]*\n)|(?:\s*/\*(?:(?:\*(?!/)|[^*])*?)\*/))*\s*use[^;]+;)+)#';
function cbk($match){
	$uses = preg_replace('#(?:(?:\s*//[^\n]*\n\s*)|(?:\s*/\*(?:(?:\*(?!/)|[^*])*?)\*/\s*))#', '', $match[3]);//remove comments
	$uses = implode(', ', array_map('trim', explode(',', str_replace(['use',';'],['', ','], $uses))));
	$extends = ($match[2]?'':'extends ').trim(implode(',', [$match[2], $uses]), ', ');
	return "class{$match[1]}$extends {\n";
}
$source = preg_replace_callback($regexp, 'cbk', $source);

// Output
echo $source;
