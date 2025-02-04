<?php

namespace Wikibase\Repo\Tests\Store\Sql;

use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Entity\NullEntityPrefetcher;
use Wikibase\DataModel\Services\EntityId\EntityIdPager;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\Sql\SiteLinkTable;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLBFactory;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use Wikibase\Repo\Store\Sql\ItemsPerSiteBuilder;

/**
 * @covers \Wikibase\Repo\Store\Sql\ItemsPerSiteBuilder
 *
 * @group Wikibase
 * @group WikibaseStore
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class ItemsPerSiteBuilderTest extends MediaWikiIntegrationTestCase {

	private const BATCH_SIZE = 5;

	/**
	 * @return ItemId
	 */
	private function getTestItemId() {
		return new ItemId( 'Q1234' );
	}

	/**
	 * @return Item
	 */
	private function getTestItem() {
		return new Item( $this->getTestItemId() );
	}

	/**
	 * @return SiteLinkTable
	 */
	private function getSiteLinkTable() {
		$mock = $this->getMockBuilder( SiteLinkTable::class )
			->disableOriginalConstructor()
			->getMock();

		$item = $this->getTestItem();
		$mock->expects( $this->exactly( 10 ) )
			->method( 'saveLinksOfItem' )
			->willReturn( true )
			->with( $this->equalTo( $item ) );

		return $mock;
	}

	/**
	 * @return EntityLookup
	 */
	private function getEntityLookup() {
		$mock = $this->createMock( EntityLookup::class );

		$item = $this->getTestItem();
		$mock->expects( $this->exactly( 10 ) )
			->method( 'getEntity' )
			->willReturn( $item )
			->with( $this->equalTo( $this->getTestItemId() ) );

		return $mock;
	}

	/**
	 * @return ItemsPerSiteBuilder
	 */
	private function getItemsPerSiteBuilder() {
		return new ItemsPerSiteBuilder(
			$this->getSiteLinkTable(),
			$this->getEntityLookup(),
			new NullEntityPrefetcher(),
			new FakeLBFactory( [ 'lb' => new FakeLoadBalancer( [ 'dbr' => $this->db ] ) ] )
		);
	}

	/**
	 * @return EntityIdPager
	 */
	private function getEntityIdPager() {
		$mock = $this->createMock( EntityIdPager::class );

		$itemIds = [
			$this->getTestItemId(),
			$this->getTestItemId(),
			$this->getTestItemId(),
			$this->getTestItemId(),
			$this->getTestItemId()
		];

		$mock->expects( $this->at( 0 ) )
			->method( 'fetchIds' )
			->willReturn( $itemIds )
			->with( $this->equalTo( self::BATCH_SIZE ) );

		$mock->expects( $this->at( 1 ) )
			->method( 'fetchIds' )
			->willReturn( $itemIds )
			->with( $this->equalTo( self::BATCH_SIZE ) );

		$mock->expects( $this->at( 2 ) )
			->method( 'fetchIds' )
			->willReturn( [] )
			->with( $this->equalTo( self::BATCH_SIZE ) );

		return $mock;
	}

	public function testRebuild() {
		$itemsPerSiteBuilder = $this->getItemsPerSiteBuilder();
		$itemsPerSiteBuilder->setBatchSize( self::BATCH_SIZE );

		$entityIdPager = $this->getEntityIdPager();
		$itemsPerSiteBuilder->rebuild( $entityIdPager );

		// The various mocks already verify they get called correctly,
		// so no need for assertions
		$this->assertTrue( true );
	}

}
