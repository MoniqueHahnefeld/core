<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Kamil Kuzminski 2011 
 * @author     Kamil Kuzminski <http://qzminski.com> 
 * @package    IsotopeWishlist 
 * @license    GNU/LGPL 
 * @filesource
 */


/**
 * Class ModuleIsotopeWishlist 
 *
 * @copyright  Kamil Kuzminski 2011 
 * @author     Kamil Kuzminski <http://qzminski.com> 
 * @package    Controller
 */
class ModuleIsotopeWishlist extends ModuleIsotope
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_iso_wishlist';

	/**
	 * Disable caching of the frontend page if this module is in use.
	 * @var bool
	 */
	protected $blnDisableCache = true;


	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ISOTOPE ECOMMERCE: WISHLIST ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = $this->Environment->script.'?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}
		
		$this->import('Isotope');
		$this->import('IsotopeWishlist');
		$this->IsotopeWishlist->initializeWishlist((int) $this->Isotope->Config->id, (int) $this->Isotope->Config->store_id);
		
		return parent::generate();
	}


	/**
	 * Generate module
	 */
	protected function compile()
	{
		$arrProducts = $this->IsotopeWishlist->getProducts();

		if (!count($arrProducts))
		{
			$this->Template->empty = true;
			$this->Template->message = $GLOBALS['TL_LANG']['MSC']['noItemsInWishlist'];
			return;
		}

		$objTemplate = new IsotopeTemplate($this->iso_cart_layout);

		global $objPage;
		$strUrl = $this->generateFrontendUrl($objPage->row());

		$blnReload = false;
		$arrQuantity = $this->Input->post('quantity');
		$arrProductData = array();

		// Surcharges must be initialized before getProducts() to apply tax_id to each product
		$arrSurcharges = array();
		foreach( $this->IsotopeWishlist->getSurcharges() as $arrSurcharge )
		{
			$arrSurcharges[] = array
			(
				'label'				=> $arrSurcharge['label'],
				'price'				=> $this->Isotope->formatPriceWithCurrency($arrSurcharge['price']),
				'total_price'		=> $this->Isotope->formatPriceWithCurrency($arrSurcharge['total_price']),
				'tax_id'			=> $arrSurcharge['tax_id'],
			);
		}

		$arrProducts = $this->IsotopeWishlist->getProducts();

		foreach( $arrProducts as $i => $objProduct )
		{
			// Remove product from wishlist
			if ($this->Input->get('remove') == $objProduct->cart_id && $this->IsotopeWishlist->deleteProduct($objProduct))
			{
				$this->redirect((strlen($this->Input->get('referer')) ? base64_decode($this->Input->get('referer', true)) : $strUrl));
			}

			// Update wishlist data if form has been submitted
			elseif ($this->Input->post('FORM_SUBMIT') == 'iso_wishlist_update' && is_array($arrQuantity))
			{
				$blnReload = true;
				$this->IsotopeWishlist->updateProduct($objProduct, array('product_quantity'=>$arrQuantity[$objProduct->cart_id]));
				continue; // no need to generate $arrProductData, we reload anyway
			}

			// No need to generate product data if we reload anyway
			elseif ($blnReload)
			{
				continue;
			}

			$arrProductData[] = array_merge($objProduct->getAttributes(), array
			(
				'id'				=> $objProduct->id,
				'image'				=> $objProduct->images->main_image,
				'link'				=> $objProduct->href_reader,
				'original_price'	=> $this->Isotope->formatPriceWithCurrency($objProduct->original_price),
				'price'				=> $this->Isotope->formatPriceWithCurrency($objProduct->price),
				'total_price'		=> $this->Isotope->formatPriceWithCurrency($objProduct->total_price),
				'tax_id'			=> $objProduct->tax_id,
				'quantity'			=> $objProduct->quantity_requested,
				'cart_item_id'		=> $objProduct->cart_id,
				'product_options'	=> $objProduct->getOptions(),
				'remove_link'		=> ampersand($strUrl . ($GLOBALS['TL_CONFIG']['disableAlias'] ? '&' : '?') . 'remove='.$objProduct->cart_id.'&referer='.base64_encode($this->Environment->request)),
				'remove_link_text'  => $GLOBALS['TL_LANG']['MSC']['removeProductLinkText'],
				'remove_link_title' => sprintf($GLOBALS['TL_LANG']['MSC']['removeProductLinkTitle'], $objProduct->name),
				'class'				=> 'row_' . $i . ($i%2 ? ' even' : ' odd') . ($i==0 ? ' row_first' : ''),
			));
		}

		$blnInsufficientSubtotal = ($this->Isotope->Config->cartMinSubtotal > 0 && $this->Isotope->Config->cartMinSubtotal > $this->IsotopeWishlist->subTotal) ? true : false;

		// Reload if the "checkout" button has been submitted and minimum order total is reached
		if ($blnReload && $this->Input->post('checkout') != '' && $this->iso_wishlist_jumpTo && !$blnInsufficientSubtotal)
		{
			$this->redirect($this->generateFrontendUrl($this->Database->execute("SELECT * FROM tl_page WHERE id={$this->iso_wishlist_jumpTo}")->fetchAssoc()));
		}

		// Otherwise, just reload the page
		elseif ($blnReload)
		{
			$this->reload();
		}

		if (count($arrProductData))
		{
			$arrProductData[count($arrProductData)-1]['class'] .= ' row_last';
		}

		// HOOK for adding additional forms into the template
		if (isset($GLOBALS['ISO_HOOKS']['compileWishlist']) && is_array($GLOBALS['ISO_HOOKS']['compileWishlist']))
		{
			foreach ($GLOBALS['ISO_HOOKS']['compileWishlist'] as $name => $callback)
			{
				$this->import($callback[0]);
				$strForm = $this->$callback[0]->$callback[1]($this, $objTemplate, $arrProductData, $arrSurcharges);

				if ($strForm !== false)
				{
				 	$arrForms[$name] = $strForm;
				}
			}
		}

		$objTemplate->hasError = $blnInsufficientSubtotal ? true : false;
		#$objTemplate->minSubtotalError = sprintf($GLOBALS['TL_LANG']['ERR']['cartMinSubtotal'], $this->Isotope->formatPriceWithCurrency($this->Isotope->Config->cartMinSubtotal));
		$objTemplate->formId = 'iso_wishlist_update';
		$objTemplate->formSubmit = 'iso_wishlist_update';
		$objTemplate->summary = $GLOBALS['ISO_LANG']['MSC']['wishlistSummary'];
		$objTemplate->action = $this->Environment->request;
		$objTemplate->products = $arrProductData;
		$objTemplate->cartJumpTo = $this->iso_cart_jumpTo ? $this->generateFrontendUrl($this->Database->execute("SELECT * FROM tl_page WHERE id={$this->iso_cart_jumpTo}")->fetchAssoc()) : '';
		$objTemplate->cartLabel = $GLOBALS['TL_LANG']['MSC']['wishlistBT'];
		$objTemplate->checkoutJumpToLabel = $GLOBALS['TL_LANG']['MSC']['sendWishlistBT'];
		$objTemplate->checkoutJumpTo = ($this->iso_wishlist_jumpTo && !$blnInsufficientSubtotal) ? $this->generateFrontendUrl($this->Database->execute("SELECT * FROM tl_page WHERE id={$this->iso_wishlist_jumpTo}")->fetchAssoc()) : '';

		$objTemplate->subTotalLabel = $GLOBALS['TL_LANG']['MSC']['subTotalLabel'];
		$objTemplate->grandTotalLabel = $GLOBALS['TL_LANG']['MSC']['grandTotalLabel'];
		$objTemplate->subTotalPrice = $this->Isotope->formatPriceWithCurrency($this->IsotopeWishlist->subTotal);
		$objTemplate->grandTotalPrice = $this->Isotope->formatPriceWithCurrency($this->IsotopeWishlist->grandTotal);
		// @todo make a module option.
		$objTemplate->showOptions = false;
		$objTemplate->surcharges = $arrSurcharges;
		$objTemplate->forms = $arrForms;

		$this->Template->empty = false;
		$this->Template->wishlist = $objTemplate->parse();
	}
}

?>