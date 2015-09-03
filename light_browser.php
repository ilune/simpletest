<?php
/**
 * Some security control
 */
if (defined('BROWSER') == false
        ||  !BROWSER) {
    print 'Forbidden'; exit;
}
/**
 *  Base include file for SimpleTest
 *  @package    SimpleTest
 *  @subpackage WebTester
 *  @version    $Id: browser.php 2013 2011-04-29 09:29:45Z pp11 $
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once(dirname(__FILE__) . '/simpletest.php');
require_once(dirname(__FILE__) . '/http.php');
require_once(dirname(__FILE__) . '/encoding.php');
require_once(dirname(__FILE__) . '/page.php');
//require_once(dirname(__FILE__) . '/php_parser.php');
//require_once(dirname(__FILE__) . '/tidy_parser.php');
//require_once(dirname(__FILE__) . '/selector.php');
require_once(dirname(__FILE__) . '/frames.php');
require_once(dirname(__FILE__) . '/user_agent.php');
require_once(dirname(__FILE__) . '/SimpleBrowserHistory.php');

/**#@-*/

if (! defined('DEFAULT_MAX_NESTED_FRAMES')) {
    define('DEFAULT_MAX_NESTED_FRAMES', 3);
}

/**
 *    Simulated web browser. No Render here
 *    @package SimpleTest
 *    @subpackage WebTester
 */
class LightBrowser {
    /**
     * @var SimpleUserAgent
     */
    private $user_agent;
    /**
     * @var SimplePage
     */
    private $page;
    private $history;
    private $ignore_frames;
    private $maximum_nested_frames;

    /**
     *    Starts with a fresh browser with no
     *    cookie or any other state information. The
     *    exception is that a default proxy will be
     *    set up if specified in the options.
     *    @access public
     */
    function __construct() {
        $this->user_agent = $this->createUserAgent();
        $this->user_agent->useProxy(
                SimpleTest::getDefaultProxy(),
                SimpleTest::getDefaultProxyUsername(),
                SimpleTest::getDefaultProxyPassword());
        $this->page = new SimplePage();
        $this->history = $this->createHistory();
        $this->ignore_frames = false;
        $this->maximum_nested_frames = DEFAULT_MAX_NESTED_FRAMES;
    }

    /**
     *    Creates the underlying user agent.
     *    @return SimpleFetcher    Content fetcher.
     *    @access protected
     */
    protected function createUserAgent() {
		$simple = new SimpleUserAgent();
        return $simple;
    }

    /**
     *    Creates a new empty history list.
     *    @return SimpleBrowserHistory    New list.
     *    @access protected
     */
    protected function createHistory() {
        return new SimpleBrowserHistory();
    }

    /**
     *    Switches off cookie sending and recieving.
     *    @access public
     */
    function ignoreCookies() {
        $this->user_agent->ignoreCookies();
    }

    /**
     *    Switches back on the cookie sending and recieving.
     *    @access public
     */
    function useCookies() {
        $this->user_agent->useCookies();
    }
    
    /**
     * 
     * @param string/SimpleUrl $url          Target to fetch.
     * @param SimpleEncoding $encoding       GET/POST parameters.
     * @param integer $depth                 Nested frameset depth protection.
     * @return SimpleHttpResponse
     */
    public function getResponse($url, $encoding, $depth = 0) {
        return  $this->user_agent->fetchResponse($url, $encoding);
    }

    /**
     *    Fetches a page. Jointly recursive with the parse()
     *    method as it descends a frameset.
     *    @param string/SimpleUrl $url          Target to fetch.
     *    @param SimpleEncoding $encoding       GET/POST parameters.
     *    @param integer $depth                 Nested frameset depth protection.
     *    @return SimplePage                    Parsed page.
     *    @access private
     */
    protected function fetch($url, $encoding, $depth = 0) {
        $response = $this->user_agent->fetchResponse($url, $encoding);
        return new SimplePage($response);
    }

    /**
     *    Fetches a page or a single frame if that is the current
     *    focus.
     *    @param SimpleUrl $url                   Target to fetch.
     *    @param SimpleEncoding $parameters       GET/POST parameters.
     *    @return string                          Raw content of page.
     *    @access private
     */
    protected function load($url, $parameters) {
        return $this->loadPage($url, $parameters);
    }

    /**
     *    Fetches a page and makes it the current page/frame.
     *    @param string/SimpleUrl $url            Target to fetch as string.
     *    @param SimplePostEncoding $parameters   POST parameters.
     *    @return string                          Raw content of page.
     *    @access private
     */
    protected function loadPage($url, $parameters) {
        $this->page = $this->fetch($url, $parameters);
        $this->history->recordEntry(
                $this->page->getUrl(),
                $this->page->getRequestData());
        return $this->page->getRaw();
    }

    /**
     *    Removes expired and temporary cookies as if
     *    the browser was closed and re-opened.
     *    @param string/integer $date   Time when session restarted.
     *                                  If omitted then all persistent
     *                                  cookies are kept.
     *    @access public
     */
    function restart($date = false) {
        $this->user_agent->restart($date);
    }

