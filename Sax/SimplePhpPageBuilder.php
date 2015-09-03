<?php


/**
 *    SAX event handler. Maintains a list of
 *    open tags and dispatches them as they close.
 *    @package SimpleTest
 *    @subpackage WebTester
 */
class SimplePhpPageBuilder {
    private $tags;
    /**
     * @var SimplePage
     */
    private $page;
    private $private_content_tag;
    private $open_forms = array();
    private $complete_forms = array();
    private $frameset = false;
    private $loading_frames = array();
    private $frameset_nesting_level = 0;
    private $left_over_labels = array();

    /**
     *    Frees up any references so as to allow the PHP garbage
     *    collection from unset() to work.
     *    @access public
     */
    function free() {
        unset($this->tags);
        unset($this->page);
        unset($this->private_content_tags);
        $this->open_forms = array();
        $this->complete_forms = array();
        $this->frameset = false;
        $this->loading_frames = array();
        $this->frameset_nesting_level = 0;
        $this->left_over_labels = array();
    }

    /**
     *    This builder is always available.
     *    @return boolean       Always true.
     */
    function can() {
        return true;
    }

    /**
     *    Reads the raw content and send events
     *    into the page to be built.
     *    @param $response SimpleHttpResponse  Fetched response.
     *    @return SimplePage                   Newly parsed page.
     *    @access public
     */
    function parse($response) {
        $this->tags = array();
        $this->page = $this->createPage($response);
        $parser = new SimpleHtmlSaxParser($this);
        $parser->parse($response->getContent());
        $this->acceptPageEnd();
        $page = $this->page;
        $this->free();
        return $page;
    }

    /**
     *    Creates an empty page.
     *    @return SimplePage        New unparsed page.
     *    @access protected
     */
    protected function createPage($response) {
        return new SimplePage($response);
    }

    /**
     *    Creates the parser used with the builder.
     *    @param SimplePhpPageBuilder $listener   Target of parser.
     *    @return SimpleSaxParser              Parser to generate
     *                                         events for the builder.
     *    @access protected
     */
    protected function createParser(&$listener) {
        return new SimpleHtmlSaxParser($listener);
    }

    /**
     *    Start of element event. Opens a new tag.
     *    @param string $name         Element name.
     *    @param hash $attributes     Attributes without content
     *                                are marked as true.
     *    @return boolean             False on parse error.
     *    @access public
     */
    function startElement($name, $attributes) {
        $factory = new SimpleTagBuilder();
        $tag = $factory->createTag($name, $attributes);
        
        if (! $tag) {
            return true;
        }
        if ($tag->getTagName() == 'label') {
            $this->acceptLabelStart($tag);
            $this->openTag($tag);
            return true;
        }
        if ($tag->getTagName() == 'form') {
            $this->acceptFormStart($tag);
            return true;
        }
        if ($tag->getTagName() == 'frameset') {
            $this->acceptFramesetStart($tag);
            return true;
        }
        if ($tag->getTagName() == 'frame') {
            $this->acceptFrame($tag);
            return true;
        }
        if ($tag->isPrivateContent() && ! isset($this->private_content_tag)) {
            $this->private_content_tag = &$tag;
        }
        if ($tag->expectEndTag()) {
            $this->openTag($tag);
            return true;
        }
        $this->acceptTag($tag);
        return true;
    }

    /**
     *    End of element event.
     *    @param string $name        Element name.
     *    @return boolean            False on parse error.
     *    @access public
     */
    function endElement($name) {
        if ($name == 'label') {
            $this->acceptLabelEnd();
            return true;
        }
        if ($name == 'form') {
            $this->acceptFormEnd();
            return true;
        }
        if ($name == 'frameset') {
            $this->acceptFramesetEnd();
            return true;
        }
        if ($this->hasNamedTagOnOpenTagStack($name)) {
            $tag = array_pop($this->tags[$name]);
            if ($tag->isPrivateContent() && $this->private_content_tag->getTagName() == $name) {
                unset($this->private_content_tag);
            }
            $this->addContentTagToOpenTags($tag);
            $this->acceptTag($tag);
            return true;
        }
        return true;
    }

    /**
     *    Test to see if there are any open tags awaiting
     *    closure that match the tag name.
     *    @param string $name        Element name.
     *    @return boolean            True if any are still open.
     *    @access private
     */
    protected function hasNamedTagOnOpenTagStack($name) {
        return isset($this->tags[$name]) && (count($this->tags[$name]) > 0);
    }

    /**
     *    Unparsed, but relevant data. The data is added
     *    to every open tag.
     *    @param string $text        May include unparsed tags.
     *    @return boolean            False on parse error.
     *    @access public
     */
    function addContent($text) {
        if (isset($this->private_content_tag)) {
            $this->private_content_tag->addContent($text);
        } else {
            $this->addContentToAllOpenTags($text);
        }
        return true;
    }

    /**
     *    Any content fills all currently open tags unless it
     *    is part of an option tag.
     *    @param string $text        May include unparsed tags.
     *    @access private
     */
    protected function addContentToAllOpenTags($text) {
        foreach (array_keys($this->tags) as $name) {
            for ($i = 0, $count = count($this->tags[$name]); $i < $count; $i++) {
                $this->tags[$name][$i]->addContent($text);
            }
        }
    }

