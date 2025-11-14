<?php
/**
 * Project: doxygen-php-filters
 * User:    Alex Schickedanz (AbcAeffchen)
 * Date:    05.03.2015
 * License: GPL v2.0
 */

// Input
$source = file_get_contents($argv[1]);

// support short array syntax
$regexp = '#((var|public|protected|private)(\s+static)?)\s+(\$[^\s;=]+)\s+\=\s+\[([\s\S]*?)\]\;#';
$replace = '$1 $4 = array( $5 );';
$source = preg_replace($regexp, $replace, $source);

// add class member type hints
$regexp = '#\@(var|type)\s+([^\s]+)([^/]+)/\s+(var|public|protected|private)(\s+static)?\s+(\$[^\s;=]+)#';
$replace = '$3 */ $4 $5 $2 $6';
$source = preg_replace($regexp, $replace, $source);

// add type hinting to methods
$regexp = '#(\/\*\*[\s\S]*?@return\s+([^\s]*)[\s\S]*?\*\/[\s\S]*?)((public|protected|private)(\s+static)?)\s+function\s+([\S]*?)\s*?\(#';
$replace = '$1 $3 $2 function $6(';
$source = preg_replace($regexp, $replace, $source);

// change "@return $this" to '@return [ClassName]'
$tokens = token_get_all($source);
$classes = [];
foreach($tokens as $key => $token)
{
    if($token[0] == T_CLASS)
        $classes[] = $tokens[$key+2][1];
}

if(!empty($classes))
{
    list($source, $tail) = explode('class ' . $classes[0], $source, 2);
    $class_code = '';
    for($i = 1; $i < count($classes); $i++)
    {
        list($class_code, $tail) = explode('class ' . $classes[$i], $tail, 2);
        $class_code = str_replace('@return $this', '@return ' . $classes[$i-1], $class_code);
        $source .= 'class ' . $classes[$i-1] . $class_code;
    }
    $class_code = str_replace('@return $this', '@return ' . $classes[count($classes)-1], $tail);
    $source .= 'class ' . $classes[count($classes)-1] . $class_code;
}

// class_exists support
list($head,$tail) = preg_split('/.*if\(!class_exists\(.+/', $source, 2);

$openingBracePos = strpos($tail,'{');
$closingBracePos = strrpos($tail,'}');

if($openingBracePos !== false && $closingBracePos !== false)
    $source = $head . substr($tail,$openingBracePos+1,
                             $closingBracePos-$openingBracePos-1);

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