    /**
     *    Adds a header to every fetch.
     *    @param string $header       Header line to add to every
     *                                request until cleared.
     *    @access public
     */
    function addHeader($header) {
        $this->user_agent->addHeader($header);
    }

    /**
     *    Reset headers
     *    @access public
     */
    function resetAdditionalHeader() {
        $this->user_agent->resetAdditionalHeader();
    }

    /**
     *    Ages the cookies by the specified time.
     *    @param integer $interval    Amount in seconds.
     *    @access public
     */
    function ageCookies($interval) {
        $this->user_agent->ageCookies($interval);
    }

    /**
     *    Sets an additional cookie. If a cookie has
     *    the same name and path it is replaced.
     *    @param string $name       Cookie key.
     *    @param string $value      Value of cookie.
     *    @param string $host       Host upon which the cookie is valid.
     *    @param string $path       Cookie path if not host wide.
     *    @param string $expiry     Expiry date.
     *    @access public
     */
    function setCookie($name, $value, $host = false, $path = '/', $expiry = false) {
        $this->user_agent->setCookie($name, $value, $host, $path, $expiry);
    }

    /**
     *    Reads the most specific cookie value from the
     *    browser cookies.
     *    @param string $host        Host to search.
     *    @param string $path        Applicable path.
     *    @param string $name        Name of cookie to read.
     *    @return string             False if not present, else the
     *                               value as a string.
     *    @access public
     */
    function getCookieValue($host, $path, $name) {
        return $this->user_agent->getCookieValue($host, $path, $name);
    }

    /**
     *    Reads the current cookies for the current URL.
     *    @param string $name   Key of cookie to find.
     *    @return string        Null if there is no current URL, false
     *                          if the cookie is not set.
     *    @access public
     */
    function getCurrentCookieValue($name) {
        return $this->user_agent->getBaseCookieValue($name, $this->page->getUrl());
    }

    /**
     *    Sets the maximum number of redirects before
     *    a page will be loaded anyway.
     *    @param integer $max        Most hops allowed.
     *    @access public
     */
    function setMaximumRedirects($max) {
        $this->user_agent->setMaximumRedirects($max);
    }

    /**
     *    Sets the maximum number of nesting of framed pages
     *    within a framed page to prevent loops.
     *    @param integer $max        Highest depth allowed.
     *    @access public
     */
    function setMaximumNestedFrames($max) {
        $this->maximum_nested_frames = $max;
    }

    /**
     *    Sets the socket timeout for opening a connection.
     *    @param integer $timeout      Maximum time in seconds.
     *    @access public
     */
    function setConnectionTimeout($timeout) {
        $this->user_agent->setConnectionTimeout($timeout);
    }

    /**
     *    Sets proxy to use on all requests for when
     *    testing from behind a firewall. Set URL
     *    to false to disable.
     *    @param string $proxy        Proxy URL.
     *    @param string $username     Proxy username for authentication.
     *    @param string $password     Proxy password for authentication.
     *    @access public
     */
    function useProxy($proxy, $username = false, $password = false) {
        $this->user_agent->useProxy($proxy, $username, $password);
    }

