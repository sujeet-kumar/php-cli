<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeetkv/php-cli
 */

namespace SujeetKV\PhpCli;

/**
 * StdIO class
 */
class StdIO
{
    const CR = "\r";
    const LF = "\n";
    const TAB = "\t";
    const EOL = PHP_EOL;
    
    /** @var resource STDOUT */
    protected $stdout = null;
    
    /** @var resource STDERR */
    protected $stderr = null;
    
    /** @var resource STDIN */
    protected $stdin = null;
    
    /** @var bool readline supported */
    protected $hasReadline = false;
    
    /** @var bool color supported */
    protected $hasColorSupport = false;
    
    /** @var bool color enabled */
    protected $colorEnabled = true;
    
    /** @var int cli width */
    protected $cliWidth = 75;
    
    /** @var int cli height */
    protected $cliHeight = 50;
    
    /** @var string text color */
    protected $foregroundColor = null;
    
    /** @var string background color */
    protected $backgroundColor = null;
    
    /** @var array text attribute */
    protected $textAttribute = array();
    
    /** @var array text colors */
    protected $foregroundColors = array(
        'black'         => '0;30',
        'dark_gray'     => '1;30',
        'blue'          => '0;34',
        'light_blue'    => '1;34',
        'green'         => '0;32',
        'light_green'   => '1;32',
        'cyan'          => '0;36',
        'light_cyan'    => '1;36',
        'red'           => '0;31',
        'light_red'     => '1;31',
        'purple'        => '0;35',
        'light_purple'  => '1;35',
        'brown'         => '0;33',
        'yellow'        => '1;33',
        'light_gray'    => '0;37',
        'white'         => '1;37'
    );
    
    /** @var array background colors */
    protected $backgroundColors = array(
        'black'         => '40',
        'red'           => '41',
        'green'         => '42',
        'yellow'        => '43',
        'blue'          => '44',
        'magenta'       => '45',
        'cyan'          => '46',
        'light_gray'    => '47'
    );
    
    /** @var array text attributes */
    protected $textAttributes = array(
        'reset'         => '0',
        'bold'          => '1',
        'low_intensity' => '2',
        'underline'     => '4',
        'blink'         => '5',
        'invert_color'  => '7',
        'invisible'     => '8'
    );
    
