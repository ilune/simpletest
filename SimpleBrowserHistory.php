<?php

/**
 *    Browser history list.
 *    @package SimpleTest
 *    @subpackage WebTester
 */
class SimpleBrowserHistory {
    private $sequence = array();
    private $position = -1;

    /**
     *    Test for no entries yet.
     *    @return boolean        True if empty.
     *    @access private
     */
    protected function isEmpty() {
        return ($this->position == -1);
    }

    /**
     *    Test for being at the beginning.
     *    @return boolean        True if first.
     *    @access private
     */
    protected function atBeginning() {
        return ($this->position == 0) && ! $this->isEmpty();
    }

    /**
     *    Test for being at the last entry.
     *    @return boolean        True if last.
     *    @access private
     */
    protected function atEnd() {
        return ($this->position + 1 >= count($this->sequence)) && ! $this->isEmpty();
    }

    /**
     *    Adds a successfully fetched page to the history.
     *    @param SimpleUrl $url                 URL of fetch.
     *    @param SimpleEncoding $parameters     Any post data with the fetch.
     *    @access public
     */
    function recordEntry($url, $parameters) {
        $this->dropFuture();
        array_push(
                $this->sequence,
                array('url' => $url, 'parameters' => $parameters));
        $this->position++;
    }

    /**
     *    Last fully qualified URL for current history
     *    position.
     *    @return SimpleUrl        URL for this position.
     *    @access public
     */
    function getUrl() {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->sequence[$this->position]['url'];
    }

    /**
     *    Parameters of last fetch from current history
     *    position.
     *    @return SimpleFormEncoding    Post parameters.
     *    @access public
     */
    function getParameters() {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->sequence[$this->position]['parameters'];
    }

    /**
     *    Step back one place in the history. Stops at
     *    the first page.
     *    @return boolean     True if any previous entries.
     *    @access public
     */
    function back() {
        if ($this->isEmpty() || $this->atBeginning()) {
            return false;
        }
        $this->position--;
        return true;
    }

    /**
     *    Step forward one place. If already at the
     *    latest entry then nothing will happen.
     *    @return boolean     True if any future entries.
     *    @access public
     */
    function forward() {
        if ($this->isEmpty() || $this->atEnd()) {
            return false;
        }
        $this->position++;
        return true;
    }

    /**
     *    Ditches all future entries beyond the current
     *    point.
     *    @access private
     */
    protected function dropFuture() {
        if ($this->isEmpty()) {
            return;
        }
        while (! $this->atEnd()) {
            array_pop($this->sequence);
        }
    }
}
