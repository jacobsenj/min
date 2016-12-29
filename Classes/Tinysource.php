<?php
namespace InstituteWeb\Min;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2012 Dennis Römmich <dennis@roemmich.eu>
 */

/**
 * Class Tinysource
 *
 * @package InstituteWeb\Min
 */
class Tinysource
{

    /**
     * @var array Configuration of tx_tinysource
     */
    public $conf = array();

    /**
     * @var string
     */
    const TINYSOURCE_HEAD = 'head.';

    /**
     * @var string
     */
    const TINYSOURCE_BODY = 'body.';

    /**
     * Method called by "contentPostProc-all" TYPO3 core hook.
     * It checks the typoscript configuration and do the minify of source code.
     *
     * @param mixed $params
     * @param mixed $obj
     * @return void
     */
    public function tinysource(&$params, &$obj)
    {
        $this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_min.']['tinysource.'];

        if ($this->conf['enable'] && !$GLOBALS['TSFE']->config['config']['disableAllHeaderCode']) {
            $source = $GLOBALS['TSFE']->content;

            $headOffset = strpos($source, '<head');
            $headEndOffset = strpos($source, '>', $headOffset);
            $closingHeadOffset = strpos($source, '</head>');
            $bodyOffset = strpos($source, '<body');
            $bodyEndOffset = strpos($source, '>', $bodyOffset);
            $closingBodyOffset = strpos($source, '</body>');

            if ($headOffset !== false &&
                $headEndOffset !== false &&
                $closingHeadOffset !== false ||
                $bodyOffset !== false &&
                $bodyEndOffset !== false &&
                $closingBodyOffset !== false
            ) {
                $beforeHead = substr($source, 0, $headEndOffset + 1);
                $head = substr($source, $headEndOffset + 1, $closingHeadOffset - $headEndOffset - 1);
                $afterHead = substr($source, $closingHeadOffset, $bodyEndOffset - $closingHeadOffset + 1);
                $body = substr($source, $bodyEndOffset + 1, $closingBodyOffset - $bodyEndOffset - 1);
                $afterBody = substr($source, $closingBodyOffset);

                $head = $this->makeTiny($head, self::TINYSOURCE_HEAD);
                $body = $this->makeTiny($body, self::TINYSOURCE_BODY);

                if ($this->conf['oneLineMode']) {
                    $beforeHead = $this->makeTiny($beforeHead, self::TINYSOURCE_HEAD);
                    $afterHead = $this->makeTiny($afterHead, self::TINYSOURCE_HEAD);
                    $afterBody = $this->makeTiny($afterBody, self::TINYSOURCE_BODY);
                }

                $GLOBALS['TSFE']->content = $beforeHead . $head . $afterHead . $body . $afterBody;
            }
        }
    }

    /**
     * Gets the configuration and makes the source tiny, <head> and <body>
     * separated
     *
     * @param string $source
     * @param string $type BODY or HEAD
     * @return string the tiny source code
     */
    private function makeTiny($source, $type)
    {
        // Get replacements
        $replacements = array();

        if ($this->conf[$type]['stripTabs']) {
            $replacements[] = "\t";
        }
        if ($this->conf[$type]['stripNewLines']) {
            $replacements[] = "\n";
            $replacements[] = "\r";
        }

        // Do replacements
        $source = str_replace($replacements, '', $source);

        // Strip comments (only for <body>)
        if ($this->conf[$type]['stripComments'] && $type == self::TINYSOURCE_BODY) {
            //Prevent Strip of Search Comment if preventStripOfSearchComment is true
            if ($this->conf[$type]['preventStripOfSearchComment']) {
                $source = $this->keepTypo3SearchTag($source);
            } else {
                $source = $this->stripHtmlComments($source);
            }
        }

        // Strip double spaces
        if ($this->conf[$type]['stripDoubleSpaces']) {
            $source = preg_replace('/( {2,})/is', ' ', $source);
        }
        if ($this->conf[$type]['stripTwoLinesToOne']) {
            $source = preg_replace('/(\n{2,})/is', "\n", $source);
        }
        return $source;
    }

    /**
     * Strips html comments from given string, but keep TYPO3SEARCH strings
     *
     * @param string $source
     * @return string source without html comments, except TYPO3SEARCH comments
     */
    protected function keepTypo3SearchTag($source)
    {
        $originalSearchTagBegin = '<!--TYPO3SEARCH_begin-->';
        $originalSearchTagEnd = '<!--TYPO3SEARCH_end-->';
        $hash = uniqid('t3search_replacement_');
        $hashedSearchTagBegin = '$$$' . $hash . '_start$$$';
        $hashedSearchTagEnd = '$$$' . $hash . '_end$$$';

        $source = str_replace(
            array($originalSearchTagBegin, $originalSearchTagEnd),
            array($hashedSearchTagBegin, $hashedSearchTagEnd),
            $source
        );
        $source = $this->stripHtmlComments($source);
        $source = str_replace(
            array($hashedSearchTagBegin, $hashedSearchTagEnd),
            array($originalSearchTagBegin, $originalSearchTagEnd),
            $source
        );
        return $source;
    }

    /**
     * Strips html comments from given string
     *
     * @param string $source
     * @return string source without html comments
     */
    protected function stripHtmlComments($source)
    {
        $source = preg_replace(
            '/<\!\-\-(?!INT_SCRIPT\.)(?!HD_)(?!TDS_)(?!FD_)(?!CSS_INCLUDE_)(?!CSS_INLINE_)(?!JS_LIBS)(?!JS_INCLUDE)(?!JS_INLINE)(?!HEADERDATA)(?!JS_LIBS_FOOTER)(?!JS_INCLUDE_FOOTER)(?!JS_INLINE_FOOTER)(?!FOOTERDATA)(?!\s\#\#\#).*?\-\->/s',
            '',
            $source
        );
        return $source;
    }
}