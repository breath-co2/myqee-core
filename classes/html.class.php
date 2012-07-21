<?php
namespace Core;

/**
 * HTML输出核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class html
{

    /**
     * @var  array  preferred order of attributes
     */
    public static $attribute_order = array
    (
        'action',
        'method',
        'type',
        'id',
        'name',
        'value',
        'href',
        'src',
        'width',
        'height',
        'cols',
        'rows',
        'size',
        'maxlength',
        'rel',
        'media',
        'accept-charset',
        'accept',
        'tabindex',
        'accesskey',
        'alt',
        'title',
        'class',
        'style',
        'selected',
        'checked',
        'readonly',
        'disabled',
        'max',
        'min',
    );

    /**
     * @var  boolean  automatically target external URLs to a new window?
     */
    public static $windowed_urls = false;

    /**
     * Convert special characters to HTML entities. All untrusted content
     * should be passed through this method to prevent XSS injections.
     *
     * echo static::chars($username);
     *
     * @param   string   string to convert
     * @param   boolean  encode existing entities
     * @return  string
     */
    public static function chars($value, $double_encode = true)
    {
        return \htmlspecialchars((string)$value, \ENT_QUOTES, \Core::$charset, $double_encode);
    }

    /**
     * Convert all applicable characters to HTML entities. All characters
     * that cannot be represented in HTML with the current character set
     * will be converted to entities.
     *
     * echo static::entities($username);
     *
     * @param   string   string to convert
     * @param   boolean  encode existing entities
     * @return  string
     */
    public static function entities($value, $double_encode = true)
    {
        return \htmlentities((string)$value, \ENT_QUOTES, \Core::$charset, $double_encode);
    }

    /**
     * Create HTML link anchors. Note that the title is not escaped, to allow
     * HTML elements within links (images, etc).
     *
     * echo static::anchor('/user/profile', 'My Profile');
     *
     * @param   string  URL or URI string
     * @param   string  link text
     * @param   array   HTML anchor attributes
     * @param   string  use a specific protocol
     * @return  string
     * @uses	URL::base
     * @uses	URL::site
     * @uses	\html::attributes
     */
    public static function anchor($uri, $title = null, array $attributes = null, $protocol = null)
    {
        if ( $title === null )
        {
            // Use the URI as the title
            $title = $uri;
        }
        if ( $uri === '' )
        {
            // Only use the base URL
            $uri = \Core::url()->base(false, $protocol);
        }
        else
        {
            if ( \strpos($uri, '://') !== false )
            {
                if ( static::$windowed_urls === true && empty($attributes['target']) )
                {
                    // Make the link open in a new window
                    $attributes['target'] = '_blank';
                }
            }
            elseif ( $uri[0] !== '#' && $uri[0] != '/' )
            {
                // Make the URI absolute for non-id anchors
                $uri = \Core::url()->site($uri, $protocol);
            }
        }
        // Add the sanitized link to the attributes
        $attributes['href'] = $uri;
        return '<a' . static::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an HTML anchor to a file. Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     * echo static::file_anchor('media/doc/user_guide.pdf', 'User Guide');
     *
     * @param   string  name of file to link to
     * @param   string  link text
     * @param   array   HTML anchor attributes
     * @param   string  non-default protocol, eg: ftp
     * @return  string
     * @uses	URL::base
     * @uses	\html::attributes
     */
    public static function file_anchor($file, $title = null, array $attributes = null, $protocol = null)
    {
        if ( $title === null )
        {
            // Use the file name as the title
            $title = \basename($file);
        }
        // Add the file link to the attributes
        $attributes['href'] = \Core::url()->base(false, $protocol) . $file;
        return '<a' . static::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Generates an obfuscated version of a string. Text passed through this
     * method is less likely to be read by web crawlers and robots, which can
     * be helpful for spam prevention, but can prevent legitimate robots from
     * reading your content.
     *
     * echo static::obfuscate($text);
     *
     * @param   string  string to obfuscate
     * @return  string
     * @since   3.0.3
     */
    public static function obfuscate($string)
    {
        $safe = '';
        foreach ( \str_split($string) as $letter )
        {
            switch ( \rand(1, 3) )
            {
                // HTML entity code
                case 1 :
                    $safe .= '&#' . \ord($letter) . ';';
                    break;
                // Hex character code
                case 2 :
                    $safe .= '&#x' . \dechex(\ord($letter)) . ';';
                    break;
                // Raw (no) encoding
                case 3 :
                    $safe .= $letter;
            }
        }
        return $safe;
    }

    /**
     * Generates an obfuscated version of an email address. Helps prevent spam
     * robots from finding email addresses.
     *
     * echo static::email($address);
     *
     * @param   string  email address
     * @return  string
     * @uses	\html::obfuscate
     */
    public static function email($email)
    {
        // Make sure the at sign is always obfuscated
        return \str_replace('@', '&#64;', static::obfuscate($email));
    }

    /**
     * Creates an email (mailto:) anchor. Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     * echo static::mailto($address);
     *
     * @param   string  email address to send to
     * @param   string  link text
     * @param   array   HTML anchor attributes
     * @return  string
     * @uses	\html::email
     * @uses	\html::attributes
     */
    public static function mailto($email, $title = null, array $attributes = null)
    {
        // Obfuscate email address
        $email = static::email($email);
        if ( $title === null )
        {
            // Use the email address as the title
            $title = $email;
        }
        return '<a href="&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email . '"' . static::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates a style sheet link element.
     *
     * echo static::style('media/css/screen.css');
     *
     * @param   string  file name
     * @param   array   default attributes
     * @param   boolean  include the index page
     * @return  string
     * @uses	URL::base
     * @uses	\html::attributes
     */
    public static function style($file, array $attributes = null, $index = false)
    {
        if ( \strpos($file, '://') === false )
        {
            // Add the base URL
            $file = \Core::url()->base($index) . $file;
        }
        // Set the stylesheet link
        $attributes['href'] = $file;
        // Set the stylesheet rel
        $attributes['rel'] = 'stylesheet';
        // Set the stylesheet type
        $attributes['type'] = 'text/css';
        return '<link' . static::attributes($attributes) . ' />';
    }

    /**
     * Creates a script link.
     *
     * echo static::script('media/js/jquery.min.js');
     *
     * @param   string   file name
     * @param   array	default attributes
     * @param   boolean  include the index page
     * @return  string
     * @uses	URL::base
     * @uses	\html::attributes
     */
    public static function script($file, array $attributes = null, $index = false)
    {
        if ( \strpos($file, '://') === false )
        {
            // Add the base URL
            $file = \Core::url()->base($index) . $file;
        }
        // Set the script link
        $attributes['src'] = $file;
        // Set the script type
        $attributes['type'] = 'text/javascript';
        return '<script' . static::attributes($attributes) . '></script>';
    }

    /**
     * Creates a image link.
     *
     * echo static::image('media/img/logo.png', array('alt' => 'My Company'));
     *
     * @param   string   file name
     * @param   array	default attributes
     * @return  string
     * @uses	URL::base
     * @uses	\html::attributes
     */
    public static function image($file, array $attributes = null, $index = false)
    {
        if ( \strpos($file, '://') === false )
        {
            // Add the base URL
            $file = \Core::url()->base($index) . $file;
        }
        // Add the image link
        $attributes['src'] = $file;
        return '<img' . static::attributes($attributes) . ' />';
    }

    /**
     * Compiles an array of HTML attributes into an attribute string.
     * Attributes will be sorted using static::$attribute_order for consistency.
     *
     * echo '<div'.static::attributes($attrs).'>'.$content.'</div>';
     *
     * @param   array   attribute list
     * @return  string
     */
    public static function attributes(array $attributes = null)
    {
        if ( empty($attributes) ) return '';
        $sorted = array();
        foreach ( static::$attribute_order as $key )
        {
            if ( isset($attributes[$key]) )
            {
                // Add the attribute to the sorted list
                $sorted[$key] = $attributes[$key];
            }
        }
        // Combine the sorted attributes
        $attributes = $sorted + $attributes;
        $compiled = '';
        foreach ( $attributes as $key => $value )
        {
            if ( $value === null )
            {
                // Skip attributes that have null values
                continue;
            }
            // Add the attribute value
            $compiled .= ' ' . $key . '="' . \str_replace('"', '&quot;', $value) . '"';
        }
        return $compiled;
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 512 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source http://gravatar.com/site/implement/images/php/
     */
    public static function gravatar( $email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array() )
    {
        $url = 'http://www.gravatar.com/avatar/';
        $url .= md5( strtolower( trim( $email ) ) );
        $url .= "?s=$s&d=$d&r=$r";
        if ( $img ) {
            $url = '<img src="' . $url . '"';
            foreach ( $atts as $key => $val )
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }

        return $url;
    }

}