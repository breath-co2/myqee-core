<?php
namespace Core;

/**
 * 缩略图核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Captcha
{

    /**
     * 输出类型
     *
     * 可以是png,gif,jpeg
     *
     * @var string
     */
    protected static $image_type = 'png';

    // Config values
    public static $config = array
    (
    	'width'      => 150,
    	'height'     => 50,
    	'complexity' => 4,
    	'background' => '',
    	'fonts'      => array
                        (
                    		'fonts/DejaVuSerif.ttf',
                        ),
        'promote'    => false,
        'life'       => 1800
    );

    protected static $image;

    protected static $response = '';

    protected static $background_image;

    protected static $sessionname = '_img_captcha';

    protected static $valid_countname = '_img_captcha_valid_count';

    public static function valid($mycode, $delsession = false)
    {
        if (!($code = \Session::instance()->get(static::$sessionname)))
        {
            return 0;
        }
        else
        {
            if (\TIME-$code['time'] <= static::$config['life'] && $code['time'] > 0 && \strtoupper($mycode) == \strtoupper($code['code']) )
            {
                if ($delsession)\Session::instance()->delete(static::$sessionname, static::$valid_countname);

                return 1;
            }
            else
            {
                $errornum = (int)\Session::instance()->get(static::$valid_countname) + 1;
                \Session::instance()->set(static::$valid_countname, $errornum);

                return -$errornum;
            }
        }
    }

    /**
     * Gets or sets the number of valid Captcha responses for this session.
     *
     * @param   integer  new counter value
     * @param   boolean  trigger invalid counter (for internal use only)
     * @return  integer  counter value
     */
    public static function valid_count($new_count = null, $invalid = false)
    {
        // Pick the right session to use
        $session = static::$valid_countname;

        // Update counter
        if ( $new_count !== null )
        {
            $new_count = (int)$new_count;

            // Reset counter = delete session
            if ( $new_count < 1 )
            {
                \Session::instance()->delete($session);
            }
            // Set counter to new value
            else
            {
                \Session::instance()->set($session, (int)$new_count);
            }

            // Return new count
            return (int)$new_count;
        }

        // Return current count
        return (int)\Session::instance()->get($session);
    }

    /**
     * Checks whether user has been promoted after having given enough valid responses.
     *
     * @param   integer  valid response count threshold
     * @return  boolean
     */
    public static function promoted($threshold = null)
    {
        // Promotion has been disabled
        if (static::$config['promote'] === false)return false;

        // Use the config threshold
        if ($threshold===null)
        {
            $threshold = static::$config['promote'];
        }

        // Compare the valid response count to the threshold
        return (static::valid_count()>=$threshold);
    }

    /**
     * render image
     *
     * @param array $config
     * @return image
     */
    public static function render($config = false)
    {
        if (\is_array($config))
        {
            static::$config = \array_merge(static::$config, $config);
        }
        if ( empty(static::$response) )
        {
            static::generate_challenge();
        }
        // Creates static::$image
        static::image_create(static::$config['background']);

        // Add a random gradient
        if ( empty(static::$config['background']) )
        {
            $color1 = \imagecolorallocate(static::$image, \mt_rand(0, 100), \mt_rand(0, 100), \mt_rand(0, 100));
            $color2 = \imagecolorallocate(static::$image, \mt_rand(0, 100), \mt_rand(0, 100), \mt_rand(0, 100));
            static::image_gradient($color1, $color2);
        }

        // Add a few random circles
        for( $i = 0, $count = \mt_rand(10, static::$config['complexity'] * 3); $i < $count; $i ++ )
        {
            $color = \imagecolorallocatealpha(static::$image, \mt_rand(0, 255), \mt_rand(0, 255), \mt_rand(0, 255), \mt_rand(80, 120));
            $size = \mt_rand(5, static::$config['height']/3);
            \imagefilledellipse(static::$image, \mt_rand(0, static::$config['width']), \mt_rand(0, static::$config['height']), $size, $size, $color);
        }

        // Calculate character font-size and spacing
        $default_size = \min(static::$config['width'], static::$config['height'] * 2) / \strlen(static::$response);
        $spacing = (int)(static::$config['width'] * 0.9 / \strlen(static::$response));

        // Background alphabetic character attributes
        $color_limit = \mt_rand(96, 160);
        $chars = 'ABEFGJKLPQRTVY';

        // Draw each captcha character with varying attributes
        for($i = 0, $strlen = \strlen(static::$response); $i < $strlen; $i ++)
        {
            // Use different fonts if available
            $font = \Core::find_file('data',static::$config['fonts'][\array_rand(static::$config['fonts'])],false);

            $angle = \mt_rand(- 40, 20);
            // Scale the character size on image height
            $size = $default_size / 10 * \mt_rand(8, 12);
            if (!\function_exists('imageftbbox'))\Core::show_500(\__('function imageftbbox not exist.'));
            $box = \imageftbbox($size, $angle, $font, static::$response[$i]);

            // Calculate character starting coordinates
            $x = $spacing / 4 + $i * $spacing;
            $y = static::$config['height']/2 + ($box[2]-$box[5])/4;

            // Draw captcha text character
            // Allocate random color, size and rotation attributes to text
            $color = \imagecolorallocate(static::$image, \mt_rand(150, 255), \mt_rand(200, 255), \mt_rand(0, 255));

            // Write text character to image
            \imagefttext(static::$image, $size, $angle, $x, $y, $color, $font, static::$response[$i]);

            // Draw "ghost" alphabetic character
            $text_color = \imagecolorallocatealpha(static::$image, \mt_rand($color_limit + 8, 255), \mt_rand($color_limit + 8, 255), \mt_rand($color_limit + 8, 255), \mt_rand(70, 120));
            $char = \substr($chars, \mt_rand(0, 14), 1);
            \imagettftext(static::$image, $size * 1.4, \mt_rand(- 45, 45), ($x - (\mt_rand(5, 10))), ($y + (\mt_rand(5, 10))), $text_color, $font, $char);
        }

        // Output
        return static::image_render();
    }

    /**
     * Generates a new captcha challenge.
     *
     * @return  string  the challenge answer
     */
    protected static function generate_challenge()
    {
        // Complexity setting is used as character count
        static::$response = static::random(\max(1, static::$config['complexity']));
        \Session::instance()->set(static::$sessionname, array('code' => static::$response, 'time' => \TIME));
    }

    protected static function random($length = 8)
    {
        $pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';

        $str = '';

        $pool_size = \strlen($pool);

        for( $i = 0; $i < $length; $i++ )
        {
            $str .= \substr($pool, \mt_rand(0, $pool_size - 1), 1);
        }

        return $str;
    }

    /**
     * Creates an image resource with the dimensions specified in config.
     * If a background image is supplied, the image dimensions are used.
     *
     * @throws  Kohana_Exception  if no GD2 support
     * @param   string  path to the background image file
     * @return  void
     */
    protected function image_create($background = null)
    {
        // Check for GD2 support
        if ( ! \function_exists('imagegd2') ) \Core::show_500(\__('captcha.requires_GD2'));

        // Create a new image (black)
        static::$image = \imagecreatetruecolor(static::$config['width'], static::$config['height']);

        // Use a background image
        if ( !empty($background) )
        {
            /*
            // Create the image using the right function for the filetype
            $function = '\\imagecreatefrom' . static::image_type($filename);
            static::$background_image = $function($background);

            // Resize the image if needed
            if ( \imagesx(static::background_image) !== static::$config['width'] || \imagesy(static::background_image) !== static::$config['height'] )
            {
                \imagecopyresampled(static::image, static::background_image, 0, 0, 0, 0, static::$config['width'], static::$config['height'], \imagesx(static::background_image), \imagesy(static::background_image));
            }

            // Free up resources
            \imagedestroy(static::background_image);
            */
        }
    }

    /**
     * Fills the background with a gradient.
     *
     * @param   resource  gd image color identifier for start color
     * @param   resource  gd image color identifier for end color
     * @param   string    direction: 'horizontal' or 'vertical', 'random' by default
     * @return  void
     */
    protected function image_gradient($color1, $color2, $direction = null)
    {
        $directions = array('horizontal', 'vertical');

        // Pick a random direction if needed
        if ( !\in_array($direction, $directions) )
        {
            $direction = $directions[\array_rand($directions)];

            // Switch colors
            if ( \mt_rand(0, 1) === 1 )
            {
                $temp = $color1;
                $color1 = $color2;
                $color2 = $temp;
            }
        }

        // Extract RGB values
        $color1 = \imagecolorsforindex(static::$image, $color1);
        $color2 = \imagecolorsforindex(static::$image, $color2);

        // Preparations for the gradient loop
        $steps = ($direction === 'horizontal') ? static::$config['width'] : static::$config['height'];

        $r1 = ($color1['red'] - $color2['red']) / $steps;
        $g1 = ($color1['green'] - $color2['green']) / $steps;
        $b1 = ($color1['blue'] - $color2['blue']) / $steps;

        $i = null;
        if ( $direction === 'horizontal' )
        {
            $x1 = & $i;
            $y1 = 0;
            $x2 = & $i;
            $y2 = static::$config['height'];
        }
        else
        {
            $x1 = 0;
            $y1 = & $i;
            $x2 = static::$config['width'];
            $y2 = & $i;
        }

        // Execute the gradient loop
        for( $i = 0; $i <= $steps; $i ++ )
        {
            $r2 = $color1['red'] - \floor($i * $r1);
            $g2 = $color1['green'] - \floor($i * $g1);
            $b2 = $color1['blue'] - \floor($i * $b1);
            $color = \imagecolorallocate(static::$image, $r2, $g2, $b2);

            \imageline(static::$image, $x1, $y1, $x2, $y2, $color);
        }
    }

    /**
     * Returns the img html element or outputs the image to the browser.
     *
     * @param   boolean  html output
     * @return  mixed    html string or void
     */
    protected function image_render()
    {
        // Send the correct HTTP header
        \header("Cache-Control:no-cache,must-revalidate");
        \header("Pragma:no-cache");
        \header('Content-Type: image/'.static::$image_type);
        \header("Connection:close");

        // Pick the correct output function
        $function = '\\image'.static::$image_type;
        $function(static::$image);

        // Free up resources
        \imagedestroy(static::$image);
    }

}