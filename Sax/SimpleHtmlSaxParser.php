<?php


/**
 *    Converts HTML tokens into selected SAX events.
 *    @package SimpleTest
 *    @subpackage WebTester
 */
class SimpleHtmlSaxParser {
    /**
     * @var SimpleHtmlLexer
     */
    private $lexer;
    /**
     * @var SimplePhpPageBuilder
     */
    private $listener;
    private $tag;
    private $attributes;
    private $current_attribute;

    /**
     *    Sets the listener.
     *    @param SimplePhpPageBuilder $listener    SAX event handler.
     *    @access public
     */
    function __construct($listener) {
        $this->listener = $listener;
        $this->lexer = $this->createLexer($this);
        $this->tag = '';
        $this->attributes = array();
        $this->current_attribute = '';
    }
    
    /**
     * adding one listening tag
     * @param string $tag
     */
    function addTag($tag) {
        $this->lexer->addTag($tag);
    }
    
    /**
     * remove all listening tags
     */
    function clearTags() {
        $this->lexer->clearTags();
    }

    /**
     *    Runs the content through the lexer which
     *    should call back to the acceptors.
     *    @param string $raw      Page text to parse.
     *    @return boolean         False if parse error.
     *    @access public
     */
    function parse($raw) {
        return $this->lexer->parse($raw);
    }

    /**
     *    Sets up the matching lexer. Starts in 'text' mode.
     *    @param SimpleSaxParser $parser    Event generator, usually $self.
     *    @return SimpleLexer               Lexer suitable for this parser.
     *    @access public
     */
    static function createLexer(&$parser) {
        return new SimpleHtmlLexer($parser);
    }

    /**
     *    Accepts a token from the tag mode. If the
     *    starting element completes then the element
     *    is dispatched and the current attributes
     *    set back to empty. The element or attribute
     *    name is converted to lower case.
     *    @param string $token     Incoming characters.
     *    @param integer $event    Lexer event type.
     *    @return boolean          False if parse error.
     *    @access public
     */
    function acceptStartToken($token, $event) {
        if ($event == LEXER_ENTER) {
            $this->tag = strtolower(substr($token, 1));
            return true;
        }
        if ($event == LEXER_EXIT) {
            $success = $this->listener->startElement(
                    $this->tag,
                    $this->attributes);
            $this->tag = '';
            $this->attributes = array();
            return $success;
        }
        if ($token != '=') {
            $this->current_attribute = strtolower(html_entity_decode($token, ENT_QUOTES));
            $this->attributes[$this->current_attribute] = '';
        }
        return true;
    }

    /**
     *    Accepts a token from the end tag mode.
     *    The element name is converted to lower case.
     *    @param string $token     Incoming characters.
     *    @param integer $event    Lexer event type.
     *    @return boolean          False if parse error.
     *    @access public
     */
    function acceptEndToken($token, $event) {
        if (! preg_match('/<\/(.*)>/', $token, $matches)) {
            return false;
        }
        return $this->listener->endElement(strtolower($matches[1]));
    }

    /**
     *    Part of the tag data.
     *    @param string $token     Incoming characters.
     *    @param integer $event    Lexer event type.
     *    @return boolean          False if parse error.
     *    @access public
     */
    function acceptAttributeToken($token, $event) {
        if ($this->current_attribute) {
            if ($event == LEXER_UNMATCHED) {
                $this->attributes[$this->current_attribute] .=
                        html_entity_decode($token, ENT_QUOTES);
            }
            if ($event == LEXER_SPECIAL) {
                $this->attributes[$this->current_attribute] .=
                        preg_replace('/^=\s*/' , '', html_entity_decode($token, ENT_QUOTES));
            }
        }
        return true;
    }

    /**
     *    A character entity.
     *    @param string $token    Incoming characters.
     *    @param integer $event   Lexer event type.
     *    @return boolean         False if parse error.
     *    @access public
     */
    function acceptEntityToken($token, $event) {
    }

    /**
     *    Character data between tags regarded as
     *    important.
     *    @param string $token     Incoming characters.
     *    @param integer $event    Lexer event type.
     *    @return boolean          False if parse error.
     *    @access public
     */
    function acceptTextToken($token, $event) {
        return $this->listener->addContent($token);
    }

    /**
     *    Incoming data to be ignored.
     *    @param string $token     Incoming characters.
     *    @param integer $event    Lexer event type.
     *    @return boolean          False if parse error.
     *    @access public
     */
    function ignore($token, $event) {
        return true;
    }
}