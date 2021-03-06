<?php defined('SYSPATH') or die('No direct script access.');
/**
 * [Gravatar's](http://en.gravatar.com) are universal avatars available to all web sites and services.
 * Users must register their email addresses with Gravatar before their avatars will be
 * usable with this module. Users with gravatars can have a default image of your selection.
 * 
 * @package     Gravatar for Kohana PHP 3
 * @author      The Kohana Team
 * @copyright   Copyright (c) 2009-2010 Kohana
 * @version     3.1.0
 * @license     Kohana License http://kohanaframework.org/license
 */
class Kohana_Gravatar {

	const GRAVATAR_G   = 'G';
	const GRAVATAR_PG  = 'PG';
	const GRAVATAR_R   = 'R';
	const GRAVATAR_X   = 'X';

	static public $default_icon = array(
		'404',       // do not load any image if none is associated with the email hash, instead return an HTTP 404 (File Not Found) response
		'mm',        // (mystery-man) a simple, cartoon-style silhouetted outline of a person (does not vary by email hash)
		'identicon', // a geometric pattern based on an email hash
		'monsterid', // a generated 'monster' with different colors, faces, etc
		'wavatar'    // generated faces with differing features and backgrounds
		);

	/**
	 * Static instances
	 *
	 * @var     array
	 * @static
	 * @access  protected
	 */
	static protected $_instances = array();

	/**
	 * Instance constructor pattern
	 *
	 * @param   string       email   the Gravatar to fetch for email address
	 * @param   string       config  the name of the configuration grouping
	 * @param   array        config  array of key value configuration pairs
	 * @return  Gravatar
	 * @access  public
	 * @static
	 */
	public static function instance($email, $config = NULL)
	{
		// Create an instance checksum
		$config_checksum = sha1(serialize($config));

		// Load the Gravatar instance for email and configuration
		if ( ! isset(self::$_instances[$email][$config_checksum]))
		{
			self::$_instances[$email][$config_checksum] = new Gravatar($email, $config);
		}

		// Return a the instance
		return self::$_instances[$email][$config_checksum];
	}

	/**
	 * Configuration for this library, merged with the static config
	 *
	 * @var     array
	 * @access  protected
	 */
	protected $_config;

	/**
	 * Additional attributes to add to the image
	 *
	 * @var     array
	 */
	public $attributes = array();

	/**
	 * The email address of the user
	 *
	 * @var     string
	 */
	public $email;

	/**
	 * Gravatar constructor
	 *
	 * @param   string       email   the Gravatar to fetch for email address
	 * @param   string       config  the name of the configuration grouping
	 * @param   array        config  array of key value configuration pairs
	 * @access  public
	 * @throws  Gravatar_Exception
	 */
	protected function __construct($email, $config = NULL)
	{
		// Set the email address
		$this->email = $email;

		if (empty($config))
		{
			$this->_config = Kohana::config('gravatar.default');
		}
		elseif (is_array($config))
		{
			// Setup the configuration
			$config += Kohana::config('gravatar.default');
			$this->_config = $config;
		}
		elseif (is_string($config))
		{
			if ($config = Kohana::config('gravatar.'.$config) === NULL)
			{
				throw new Gravatar_Exception('Gravatar.__construct() , Invalid configuration group name : :config', array(':config' => $config));
			}

			$this->_config = $config + Kohana::config('gravatar.default');
		}
	}

	/**
	 * Handles this object being cast to string
	 *
	 * @return  string       the resulting Gravatar
	 * @access  public
	 * @author  Sam Clark
	 */
	public function __toString()
	{
		return (string) $this->render();
	}

	/**
	 * Accessor method for setting size of gravatar
	 *
	 * @param   int          size  the size of the gravatar image in pixels
	 * @return  self
	 */
	public function size($size = NULL)
	{
		if ($size === NULL)
		{
			return $this->_config['size'];
		}
		else
		{
			$this->_config['size'] = (int) $size;
			return $this;
		}
	}

	/**
	 * Accessor method for the rating of the gravatar
	 *
	 * @param   string       rating  the rating of the gravatar
	 * @return  self
	 */
	public function rating($rating = NULL)
	{
		$rating = strtoupper($rating);

		if ($rating === NULL)
		{
			return $this->_config['rating'];
		}
		else
		{
			if (in_array($rating, array(Gravatar::GRAVATAR_G, Gravatar::GRAVATAR_PG, Gravatar::GRAVATAR_R, Gravatar::GRAVATAR_X)))
			{
				$this->_config['rating'] = $rating;
			}
			else
			{
				throw new Gravatar_Exception('The rating value :rating is not valid. Please use G, PG, R or X. Also available through Class constants', array(':rating' => $rating));
			}
		}

		return $this;
	}

	/**
	 * Accessor method for setting the default image if the supplied email address or rating return an empty result
	 *
	 * @param   string       url  the url of the image to use instead of the Gravatar
	 * @return  self
	 */
	public function default_image($url = NULL)
	{
		if ($url === NULL)
		{
			return $this->_config['default_image'];
		}
		else
		{
			if (in_array($url, Gravatar::$default_icon) || validate::url($url))
			{
				$this->_config['default_image'] = $url;
			}
			else
			{
				throw new Gravatar('The url : :url is improperly formatted', array(':url' => $url));
			}
		}

		return $this;
	}

	/**
	 * Renders the Gravatar using supplied configuration and attributes. Can use custom view.
	 *
	 * @param   string       view  [Optional] a kohana PHP
	 * @param   string       email  [Optional] the valid email of a Gravatar user
	 * @return  string       the rendered Gravatar output
	 * @access  public
	 * @author  Sam Clark
	 */
	public function render($view = FALSE, $email = NULL)
	{
		if ($email !== NULL)
		{
			$this->email = $email;
		}

		$data = array('attr' => array(), 'src' => $this->_generate_url());

		if ($this->attributes)
		{
		    $data['attr'] += $this->attributes;
		}

		$data['attr']['alt'] = $this->_process_alt();

		if ( ! $view)
		{
			return new View($this->_config['view'], $data);
		}
		else
		{
			return new View($view, $data);
		}
	}

	/**
	 * Process the alt attribute output
	 *
	 * @return  string
	 * @access  protected
	 * @author  Sam Clark
	 */
	protected function _process_alt()
	{
		$keys = array
		(
			'{$email}'      => $this->email,
			'{$size}'       => $this->_config['size'],
			'{$rating}'     => $this->_config['rating'],
		);

		if ($this->_config['alt'])
		{
			$alt = strtr($this->_config['alt'], $keys);
		}
		else
		{
			$alt = FALSE;
		}

		return $alt;
	}

	/**
	 * Creates the Gravatar URL based on the configuration and email
	 *
	 * @return  string       the resulting Gravatar URL
	 * @access  protected
	 * @author  Sam Clark
	 */
	protected function _generate_url()
	{
		$string = $this->_config['service'].
			'?gravatar_id='.md5($this->email).
			'&s='.$this->_config['size'].
			'&r='.$this->_config['rating'];

		if ( ! empty($this->_config['default_image']))
		{
			$string .= '&d='.$this->_config['default_image'];
		}
		
		return $string;
	}
}