    /**
     *    Fetches the page content with a HEAD request.
     *    Will affect cookies, but will not change the base URL.
     *    @param string/SimpleUrl $url                Target to fetch as string.
     *    @param hash/SimpleHeadEncoding $parameters  Additional parameters for
     *                                                HEAD request.
     *    @return boolean                             True if successful.
     *    @access public
     */
    function head($url, $parameters = false) {
        if (!($url instanceof SimpleUrl)) {
            $url = new SimpleUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        $response = $this->user_agent->fetchResponse($url, new SimpleHeadEncoding($parameters));
        $this->page = new SimplePage($response);
        return ! $response->isError();
    }

    /**
     *    Fetches the page content with a simple GET request.
     *    @param string/SimpleUrl $url                Target to fetch.
     *    @param hash/SimpleFormEncoding $parameters  Additional parameters for
     *                                                GET request.
     *    @return string                              Content of page or false.
     *    @access public
     */
    function get($url, $parameters = false) {
        if (substr($url,0,5) == 'https' && $this->user_agent->usedProxy() ) {
            throw new Exception('HTTPS doesn\'t work with proxy');
        }
        if (!($url instanceof SimpleUrl)) {
            $url = new SimpleUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        return $this->load($url, new SimpleGetEncoding($parameters));
    }

    /**
     *    Fetches the page content with a POST request.
     *    @param string/SimpleUrl $url                Target to fetch as string.
     *    @param hash/SimpleFormEncoding $parameters  POST parameters or request body.
     *    @param string $content_type                 MIME Content-Type of the request body
     *    @return string                              Content of page.
     *    @access public
     */
    function post($url, $parameters = false, $content_type = false) {
        if (! is_object($url)) {
            $url = new SimpleUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        return $this->load($url, new SimplePostEncoding($parameters, $content_type));
    }

    /**
     *    Fetches the page content with a PUT request.
     *    @param string/SimpleUrl $url                Target to fetch as string.
     *    @param hash/SimpleFormEncoding $parameters  PUT request body.
     *    @param string $content_type                 MIME Content-Type of the request body
     *    @return string                              Content of page.
     *    @access public
     */
    function put($url, $parameters = false, $content_type = false) {
        if (! is_object($url)) {
            $url = new SimpleUrl($url);
        }
        return $this->load($url, new SimplePutEncoding($parameters, $content_type));
    }

    /**
     *    Sends a DELETE request and fetches the response.
     *    @param string/SimpleUrl $url                Target to fetch.
     *    @param hash/SimpleFormEncoding $parameters  Additional parameters for
     *                                                DELETE request.
     *    @return string                              Content of page or false.
     *    @access public
     */
    function delete($url, $parameters = false) {
        if (! is_object($url)) {
            $url = new SimpleUrl($url);
        }
        return $this->load($url, new SimpleDeleteEncoding($parameters));
    }

    /**
     *    Equivalent to hitting the retry button on the
     *    browser. Will attempt to repeat the page fetch. If
     *    there is no history to repeat it will give false.
     *    @return string/boolean   Content if fetch succeeded
     *                             else false.
     *    @access public
     */
    function retry() {
        $frames = $this->page->getFrameFocus();
        if (count($frames) > 0) {
            $this->loadFrame(
                    $frames,
                    $this->page->getUrl(),
                    $this->page->getRequestData());
            return $this->page->getRaw();
        }
        if ($url = $this->history->getUrl()) {
            $this->page = $this->fetch($url, $this->history->getParameters());
            return $this->page->getRaw();
        }
        return false;
    }

    /**
     *    Equivalent to hitting the back button on the
     *    browser. The browser history is unchanged on
     *    failure. The page content is refetched as there
     *    is no concept of content caching in SimpleTest.
     *    @return boolean     True if history entry and
     *                        fetch succeeded
     *    @access public
     */
    function back() {
        if (! $this->history->back()) {
            return false;
        }
        $content = $this->retry();
        if (! $content) {
            $this->history->forward();
        }
        return $content;
    }

    /**
     *    Equivalent to hitting the forward button on the
     *    browser. The browser history is unchanged on
     *    failure. The page content is refetched as there
     *    is no concept of content caching in SimpleTest.
     *    @return boolean     True if history entry and
     *                        fetch succeeded
     *    @access public
     */
    function forward() {
        if (! $this->history->forward()) {
            return false;
        }
        $content = $this->retry();
        if (! $content) {
            $this->history->back();
        }
        return $content;
    }

    /**
     *    Retries a request after setting the authentication
     *    for the current realm.
     *    @param string $username    Username for realm.
     *    @param string $password    Password for realm.
     *    @return boolean            True if successful fetch. Note
     *                               that authentication may still have
     *                               failed.
     *    @access public
     */
    function authenticate($username, $password) {
        if (! $this->page->getRealm()) {
            return false;
        }
        $url = $this->page->getUrl();
        if (! $url) {
            return false;
        }
        $this->user_agent->setIdentity(
                $url->getHost(),
                $this->page->getRealm(),
                $username,
                $password);
        return $this->retry();
    }

    /**
     *    Accessor for last error.
     *    @return string        Error from last response.
     *    @access public
     */
    function getTransportError() {
        return $this->page->getTransportError();
    }

    /**
     *    Accessor for current MIME type.
     *    @return string    MIME type as string; e.g. 'text/html'
     *    @access public
     */
    function getMimeType() {
        return $this->page->getMimeType();
    }

    /**
     *    Accessor for last response code.
     *    @return integer    Last HTTP response code received.
     *    @access public
     */
    function getResponseCode() {
        return $this->page->getResponseCode();
    }

    /**
     *    Accessor for last Authentication type. Only valid
     *    straight after a challenge (401).
     *    @return string    Description of challenge type.
     *    @access public
     */
    function getAuthentication() {
        return $this->page->getAuthentication();
    }

    /**
     *    Accessor for last Authentication realm. Only valid
     *    straight after a challenge (401).
     *    @return string    Name of security realm.
     *    @access public
     */
    function getRealm() {
        return $this->page->getRealm();
    }

    /**
     *    Accessor for current URL of page or frame if
     *    focused.
     *    @return string    Location of current page or frame as
     *                      a string.
     */
    function getUrl() {
        $url = $this->page->getUrl();
        return $url ? $url->asString() : false;
    }

    /**
     *    Accessor for base URL of page if set via BASE tag
     *    @return string    base URL
     */
    function getBaseUrl() {
        $url = $this->page->getBaseUrl();
        return $url ? $url->asString() : false;
    }
    /**
     *    Accessor for base URL of page if set via BASE tag
     *    @return string    base URL
     */
    function getServerUrl() {
        $aUrl = parse_url($this->getUrl());
        return $aUrl['scheme'].'://'.$aUrl['host'];
    }

    /**
     *    Accessor for raw bytes sent down the wire.
     *    @return string      Original text sent.
     *    @access public
     */
    function getRequest() {
        return $this->page->getRequest();
    }

    /**
     *    Accessor for raw page information.
     *    @return string      Original text content of web page.
     *    @access public
     */
    function getContent() {
        return $this->page->getRaw();
    }
}
?>
