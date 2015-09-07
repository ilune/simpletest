<?php
/**
 *  base include file for SimpleTest
 *  @package    SimpleTest
 *  @subpackage WebTester
 */

/**#@+
 * Lexer mode stack constants
 */
foreach (array('LEXER_ENTER', 'LEXER_MATCHED',
                'LEXER_UNMATCHED', 'LEXER_EXIT',
                'LEXER_SPECIAL') as $i => $constant) {
    if (! defined($constant)) {
        define($constant, $i + 1);
    }
}

require_once('ParallelRegex.php');
require_once('SimpleStateStack.php');
require_once('Sax/SimpleLexer.php');
require_once('Sax/SimpleHtmlLexer.php');
require_once('Sax/SimpleHtmlSaxParser.php');
require_once('Sax/SimplePhpPageBuilder.php');
?>