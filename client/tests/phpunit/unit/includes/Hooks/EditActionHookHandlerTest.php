<?php

namespace Wikibase\Client\Tests\Unit\Hooks;

use EditPage;
use Html;
use IContextSource;
use OutputPage;
use RequestContext;
use Title;
use Wikibase\Client\Hooks\EditActionHookHandler;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\Sql\SqlUsageTracker;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers \Wikibase\Client\Hooks\EditActionHookHandler
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Amir Sarabadani <ladsgroup@gmail.com>
 */
class EditActionHookHandlerTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider handleProvider
	 * @param string HTML $expected
	 * @param IContextSource $context
	 * @param EntityId|bool $entityId
	 * @param string $message
	 */
	public function testHandle( $expected, IContextSource $context, $entityId, $message ) {
		$out = new OutputPage( $context );
		$hookHandler = $this->newHookHandler( $entityId );
		$editor = $this->getEditPage();
		$tabindex = 0; // unused but must be a variable to be passed by reference
		$hookHandler->onEditPage__showStandardInputs_options( $editor, $out, $tabindex );

		$this->assertSame( $expected, $editor->editFormTextAfterTools, $message );
		$this->assertContains( 'wikibase.client.action.edit.collapsibleFooter', $out->getModules() );
	}

	public function handleProvider() {
		$context = $this->getContext();
		$labeledLink = '<a href="https://www.wikidata.org/wiki/Q4" class="external">Berlin</a>';
		$q5Link = '<a href="https://www.wikidata.org/wiki/Q5" class="external">Q5</a>';
		$explanation = $context->msg( 'wikibase-pageinfo-entity-usage' )->escaped();
		$header = '<div class="wikibase-entity-usage"><div class="wikibase-entityusage-explanation">';
		$header .= "<p>$explanation\n</p></div>";
		$cases = [];

		$cases[] = [
			"$header\n<ul><li>$labeledLink: Sitelink</li></ul></div>",
			$context,
			new ItemId( 'Q4' ),
			'item id link'
		];

		$cases[] = [
			'',
			$context,
			false,
			'page is not connected to an item'
		];

		$cases[] = [
			"$header\n<ul><li>$q5Link: Sitelink</li></ul></div>",
			$context,
			new ItemId( 'Q5' ),
			'No label for Q5'
		];

		return $cases;
	}

	/**
	 * @param ItemId|bool $entityId
	 *
	 * @return EditActionHookHandler
	 */
	private function newHookHandler( $entityId ) {
		$repoLinker = $this->getMockBuilder( RepoLinker::class )
			->disableOriginalConstructor()
			->setMethods( [ 'buildEntityLink' ] )
			->getMock();

		$repoLinker->method( 'buildEntityLink' )
			->willReturnCallback( function (
				EntityId $entityId,
				array $classes = [],
				$text = null
			) {
				return Html::rawElement( 'a', [
					'href' => 'https://www.wikidata.org/wiki/' . $entityId,
					'class' => $classes,
				], $text ?: $entityId );
			} );

		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );

		$siteLinkLookup->method( 'getItemIdForLink' )
			->willReturn( $entityId );

		$sqlUsageTracker = $this->getMockBuilder( SqlUsageTracker::class )
			->disableOriginalConstructor()
			->getMock();

		$entityUsage = $entityId ? [ new EntityUsage( $entityId, 'S' ) ] : null;
		$sqlUsageTracker->expects( $this->once() )
			->method( 'getUsagesForPage' )
			->willReturn( $entityUsage );

		$labelDescriptionLookupFactory = $this->getMockBuilder(
			LanguageFallbackLabelDescriptionLookupFactory::class
		)
			->disableOriginalConstructor()
			->getMock();

		$labelDescriptionLookupFactory->method( 'newLabelDescriptionLookup' )
			->willReturnCallback( [ $this, 'newLabelDescriptionLookup' ] );

		$idParser = $this->createMock( EntityIdParser::class );

		$idParser->method( 'parse' )
			->willReturnCallback( function ( $idSerialization ) {
				return new ItemId( $idSerialization );
			} );

		$hookHandler = new EditActionHookHandler(
			$repoLinker,
			$sqlUsageTracker,
			$labelDescriptionLookupFactory,
			$idParser
		);

		return $hookHandler;
	}

	/**
	 * @return IContextSource
	 */
	private function getContext() {
		$title = $this->getTitle();

		$context = new RequestContext();
		$context->setTitle( $title );

		$context->setLanguage( 'en' );

		return $context;
	}

	/**
	 * @return LabelDescriptionLookup
	 */
	public function newLabelDescriptionLookup() {
		$lookup = $this->createMock( LabelDescriptionLookup::class );

		$lookup->method( 'getLabel' )
			->willReturnCallback( function ( EntityId $entityId ) {
				switch ( $entityId->getSerialization() ) {
					case 'Q4':
						return new Term( 'en', 'Berlin' );
					default:
						return null;
				}
			} );

		return $lookup;
	}

	/**
	 * @return EditPage
	 */
	private function getEditPage() {
		$title = $this->getTitle();

		$editor = $this->getMockBuilder( EditPage::class )
			->disableOriginalConstructor()
			->getMock();

		$editor->method( 'getTitle' )
			->willReturn( $title );

		$editor->editFormTextAfterTools = '';

		return $editor;
	}

	/**
	 * @return Title
	 */
	private function getTitle() {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();

		$title->method( 'exists' )
			->willReturn( true );

		$title->method( 'getNamespace' )
			->willReturn( NS_MAIN );

		$title->method( 'getPrefixedText' )
			->willReturn( 'Cat' );

		return $title;
	}

}
