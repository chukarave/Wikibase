<?php

namespace Wikibase\Repo\Specials;

use Html;
use HTMLForm;
use OutputPage;
use Status;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * Page for creating new Wikibase entities that contain a Fingerprint.
 *
 * @license GPL-2.0+
 */
abstract class SpecialNewEntity extends SpecialWikibaseRepoPage {

	/**
	 * Contains pieces of the sub-page name of this special page if a subpage was called.
	 * E.g. [ 'a', 'b' ] in case of 'Special:NewEntity/a/b'
	 * @var string[]|null
	 */
	protected $parts = null;

	/**
	 * @var SpecialPageCopyrightView
	 */
	private $copyrightView;

	/**
	 * @var EntityNamespaceLookup
	 */
	protected $entityNamespaceLookup;

	/**
	 * @param string $name Name of the special page, as seen in links and URLs.
	 * @param string $restriction User right required,
	 * @param SpecialPageCopyrightView $copyrightView
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 */
	public function __construct(
		$name,
		$restriction,
		SpecialPageCopyrightView $copyrightView,
		EntityNamespaceLookup $entityNamespaceLookup = null
	) {
		parent::__construct( $name, $restriction );

		$this->copyrightView = $copyrightView;
		if ( !$entityNamespaceLookup ) {
			$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();
		}
		$this->entityNamespaceLookup = $entityNamespaceLookup;
	}

	/**
	 * @see SpecialPage::doesWrites
	 *
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	public function isListed() {
		//For BC
		if ( $this->getEntityType() === null ) {
			return true;
		}

		return (bool)$this->entityNamespaceLookup->getEntityNamespace( $this->getEntityType() );
	}

	/**
	 * @return string Type id of the entity that will be created (eg: Item::ENTITY_TYPE value)
	 */
	protected function getEntityType() {
		//TODO Make abstract as soon as SpecialNewLexeme overrides this method
		return null;
	}

	/**
	 * @see SpecialWikibasePage::execute
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->checkPermissions();
		$this->checkBlocked();
		$this->checkReadOnly();

		$this->parts = ( $subPage === '' ? [] : explode( '/', $subPage ) );

		$form = $this->createForm();

		$form->prepareForm();

		/** @var Status|false $submitStatus `false` if form was not submitted */
		$submitStatus = $form->tryAuthorizedSubmit();

		if ( $submitStatus && $submitStatus->isGood() ) {
			$this->redirectToEntityPage( $submitStatus->getValue() );

			return;
		}

		$out = $this->getOutput();

		$this->displayBeforeForm( $out );

		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	/**
	 * @return array[]
	 */
	abstract protected function getFormFields();

	/**
	 * @return string Legend for the fieldset
	 */
	abstract protected function getLegend();

	/**
	 * @return string[] Warnings that should be presented to the user
	 */
	abstract protected function getWarnings();

	/**
	 * @return HTMLForm
	 */
	private function createForm() {
		return HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() )
			->setId( 'mw-newentity-form1' )
			->setSubmitID( 'wb-newentity-submit' )
			->setSubmitName( 'submit' )
			->setSubmitTextMsg( 'wikibase-newentity-submit' )
			->setWrapperLegendMsg( $this->getLegend() )
			->setSubmitCallback(
				function ( $data, HTMLForm $form ) {
					$validationStatus = $this->validateFormData( $data );
					if ( !$validationStatus->isGood() ) {
						return $validationStatus;
					}

					$entity = $this->createEntityFromFormData( $data );

					$summary = $this->createSummary( $entity );

					$saveStatus = $this->saveEntity(
						$entity,
						$summary,
						$form->getRequest()->getVal( 'wpEditToken' ),
						EDIT_NEW
					);

					if ( !$saveStatus->isGood() ) {
						return $saveStatus;
					}

					return Status::newGood( $entity );
				}
			);
	}

	/**
	 * @param array $formData
	 *
	 * @return EntityDocument
	 */
	abstract protected function createEntityFromFormData( array $formData );

	/**
	 * @param array $formData
	 *
	 * @return Status
	 */
	abstract protected function validateFormData( array $formData );

	/**
	 * @param EntityDocument $entity
	 *
	 * @return Summary
	 */
	abstract protected function createSummary( $entity );

	/**
	 * @return string
	 */
	private function getCopyrightText() {
		return $this->copyrightView->getHtml( $this->getLanguage(), 'wikibase-newentity-submit' );
	}

	/**
	 * @param OutputPage $output
	 */
	protected function displayBeforeForm( OutputPage $output ) {
		$output->addModules( 'wikibase.special.newEntity' );

		$output->addHTML( $this->getCopyrightText() );

		foreach ( $this->getWarnings() as $warning ) {
			$output->addHTML( Html::element( 'div', [ 'class' => 'warning' ], $warning ) );
		}
	}

	/**
	 * @param EntityDocument $entity
	 */
	private function redirectToEntityPage( EntityDocument $entity ) {
		$title = $this->getEntityTitle( $entity->getId() );
		$entityUrl = $title->getFullURL();
		$this->getOutput()->redirect( $entityUrl );
	}

}
