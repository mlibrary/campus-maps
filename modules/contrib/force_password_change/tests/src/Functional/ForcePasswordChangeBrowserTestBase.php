<?php

namespace Drupal\Tests\force_password_change\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Provides some helper functions for functional tests.
 */
class ForcePasswordChangeBrowserTestBase extends BrowserTestBase
{
	public function assertStatusCodeEquals($statusCode)
	{
		$this->assertSession()->statusCodeEquals($statusCode);
	}

	public function assertElementExists($selector)
	{
		$this->assertSession()->elementExists('css', $selector);
	}

	public function assertElementNotExists($selector)
	{
		$this->assertSession()->elementNotExists('css', $selector);
	}

	public function assertElementExistsXpath($selector)
	{
		$this->assertSession()->elementExists('xpath', $selector);
	}

	public function assertElementNotExistsXpath($selector)
	{
		$this->assertSession()->elementNotExists('xpath', $selector);
	}

	public function assertElementAttributeExists($selector, $attribute)
	{
		$this->assertSession()->elementAttributeExists('css', $selector, $attribute);
	}

	public function assertElementAttributeContains($selector, $attribute, $value)
	{
		$this->assertSession()->elementAttributeContains('css', $selector, $attribute, $value);
	}

	public function getHtml()
	{
		$this->assertEquals('', $this->getSession()->getPage()->getHTML());
	}

	public function assertRadioExists($htmlID)
	{
		if(!preg_match('/^#/', $htmlID))
		{
			$htmlID = '#' . $htmlID;
		}

		$this->assertElementExists($htmlID);
		$this->assertElementAttributeExists($htmlID, 'type');
		$this->assertElementAttributeContains($htmlID, 'type', 'radio');
	}

	public function selectRadio($htmlID)
	{
		if(preg_match('/^#/', $htmlID))
		{
			$htmlID = substr($htmlID, 1);
		}

		$radio = $this->getSession()->getPage()->findField($htmlID);
		$name = $radio->getAttribute('name');
		$option = $radio->getAttribute('value');
		$this->getSession()->getPage()->selectFieldOption($name, $option);
	}

	public function assertRadioSelected($htmlID)
	{
		if(!preg_match('/^#/', $htmlID))
		{
			$htmlID = '#' . $htmlID;
		}

		$selected_radio = $this->getSession()->getPage()->find('css', 'input[type="radio"]:checked' . $htmlID);

		if(!$selected_radio)
		{
			throw new \Exception('Radio button with ID ' . $htmlID . ' is not selected');
		}
	}

	public function assertSelectExists($htmlID)
	{
		$this->assertSession()->selectExists($htmlID);
	}

	public function selectSelectOption($selectElementHtmlID, $value)
	{
		if(preg_match('/^#/', $selectElementHtmlID))
		{
			$selectElementHtmlID = substr($selectElementHtmlID, 1);
		}

		$this->getSession()->getDriver()->selectOption(
			'//select[@id="' . $selectElementHtmlID . '"]',
			$value
		);
	}

	public function assertSelectOption($selectElementHtmlID, $value)
	{
		if(preg_match('/^#/', $selectElementHtmlID))
		{
			$selectElementHtmlID = substr($selectElementHtmlID, 1);
		}

		$selected_option = $this->getSession()->getPage()->find('xpath', '//select[@id="' . $selectElementHtmlID . '"]/option[@value="' . $value . '" and @selected="selected"]');

		if(!$selected_option)
		{
			throw new \Exception('Select ' . $selectElementHtmlID . ' does not have value "' . $value . '" selected');
		}
	}

	public function assertCheckboxExists($htmlID)
	{
		if(!preg_match('/^#/', $htmlID))
		{
			$htmlID = '#' . $htmlID;
		}

		$this->assertElementExists($htmlID);
		$this->assertElementAttributeExists($htmlID, 'type');
		$this->assertElementAttributeContains($htmlID, 'type', 'checkbox');
	}

	public function checkCheckbox($htmlID)
	{
		if(preg_match('/^#/', $htmlID))
		{
			$htmlID = substr($htmlID, 1);
		}

		$this->getSession()->getPage()->checkField($htmlID);
	}

	public function assertCheckboxChecked($htmlID)
	{
		if(preg_match('/^#/', $htmlID))
		{
			$htmlID = substr($htmlID, 1);
		}

		$this->assertSession()->checkboxChecked($htmlID);
	}

	public function fillTextValue($htmlID, $value)
	{
		if(preg_match('/^#/', $htmlID))
		{
			$htmlID = substr($htmlID, 1);
		}

		$this->getSession()->getPage()->fillField($htmlID, $value);
	}

	public function assertTextValue($htmlID, $value)
	{
		if(preg_match('/^#/', $htmlID))
		{
			$htmlID = substr($htmlID, 1);
		}

		$this->assertSession()->fieldValueEquals($htmlID, $value);
	}
}
