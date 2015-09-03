<?php

/**
 *    Breaks HTML into SAX events.
 *    @package SimpleTest
 *    @subpackage WebTester
 */
class SimpleHtmlLexer extends SimpleLexer {

    protected $parsedTags = array('a', 'base', 'title', 'form', 'input', 'button', 'textarea', 'select',
                'option', 'frameset', 'frame', 'label','img');
    /**
     *    Sets up the lexer with case insensitive matching
     *    and adds the HTML handlers.
     *    @param SimpleSaxParser $parser  Handling strategy by
     *                                    reference.
     *    @access public
     */
    function __construct($parser,$tags=null) {
        if (is_array($tags)) {
            $this->parsedTags = $tags;
        }
        parent::__construct($parser, 'text');
        $this->mapHandler('text', 'acceptTextToken');
        $this->addSkipping();
        foreach ($this->getParsedTags() as $tag) {
            $this->addTag($tag);
        }
        $this->addInTagTokens();
    }

    /**
     *    List of parsed tags. Others are ignored.
     *    @return array        List of searched for tags.
     *    @access private
     */
    protected function getParsedTags() {
        return $this->parsedTags;
    }
    
    /**
     * remove listening tags
     */
    public function clearTags() {
        $this->parsedTags = array();
    }

    /**
     *    The lexer has to skip certain sections such
     *    as server code, client code and styles.
     *    @access private
     */
    protected function addSkipping() {
        $this->mapHandler('css', 'ignore');
        $this->addEntryPattern('<style', 'text', 'css');
        $this->addExitPattern('</style>', 'css');
        $this->mapHandler('js', 'ignore');
        $this->addEntryPattern('<script', 'text', 'js');
        $this->addExitPattern('</script>', 'js');
        $this->mapHandler('comment', 'ignore');
        $this->addEntryPattern('<!--', 'text', 'comment');
        $this->addExitPattern('-->', 'comment');
    }

    /**
     *    Pattern matches to start and end a tag.
     *    @param string $tag          Name of tag to scan for.
     *    @access private
     */
    public function addTag($tag) {
        $this->addSpecialPattern("</$tag>", 'text', 'acceptEndToken');
        $this->addEntryPattern("<$tag", 'text', 'tag');
    }

    /**
     *    Pattern matches to parse the inside of a tag
     *    including the attributes and their quoting.
     *    @access private
     */
    protected function addInTagTokens() {
        $this->mapHandler('tag', 'acceptStartToken');
        $this->addSpecialPattern('\s+', 'tag', 'ignore');
        $this->addAttributeTokens();
        $this->addExitPattern('/>', 'tag');
        $this->addExitPattern('>', 'tag');
    }

    /**
     *    Matches attributes that are either single quoted,
     *    double quoted or unquoted.
     *    @access private
     */
    protected function addAttributeTokens() {
        $this->mapHandler('dq_attribute', 'acceptAttributeToken');
        $this->addEntryPattern('=\s*"', 'tag', 'dq_attribute');
        $this->addPattern("\\\\\"", 'dq_attribute');
        $this->addExitPattern('"', 'dq_attribute');
        $this->mapHandler('sq_attribute', 'acceptAttributeToken');
        $this->addEntryPattern("=\s*'", 'tag', 'sq_attribute');
        $this->addPattern("\\\\'", 'sq_attribute');
        $this->addExitPattern("'", 'sq_attribute');
        $this->mapHandler('uq_attribute', 'acceptAttributeToken');
        $this->addSpecialPattern('=\s*[^>\s]*', 'tag', 'uq_attribute');
    }
}