    /**
     * Initialize StdIO class
     */
    public function __construct() {
        $this->stdout = @fopen('php://stdout', 'w');
        $this->stderr = @fopen('php://stderr', 'w');
        $this->stdin = @fopen('php://stdin', 'r');
        
        if (!$this->stdout) {
            throw new CliException('Could not open output stream.');
        }
        
        if (!$this->stdin) {
            throw new CliException('Could not open input stream.');
        }
        
        $this->hasReadline = (extension_loaded('readline') && function_exists('readline'));
        
        if (self::isWindows()) {
            $this->hasColorSupport = ('10.0.10586' === PHP_WINDOWS_VERSION_MAJOR.'.'.PHP_WINDOWS_VERSION_MINOR.'.'.PHP_WINDOWS_VERSION_BUILD
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM'));
        } else {
            $this->hasColorSupport = (function_exists('posix_isatty') && @posix_isatty($this->stream));
        }
        
        $width = (int) (isset($_SERVER['COLUMNS']) ? $_SERVER['COLUMNS'] : @exec('tput cols'));
        if ($width) {
            $this->cliWidth = $width;
        }
        
        $height = (int) (isset($_SERVER['ROWS']) ? $_SERVER['ROWS'] : @exec('tput lines'));
        if ($height) {
            $this->cliHeight = $height;
        }
    }
    
    /**
     * Read line from console
     * 
     * @param string $prompt
     */
    public function read($prompt = '') {
        if ($this->hasReadline) {
            $line = readline($prompt);
        } else {
            $this->write($prompt);
            $line = fgets($this->stdin);
            $line = (false === $line || '' === $line) ? false : rtrim($line);
        }
        return $line;
    }
    
    /**
     * Write text to console
     * 
     * @param mixed $text
     * @param int $newlines
     * @param bool $toError
     */
    public function write($text, $newlines = 0, $toError = false) {
        if (is_array($text)) {
            $text = implode(self::EOL, $text);
        }
        if ($this->foregroundColor || $this->backgroundColor || $this->textAttribute) {
            $text = $this->colorizeText($text, $this->foregroundColor, $this->backgroundColor, $this->textAttribute);
            $this->foregroundColor = $this->backgroundColor = null;
            $this->textAttribute = array();
        }
        return fwrite((($toError && $this->stderr) ? $this->stderr : $this->stdout), $text . str_repeat(self::EOL, $newlines));
    }
    
    /**
     * Write text to console and add a new line at end
     * 
     * @param mixed $text
     * @param bool $toError
     */
    public function writeln($text, $toError = false) {
        return $this->write($text, 1, $toError);
    }
    
    /**
     * Overwrite the current line
     * 
     * @param mixed $text
     */
    public function overwrite($text) {
        $this->write("\x0D");// Move the cursor to the beginning of the line
        $this->write("\x1B[2K");// Erase the line
        return $this->write($text);
    }
    
    /**
     * Set color for next write operation
     * 
     * @param string $foregroundColor
     * @param string $backgroundColor
     */
    public function setColor($foregroundColor = null, $backgroundColor = null) {
        $this->foregroundColor = $foregroundColor;
        $this->backgroundColor = $backgroundColor;
        return $this;
    }
    
    /**
     * Set text attributes for next write operation
     * 
     * @param array $textAttributes
     */
    public function setAttr($textAttributes = array()) {
        $this->textAttribute = $textAttributes;
        return $this;
    }
    
    /**
     * Get colored text
     * 
     * @param string $text
     * @param string $foregroundColor
     * @param string $backgroundColor
     * @param string $attributes
     */
    public function colorizeText($text = '', $foregroundColor = null, $backgroundColor = null, $attributes = array()) {
        $str = '';
        $attrChanged = false;
        if ($text !== '' && $this->hasColorSupport && $this->colorEnabled) {
            if (!empty($foregroundColor) && isset($this->foregroundColors[$foregroundColor])) {
                $str .= "\033[" . $this->foregroundColors[$foregroundColor] . "m";
                $attrChanged = true;
            }
            if (!empty($backgroundColor) && isset($this->backgroundColors[$backgroundColor])) {
                $str .= "\033[" . $this->backgroundColors[$backgroundColor] . "m";
                $attrChanged = true;
            }
            if (!empty($attributes) && is_array($attributes)) {
                foreach ($attributes as $attr) {
                    if (isset($this->textAttributes[$attr])) {
                        $str .= "\033[" . $this->textAttributes[$attr] . "m";
                        $attrChanged = true;
                    }
                }
            }
        }
        $str .= $text;
        if ($attrChanged) {
            $str .= "\033[" . $this->textAttributes['reset'] . "m";
        }
        return $str;
    }
    
    /**
     * Check color support
     */
    public function hasColorSupport() {
        return $this->hasColorSupport;
    }
    
    /**
     * Check color enabled
     */
    public function colorEnabled() {
        return $this->colorEnabled;
    }
    
    /**
     * Enable or Disable color output
     * 
     * @param bool $colorEnabled
     */
    public function enableColor($colorEnabled = true) {
        $this->colorEnabled = $colorEnabled;
    }
    
    /**
     * Check readline support
     */
    public function hasReadline() {
        return $this->hasReadline;
    }
    
    /**
     * Get foreground colors
     */
    public function getForegroundColors() {
        return array_keys($this->foregroundColors);
    }
    
    /**
     * Get background colors
     */
    public function getBackgroundColors() {
        return array_keys($this->backgroundColors);
    }
    
    /**
     * Get text attribute
     */
    public function getTextAttributes() {
        return array_keys($this->textAttributes);
    }
    
    /**
     * Get cli width in char count
     */
    public function getWidth() {
        return $this->cliWidth;
    }
    
    /**
     * Get cli height in char count
     */
    public function getHeight() {
        return $this->cliHeight;
    }
    
    /**
     * Print blank newlines
     * 
     * @param int $count
     */
    public function ln($count = 1) {
        $this->write('', $count);
        return $this;
    }
    
    /**
     * Print horizontal rule
     * 
     * @param int $size
     * @param string $char
     */
    public function hr($size = 0, $char = '-') {
        $this->writeln(str_repeat($char, ($size ? $size : $this->cliWidth)));
        return $this;
    }
    
    /**
     * Clear non-windows cli
     */
    public function clear() {
        if (!self::isWindows()) {
            shell_exec('clear');
        }
        return $this;
    }
    
    /**
     * Check if execution environment is cli
     */
    public static function isCli() {
        return (php_sapi_name() == 'cli' or defined('STDIN'));
    }
    
    /**
     * Check for windows os
     */
    public static function isWindows() {
        return (DIRECTORY_SEPARATOR === '\\');
    }
    
    /**
     * Get input stream
     */
    public function getInputStream() {
        return $this->stdin;
    }
    
    /**
     * Get output stream
     * 
     * @param bool $stdout
     */
    public function getOutputStream($stdout = true) {
        return $stdout ? $this->stdout : $this->stderr;
    }
    
    public function __destruct() {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }
        if (is_resource($this->stderr)) {
            fclose($this->stderr);
        }
    }
}
