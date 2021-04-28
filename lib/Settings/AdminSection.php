<?php
namespace OCA\Approval\Settings;

use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\Settings\IIconSection;

use OCA\Approval\AppInfo\Application;

class AdminSection implements IIconSection {

	/** @var IL10N */
	private $l;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(string $appName,
				    IURLGenerator $urlGenerator,
				    IL10N $l
				    ) {
		$this->appName = $appName;
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * returns the ID of the section. It is supposed to be a lower case string
	 *
	 * @returns string
	 */
	public function getID(): string {
		return Application::ADMIN_SETTINGS_SECTION;
	}

	/**
	 * returns the translated name as it should be displayed, e.g. 'LDAP / AD
	 * integration'. Use the L10N service to translate it.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->l->t('Approval rules');
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the settings navigation. The sections are arranged in ascending order of
	 * the priority values. It is required to return a value between 0 and 99.
	 */
	public function getPriority(): int {
		return 60;
	}

	/**
	 * @return ?string The relative path to a an icon describing the section
	 */
	public function getIcon(): ?string {
		return $this->urlGenerator->imagePath('approval', 'app-dark.svg');
	}

}