    /**
     *    Parsed data in tag form. The parsed tag is added
     *    to every open tag. Used for adding options to select
     *    fields only.
     *    @param SimpleTag $tag        Option tags only.
     *    @access private
     */
    protected function addContentTagToOpenTags(&$tag) {
        if ($tag->getTagName() != 'option') {
            return;
        }
        foreach (array_keys($this->tags) as $name) {
            for ($i = 0, $count = count($this->tags[$name]); $i < $count; $i++) {
                $this->tags[$name][$i]->addTag($tag);
            }
        }
    }

    /**
     *    Opens a tag for receiving content. Multiple tags
     *    will be receiving input at the same time.
     *    @param SimpleTag $tag        New content tag.
     *    @access private
     */
    protected function openTag($tag) {
        $name = $tag->getTagName();
        if (! in_array($name, array_keys($this->tags))) {
            $this->tags[$name] = array();
        }
        $this->tags[$name][] = $tag;
    }

    /**
     *    Adds a tag to the page.
     *    @param SimpleTag $tag        Tag to accept.
     *    @access public
     */
    protected function acceptTag($tag) {
        if ($tag->getTagName() == "a") {
            $this->page->addLink($tag);
        } elseif ($tag->getTagName() == "img") {
            $this->page->addImage($tag);
        } elseif ($tag->getTagName() == "base") {
            $this->page->setBase($tag->getAttribute('href'));
        } elseif ($tag->getTagName() == "title") {
            $this->page->setTitle($tag);
        } elseif ($this->isFormElement($tag->getTagName())) {
            for ($i = 0; $i < count($this->open_forms); $i++) {
                $this->open_forms[$i]->addWidget($tag);
            }
            $this->last_widget = $tag;
        }
    }

    /**
     *    Opens a label for a described widget.
     *    @param SimpleFormTag $tag      Tag to accept.
     *    @access public
     */
    protected function acceptLabelStart($tag) {
        $this->label = $tag;
        unset($this->last_widget);
    }

    /**
     *    Closes the most recently opened label.
     *    @access public
     */
    protected function acceptLabelEnd() {
        if (isset($this->label)) {
            if (isset($this->last_widget)) {
                $this->last_widget->setLabel($this->label->getText());
                unset($this->last_widget);
            } else {
                $this->left_over_labels[] = SimpleTestCompatibility::copy($this->label);
            }
            unset($this->label);
        }
    }

    /**
     *    Tests to see if a tag is a possible form
     *    element.
     *    @param string $name     HTML element name.
     *    @return boolean         True if form element.
     *    @access private
     */
    protected function isFormElement($name) {
        return in_array($name, array('input', 'button', 'textarea', 'select'));
    }

    /**
     *    Opens a form. New widgets go here.
     *    @param SimpleFormTag $tag      Tag to accept.
     *    @access public
     */
    protected function acceptFormStart($tag) {
        $this->open_forms[] = new SimpleForm($tag, $this->page);
    }

    /**
     *    Closes the most recently opened form.
     *    @access public
     */
    protected function acceptFormEnd() {
        if (count($this->open_forms)) {
            $this->complete_forms[] = array_pop($this->open_forms);
        }
    }

    /**
     *    Opens a frameset. A frameset may contain nested
     *    frameset tags.
     *    @param SimpleFramesetTag $tag      Tag to accept.
     *    @access public
     */
    protected function acceptFramesetStart($tag) {
        if (! $this->isLoadingFrames()) {
            $this->frameset = $tag;
        }
        $this->frameset_nesting_level++;
    }

    /**
     *    Closes the most recently opened frameset.
     *    @access public
     */
    protected function acceptFramesetEnd() {
        if ($this->isLoadingFrames()) {
            $this->frameset_nesting_level--;
        }
    }

    /**
     *    Takes a single frame tag and stashes it in
     *    the current frame set.
     *    @param SimpleFrameTag $tag      Tag to accept.
     *    @access public
     */
    protected function acceptFrame($tag) {
        if ($this->isLoadingFrames()) {
            if ($tag->getAttribute('src')) {
                $this->loading_frames[] = $tag;
            }
        }
    }

    /**
     *    Test to see if in the middle of reading
     *    a frameset.
     *    @return boolean        True if inframeset.
     *    @access private
     */
    protected function isLoadingFrames() {
        return $this->frameset and $this->frameset_nesting_level > 0;
    }

    /**
     *    Marker for end of complete page. Any work in
     *    progress can now be closed.
     *    @access public
     */
    protected function acceptPageEnd() {
        while (count($this->open_forms)) {
            $this->complete_forms[] = array_pop($this->open_forms);
        }
        foreach ($this->left_over_labels as $label) {
            for ($i = 0, $count = count($this->complete_forms); $i < $count; $i++) {
                $this->complete_forms[$i]->attachLabelBySelector(
                        new SimpleById($label->getFor()),
                        $label->getText());
            }
        }
        $this->page->setForms($this->complete_forms);
        $this->page->setFrames($this->loading_frames);
    }
}