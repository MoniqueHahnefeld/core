<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2008-2012 Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://www.isotopeecommerce.com
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Class ContentIsotope
 *
 * Provide methods to handle Isotope content elements.
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 */
abstract class ContentIsotope extends \ContentElement
{

	/**
	 * Isotope object
	 * @var object
	 */
	protected $Isotope;


	/**
	 * Initialize the content element
	 * @param object
	 */
	public function __construct($objElement)
	{
		parent::__construct($objElement);

		if (TL_MODE == 'FE')
		{
			$this->import('Isotope\Isotope', 'Isotope');

			if (FE_USER_LOGGED_IN === true)
			{
				$this->import('FrontendUser', 'User');
			}

			// Load Isotope javascript and css
			$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/isotope/assets/isotope.min.js';
			$GLOBALS['TL_CSS'][] = 'system/modules/isotope/assets/isotope.min.css';
		}
	}
}
