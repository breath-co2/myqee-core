<?php
namespace Core;

/**
 * 字符处理核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Text
{

    /**
     * @var  array   number units and text equivalents
     */
    public static $units = array(
        1000000000 => 'billion',
        1000000 => 'million',
        1000 => 'thousand',
        100 => 'hundred',
        90 => 'ninety',
        80 => 'eighty',
        70 => 'seventy',
        60 => 'sixty',
        50 => 'fifty',
        40 => 'fourty',
        30 => 'thirty',
        20 => 'twenty',
        19 => 'nineteen',
        18 => 'eighteen',
        17 => 'seventeen',
        16 => 'sixteen',
        15 => 'fifteen',
        14 => 'fourteen',
        13 => 'thirteen',
        12 => 'twelve',
        11 => 'eleven',
        10 => 'ten',
        9 => 'nine',
        8 => 'eight',
        7 => 'seven',
        6 => 'six',
        5 => 'five',
        4 => 'four',
        3 => 'three',
        2 => 'two',
        1 => 'one'
    );

    /**
     * Limits a phrase to a given number of words.
     *
     * $text = static::limit_words($text);
     *
     * @param   string   phrase to limit words of
     * @param   integer  number of words to limit to
     * @param   string   end character or entity
     * @return  string
     */
    public static function limit_words($str, $limit = 100, $end_char = null)
    {
        $limit = (int)$limit;
        $end_char = ($end_char === null) ? '…' : $end_char;

        if ( \trim($str) === '' ) return $str;

        if ( $limit <= 0 ) return $end_char;

        \preg_match('/^\s*+(?:\S++\s*+){1,' . $limit . '}/u', $str, $matches);

        // Only attach the end character if the matched string is shorter
        // than the starting string.
        return \rtrim($matches[0]) . ((\strlen($matches[0]) === \strlen($str)) ? '' : $end_char);
    }

    /**
     * Limits a phrase to a given number of characters.
     *
     * $text = static::limit_chars($text);
     *
     * @param   string   phrase to limit characters of
     * @param   integer  number of characters to limit to
     * @param   string   end character or entity
     * @param   boolean  enable or disable the preservation of words while limiting
     * @return  string
     * @uses    \utf8::strlen
     */
    public static function limit_chars($str, $limit = 100, $end_char = null, $preserve_words = false)
    {
        $end_char = ($end_char === null) ? '…' : $end_char;

        $limit = (int)$limit;

        if ( \trim($str) === '' || \utf8::strlen($str) <= $limit ) return $str;

        if ( $limit <= 0 ) return $end_char;

        if ( $preserve_words === false ) return \rtrim(\utf8::substr($str, 0, $limit)) . $end_char;

        // Don't preserve words. The limit is considered the top limit.
        // No strings with a length longer than $limit should be returned.
        if ( ! \preg_match('/^.{0,' . $limit . '}\s/us', $str, $matches) ) return $end_char;

        return \rtrim($matches[0]) . ((\strlen($matches[0]) === \strlen($str)) ? '' : $end_char);
    }

    /**
     * Alternates between two or more strings.
     *
     * echo \Test::alternate('one', 'two'); // "one"
     * echo \Test::alternate('one', 'two'); // "two"
     * echo \Test::alternate('one', 'two'); // "one"
     *
     * Note that using multiple iterations of different strings may produce
     * unexpected results.
     *
     * @param   string  strings to alternate between
     * @return  string
     */
    public static function alternate()
    {
        static $i = null;

        if ( \func_num_args() === 0 )
        {
            $i = 0;
            return '';
        }

        $args = \func_get_args();

        return $args[($i++ % \count($args))];
    }

    /**
     * Generates a random string of a given type and length.
     *
     *
     * $str = \Test::random(); // 8 character random string
     *
     * The following types are supported:
     *
     * alnum
     * :  Upper and lower case a-z, 0-9 (default)
     *
     * alpha
     * :  Upper and lower case a-z
     *
     * hexdec
     * :  Hexadecimal characters a-f, 0-9
     *
     * distinct
     * :  Uppercase characters and numbers that cannot be confused
     *
     * You can also create a custom type by providing the "pool" of characters
     * as the type.
     *
     * @param   string   a type of pool, or a string of characters to use as the pool
     * @param   integer  length of string to return
     * @return  string
     * @uses    \utf8::split
     */
    public static function random($type = null, $length = 8)
    {
        if ( $type === null )
        {
            // Default is to generate an alphanumeric string
            $type = 'alnum';
        }

        $utf8 = false;

        switch ( $type )
        {
            case 'alnum' :
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'alpha' :
                $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'hexdec' :
                $pool = '0123456789abcdef';
                break;
            case 'numeric' :
                $pool = '0123456789';
                break;
            case 'nozero' :
                $pool = '123456789';
                break;
            case 'distinct' :
                $pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';
                break;
            default :
                $pool = (string)$type;
                $utf8 = ! \utf8::is_ascii($pool);
                break;
        }

        // Split the pool into an array of characters
        $pool = ($utf8 === true) ? \utf8::str_split($pool, 1) : \str_split($pool, 1);

        // Largest pool key
        $max = \count($pool) - 1;

        $str = '';
        for( $i = 0; $i < $length; $i ++ )
        {
            // Select a random character from the pool and add it to the string
            $str .= $pool[\mt_rand(0, $max)];
        }

        // Make sure alnum strings contain at least one letter and one digit
        if ( $type === 'alnum' && $length > 1 )
        {
            if ( \ctype_alpha($str) )
            {
                // Add a random digit
                $str[\mt_rand(0, $length - 1)] = \chr(\mt_rand(48, 57));
            }
            elseif ( \ctype_digit($str) )
            {
                // Add a random letter
                $str[\mt_rand(0, $length - 1)] = \chr(\mt_rand(65, 90));
            }
        }

        return $str;
    }

    /**
     * Reduces multiple slashes in a string to single slashes.
     *
     * $str = static::reduce_slashes('foo//bar/baz'); // "foo/bar/baz"
     *
     * @param   string  string to reduce slashes of
     * @return  string
     */
    public static function reduce_slashes($str)
    {
        return \preg_replace('#(?<!:)//+#', '/', $str);
    }

    /**
     * Replaces the given words with a string.
     *
     * // Displays "What the #####, man!"
     * echo \Test::censor('What the frick, man!', array(
     * 'frick' => '#####',
     * ));
     *
     * @param   string   phrase to replace words in
     * @param   array    words to replace
     * @param   string   replacement string
     * @param   boolean  replace words across word boundries (space, period, etc)
     * @return  string
     * @uses    \utf8::strlen
     */
    public static function censor($str, $badwords, $replacement = '#', $replace_partial_words = true)
    {
        foreach ( (array)$badwords as $key => $badword )
        {
            $badwords[$key] = \str_replace('\*', '\S*?', \preg_quote((string)$badword));
        }

        $regex = '(' . \implode('|', $badwords) . ')';

        if ( $replace_partial_words === false )
        {
            // Just using \b isn't sufficient when we need to replace a badword that already contains word boundaries itself
            $regex = '(?<=\b|\s|^)' . $regex . '(?=\b|\s|$)';
        }

        $regex = '!' . $regex . '!ui';

        if ( \utf8::strlen($replacement) == 1 )
        {
            $regex .= 'e';
            return \preg_replace($regex, '\\str_repeat($replacement, \utf8::strlen(\'$1\'))', $str);
        }

        return \preg_replace($regex, $replacement, $str);
    }

    /**
     * Finds the text that is similar between a set of words.
     *
     * $match = static::similar(array('fred', 'fran', 'free'); // "fr"
     *
     * @param   array   words to find similar text of
     * @return  string
     */
    public static function similar(array $words)
    {
        // First word is the word to match against
        $word = \current($words);

        for( $i = 0, $max = \strlen($word); $i < $max; ++ $i )
        {
            foreach ( $words as $w )
            {
                // Once a difference is found, break out of the loops
                if ( !isset($w[$i]) || $w[$i]!==$word[$i] ) break 2;
            }
        }

        // Return the similar text
        return \substr($word, 0, $i);
    }

    /**
     * Converts text email addresses and anchors into links. Existing links
     * will not be altered.
     *
     * echo \Test::auto_link($text);
     *
     * [!!] This method is not foolproof since it uses regex to parse HTML.
     *
     * @param   string   text to auto link
     * @return  string
     * @uses    static::auto_link_urls
     * @uses    static::auto_link_emails
     */
    public static function auto_link($text)
    {
        // Auto link emails first to prevent problems with "www.domain.com@example.com"
        return static::auto_link_urls(static::auto_link_emails($text));
    }

    /**
     * Converts text anchors into links. Existing links will not be altered.
     *
     * echo \Test::auto_link_urls($text);
     *
     * [!!] This method is not foolproof since it uses regex to parse HTML.
     *
     * @param   string   text to auto link
     * @return  string
     * @uses    html::anchor
     */
    public static function auto_link_urls($text)
    {
        $auto_link_urls_callback1 = function ($matches)
        {
            return \html::anchor($matches[0]);
        };

        $auto_link_urls_callback2 = function ($matches)
        {
            return \html::anchor('http://' . $matches[0], $matches[0]);
        };

        // Find and replace all http/https/ftp/ftps links that are not part of an existing html anchor
        $text = \preg_replace_callback('~\b(?<!href="|">)(?:ht|f)tps?://\S+(?:/|\b)~i', $auto_link_urls_callback1, $text);

        // Find and replace all naked www.links.com (without http://)
        return \preg_replace_callback('~\b(?<!://|">)www(?:\.[a-z0-9][-a-z0-9]*+)+\.[a-z]{2,6}\b~i', $auto_link_urls_callback2, $text);
    }

    /**
     * Converts text email addresses into links. Existing links will not
     * be altered.
     *
     * echo \Test::auto_link_emails($text);
     *
     * [!!] This method is not foolproof since it uses regex to parse HTML.
     *
     * @param   string   text to auto link
     * @return  string
     * @uses    html::mailto
     */
    public static function auto_link_emails($text)
    {
        $auto_link_emails_callback = function ($matches)
        {
            return \html::mailto($matches[0]);
        };

        // Find and replace all email addresses that are not part of an existing html mailto anchor
        // Note: The "58;" negative lookbehind prevents matching of existing encoded html mailto anchors
        //       The html entity for a colon (:) is &#58; or &#058; or &#0058; etc.
        return \preg_replace_callback('~\b(?<!href="mailto:|58;)(?!\.)[-+_a-z0-9.]++(?<!\.)@(?![-.])[-a-z0-9.]+(?<!\.)\.[a-z]{2,6}\b(?!</a>)~i', $auto_link_emails_callback, $text);
    }

    /**
     * Automatically applies "p" and "br" markup to text.
     * Basically [nl2br](http://php.net/nl2br) on steroids.
     *
     * echo \Test::auto_p($text);
     *
     * [!!] This method is not foolproof since it uses regex to parse HTML.
     *
     * @param   string   subject
     * @param   boolean  convert single linebreaks to <br />
     * @return  string
     */
    public static function auto_p($str, $br = true)
    {
        // Trim whitespace
        if ( ($str = \trim($str)) === '' ) return '';

        // Standardize newlines
        $str = \str_replace(array("\r\n", "\r"), "\n", $str);

        // Trim whitespace on each line
        $str = \preg_replace('~^[ \t]+~m', '', $str);
        $str = \preg_replace('~[ \t]+$~m', '', $str);

        // The following regexes only need to be executed if the string contains html
        if ( $html_found = (\strpos($str, '<') !== false) )
        {
            // Elements that should not be surrounded by p tags
            $no_p = '(?:p|div|h[1-6r]|ul|ol|li|blockquote|d[dlt]|pre|t[dhr]|t(?:able|body|foot|head)|c(?:aption|olgroup)|form|s(?:elect|tyle)|a(?:ddress|rea)|ma(?:p|th))';

            // Put at least two linebreaks before and after $no_p elements
            $str = \preg_replace('~^<' . $no_p . '[^>]*+>~im', "\n$0", $str);
            $str = \preg_replace('~</' . $no_p . '\s*+>$~im', "$0\n", $str);
        }

        // Do the <p> magic!
        $str = '<p>' . \trim($str) . '</p>';
        $str = \preg_replace('~\n{2,}~', "</p>\n\n<p>", $str);

        // The following regexes only need to be executed if the string contains html
        if ( $html_found !== false )
        {
            // Remove p tags around $no_p elements
            $str = \preg_replace('~<p>(?=</?' . $no_p . '[^>]*+>)~i', '', $str);
            $str = \preg_replace('~(</?' . $no_p . '[^>]*+>)</p>~i', '$1', $str);
        }

        // Convert single linebreaks to <br />
        if ( $br === true )
        {
            $str = \preg_replace('~(?<!\n)\n(?!\n)~', "<br />\n", $str);
        }

        return $str;
    }

    /**
     * Returns human readable sizes. Based on original functions written by
     * [Aidan Lister](http://aidanlister.com/repos/v/function.size_readable.php)
     * and [Quentin Zervaas](http://www.phpriot.com/d/code/strings/filesize-format/).
     *
     * echo \Test::bytes(filesize($file));
     *
     * @param   integer  size in bytes
     * @param   string   a definitive unit
     * @param   string   the return string format
     * @param   boolean  whether to use SI prefixes or IEC
     * @return  string
     */
    public static function bytes($bytes, $force_unit = null, $format = null, $si = true)
    {
        // Format string
        $format = ($format === null) ? '%01.2f %s' : (string)$format;

        // IEC prefixes (binary)
        if ( $si == false || \strpos($force_unit, 'i') !== false )
        {
            $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
            $mod = 1024;
        }
        // SI prefixes (decimal)
        else
        {
            $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB');
            $mod = 1000;
        }

        // Determine unit to use
        if ( ($power = \array_search((string)$force_unit, $units)) === false )
        {
            $power = ($bytes > 0) ? \floor(\log($bytes, $mod)) : 0;
        }

        return \sprintf($format, $bytes / \pow($mod, $power), $units[$power]);
    }

    /**
     * Format a number to human-readable text.
     *
     * // Display: one thousand and twenty-four
     * echo \Test::number(1024);
     *
     * // Display: five million, six hundred and thirty-two
     * echo \Test::number(5000632);
     *
     * @param   integer   number to format
     * @return  string
     * @since   3.0.8
     */
    public static function number($number)
    {
        // The number must always be an integer
        $number = (int)$number;

        // Uncompiled text version
        $text = array();

        // Last matched unit within the loop
        $last_unit = null;

        // The last matched item within the loop
        $last_item = '';

        foreach ( static::$units as $unit => $name )
        {
            if ( $number / $unit >= 1 )
            {
                // $value = the number of times the number is divisble by unit
                $number -= $unit * ($value = (int)\floor($number / $unit));
                // Temporary var for textifying the current unit
                $item = '';

                if ( $unit < 100 )
                {
                    if ( $last_unit < 100 && $last_unit >= 20 )
                    {
                        $last_item .= '-' . $name;
                    }
                    else
                    {
                        $item = $name;
                    }
                }
                else
                {
                    $item = static::number($value) . ' ' . $name;
                }

                // In the situation that we need to make a composite number (i.e. twenty-three)
                // then we need to modify the previous entry
                if ( empty($item) )
                {
                    \array_pop($text);

                    $item = $last_item;
                }

                $last_item = $text[] = $item;
                $last_unit = $unit;
            }
        }

        if ( \count($text) > 1 )
        {
            $and = \array_pop($text);
        }

        $text = \implode(', ', $text);

        if ( isset($and) )
        {
            $text .= ' and ' . $and;
        }

        return $text;
    }

    /**
     * Prevents [widow words](http://www.shauninman.com/archive/2006/08/22/widont_wordpress_plugin)
     * by inserting a non-breaking space between the last two words.
     *
     * echo \Test::widont($text);
     *
     * @param   string  text to remove widows from
     * @return  string
     */
    public static function widont($str)
    {
        $str = \rtrim($str);
        $space = \strrpos($str, ' ');

        if ( $space !== false )
        {
            $str = \substr($str, 0, $space) . '&nbsp;' . \substr($str, $space + 1);
        }

        return $str;
    }



    /**
     * 等同js脚本里的escape函数
     *
     * @param string $str
     * @param string $encode
     */
    public static function escape($str, $encode = 'UTF-8')
    {
        $encode = \strtoupper($encode);
        if ( $encode == 'UTF-8' )
        {
            \preg_match_all("/[\xC0-\xE0].|[\xE0-\xF0]..|[\x01-\x7f]+/", $str, $r);
        }
        else
        {
            \preg_match_all("/[\x80-\xff].|[\x01-\x7f]+/", $str, $r);
        }
        //prt($r);
        $ar = $r[0];
        foreach ( $ar as $k => $v )
        {
            $ord = ord($v[0]);
            if ( $ord <= 128 )
            {
                $ar[$k] = \rawurlencode($v);
            }
            else
            {
                $ar[$k] = "%u" . \bin2hex(\iconv($encode, "UCS-2BE", $v));
            }
        }

        return \join("", $ar);
    }

    /**
     * 等同js脚本里的unescape函数
     *
     * @param string $str
     * @param string $encode
     */
    public static function unescape($str, $encode = 'UTF-8')
    {
        $encode = \strtoupper($encode);
        if ( $encode == 'GBK' || $encode == 'GB2312' )
        {
            $substrStrNum = 2;
        }
        else
        {
            $substrStrNum = 3;
        }
        $str = \rawurldecode($str);
        \preg_match_all('#%u.{4}|&#x.{4};|&#\d+;|&#\d+?|.+#U', $str, $r);
        $ar = $r[0];
        foreach ( $ar as $k => $v )
        {
            if ( \substr($v, 0, 2) == "%u" ) $ar[$k] = \iconv("UCS-2BE", $encode, \pack("H4", \substr($v, - 4)));
            elseif ( \substr($v, 0, 3) == "&#x" ) $ar[$k] = \iconv("UCS-2BE", $encode, \pack("H4", \substr($v, $substrStrNum, - 1)));
            elseif ( \substr($v, 0, 2) == "&#" )
            {
                $ar [$k] = \iconv( "UCS-2BE", $encode, \pack( "n", \preg_replace( '#[^\d]#', "", $v ) ) );
            }
        }
        return \join( "", $ar );
    }

    /**
     * 截取文件
     *
     * @param string $str
     * @param int $start
     * @param int $length
     * @param string $encoding
     */
    public static function substr($str, $start, $length = null, $encoding = 'UTF-8')
    {
        static $supper_mb = null;
        if (null===$supper_mb)
        {
            $supper_mb = \function_exists('\\mb_substr');
        }
        if ( $supper_mb )
        {
            if ( null === $length )
            {
                return \mb_substr((string)$str, $start, null, $encoding);
            }
            else
            {
                return \mb_substr((string)$str, $start, $length, $encoding);
            }
        }
        else
        {
            if ( self::is_ascii($str) ) return ($length === null) ? \substr($str, $start) : \substr($str, $start, $length);

            // Normalize params
            $str = (string)$str;
            $strlen = self::strlen($str);
            $start = (int)($start < 0) ? \max(0, $strlen + $start) : $start; // Normalize to positive offset
            $length = ($length === null) ? null : (int)$length;

            // Impossible
            if ( $length === 0 || $start >= $strlen || ($length < 0 && $length <= $start - $strlen) ) return '';

            // Whole string
            if ( $start == 0 && ($length === null || $length >= $strlen) ) return $str;

            // Build regex
            $regex = '^';

            // Create an offset expression
            if ( $start > 0 )
            {
                // PCRE repeating quantifiers must be less than 65536, so repeat when necessary
                $x = (int)($start / 65535);
                $y = (int)($start % 65535);
                $regex .= ($x == 0) ? '' : '(?:.{65535}){' . $x . '}';
                $regex .= ($y == 0) ? '' : '.{' . $y . '}';
            }

            // Create a length expression
            if ( $length === null )
            {
                $regex .= '(.*)'; // No length set, grab it all
            } // Find length from the left (positive length)
            elseif ( $length > 0 )
            {
                // Reduce length so that it can't go beyond the end of the string
                $length = \min($strlen - $start, $length);

                $x = (int)($length / 65535);
                $y = (int)($length % 65535);
                $regex .= '(';
                $regex .= ($x == 0) ? '' : '(?:.{65535}){' . $x . '}';
                $regex .= '.{' . $y . '})';
            } // Find length from the right (negative length)
            else
            {
                $x = (int)(- $length / 65535);
                $y = (int)(- $length % 65535);
                $regex .= '(.*)';
                $regex .= ($x == 0) ? '' : '(?:.{65535}){' . $x . '}';
                $regex .= '.{' . $y . '}';
            }

            \preg_match('/' . $regex . '/us', $str, $matches);

            return $matches[1];
        }
    }

}
