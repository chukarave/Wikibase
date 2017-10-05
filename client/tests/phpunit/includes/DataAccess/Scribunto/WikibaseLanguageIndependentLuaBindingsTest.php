<?php

namespace Wikibase\Client\Tests\DataAccess\Scribunto;

use PHPUnit_Framework_TestCase;
use Wikibase\Client\DataAccess\Scribunto\WikibaseLanguageIndependentLuaBindings;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\Usage\HashUsageAccumulator;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\HashSiteLinkStore;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\SettingsArray;

/**
 * @covers Wikibase\Client\DataAccess\Scribunto\WikibaseLanguageIndependentLuaBindings
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseScribunto
 *
 * @license GPL-2.0+
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class WikibaseLanguageIndependentLuaBindingsTest extends PHPUnit_Framework_TestCase {

	public function testConstructor() {
		$wikibaseLuaBindings = $this->getWikibaseLanguageIndependentLuaBindings(
			$this->getMock( SiteLinkLookup::class )
		);

		$this->assertInstanceOf( WikibaseLanguageIndependentLuaBindings::class, $wikibaseLuaBindings );
	}

	/**
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param UsageAccumulator|null $usageAccumulator
	 *
	 * @return WikibaseLanguageIndependentLuaBindings
	 */
	private function getWikibaseLanguageIndependentLuaBindings(
		SiteLinkLookup $siteLinkLookup,
		UsageAccumulator $usageAccumulator = null
	) {
		return new WikibaseLanguageIndependentLuaBindings(
			$siteLinkLookup,
			new SettingsArray(),
			$usageAccumulator ?: new HashUsageAccumulator(),
			'enwiki'
		);
	}

	private function hasUsage( array $actualUsages, EntityId $entityId, $aspect ) {
		$usage = new EntityUsage( $entityId, $aspect );
		$key = $usage->getIdentityString();
		return isset( $actualUsages[$key] );
	}

	public function testGetSettings() {
		$settings = new SettingsArray();
		$settings->setSetting( 'a-setting', 'a-value' );

		$bindings = new WikibaseLanguageIndependentLuaBindings(
			$this->getMock( SiteLinkLookup::class ),
			$settings,
			new HashUsageAccumulator(),
			'enwiki'
		);

		$this->assertSame(
			'a-value',
			$bindings->getSetting( 'a-setting' )
		);
	}

	public function testGetEntityId() {
		$item = new Item( new ItemId( 'Q33' ) );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Rome' );

		$usages = new HashUsageAccumulator();

		$siteLinkStore = new HashSiteLinkStore();
		$siteLinkStore->saveLinksOfItem( $item );

		$wikibaseLuaBindings = $this->getWikibaseLanguageIndependentLuaBindings(
			$siteLinkStore,
			$usages
		);

		$id = $wikibaseLuaBindings->getEntityId( 'Rome' );
		$this->assertSame( 'Q33', $id );

		$itemId = new ItemId( $id );
		$this->assertTrue(
			$this->hasUsage( $usages->getUsages(), $itemId, EntityUsage::TITLE_USAGE ),
			'title usage'
		);
		$this->assertFalse(
			$this->hasUsage( $usages->getUsages(), $itemId, EntityUsage::SITELINK_USAGE ),
			'sitelink usage'
		);

		$id = $wikibaseLuaBindings->getEntityId( 'Barcelona' );
		$this->assertNull( $id );
	}

	public function getSiteLinkPageNameProvider() {
		return [
			[ 'Beer', 'Q666', null ],
			[ 'Bier', 'Q666', 'dewiki' ],
			[ null, 'DoesntExist', null ],
			[ null, 'DoesntExist', 'foobar' ],
		];
	}

	/**
	 * @dataProvider getSiteLinkPageNameProvider
	 */
	public function testGetSiteLinkPageName( $expected, $itemId, $globalSiteId ) {
		$item = $this->getItem();

		$siteLinkStore = new HashSiteLinkStore();
		$siteLinkStore->saveLinksOfItem( $item );

		$wikibaseLuaBindings = $this->getWikibaseLanguageIndependentLuaBindings( $siteLinkStore );
		$this->assertSame( $expected, $wikibaseLuaBindings->getSiteLinkPageName( $itemId, $globalSiteId ) );
	}

	public function provideGlobalSiteId() {
		return [
			[ [ 'Q666#T' ], null ],
			[ [ 'Q666#T' ], 'enwiki' ],
			[ [ 'Q666#S' ], 'dewiki' ],
			[ [ 'Q666#S' ], 'whateverwiki' ],
		];
	}

	/**
	 * @dataProvider provideGlobalSiteId
	 */
	public function testGetSiteLinkPageName_usage( array $expectedUsages, $globalSiteId ) {
		$item = $this->getItem();

		$siteLinkStore = new HashSiteLinkStore();
		$siteLinkStore->saveLinksOfItem( $item );

		$usages = new HashUsageAccumulator();

		$wikibaseLuaBindings = $this->getWikibaseLanguageIndependentLuaBindings(
			$siteLinkStore,
			$usages
		);

		$itemId = $item->getId();
		$wikibaseLuaBindings->getSiteLinkPageName( $itemId->getSerialization(), $globalSiteId );

		$this->assertSame( $expectedUsages, array_keys( $usages->getUsages() ) );
	}

	/**
	 * @return Item
	 */
	private function getItem() {
		$item = new Item( new ItemId( 'Q666' ) );
		$item->setLabel( 'en', 'Beer' );
		$item->setDescription( 'en', 'yummy beverage' );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Beer' );
		$item->getSiteLinkList()->addNewSiteLink( 'dewiki', 'Bier' );

		return $item;
	}

}
