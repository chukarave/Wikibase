<?php

namespace Wikibase\Client\Tests\Unit\DataAccess\Scribunto;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use Wikibase\Client\DataAccess\Scribunto\LuaFunctionCallTracker;

/**
 * @covers \Wikibase\Client\DataAccess\Scribunto\LuaFunctionCallTracker
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseScribunto
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class LuaFunctionCallTrackerTest extends \PHPUnit\Framework\TestCase {

	public function incrementKeyProvider() {
		return [
			'logging disabled' => [
				[],
				false,
				false
			],
			'per site group logging only' => [
				[ 'fancy.wikibase.client.scribunto.doStuff.call' ],
				true,
				false
			],
			'per wiki logging only' => [
				[ 'defancywiki.wikibase.client.scribunto.doStuff.call' ],
				false,
				true
			],
			'per wiki and per site group logging' => [
				[
					'defancywiki.wikibase.client.scribunto.doStuff.call',
					'fancy.wikibase.client.scribunto.doStuff.call'
				],
				true,
				true
			],
		];
	}

	/**
	 * @dataProvider incrementKeyProvider
	 */
	public function testIncrementKey( $expected, $trackLuaFunctionCallsPerSiteGroup, $trackLuaFunctionCallsPerWiki ) {
		$statsdFactory = $this->createMock( StatsdDataFactoryInterface::class );

		$keyBuffer = [];
		$statsdFactory->expects( $this->exactly( count( $expected ) ) )
			->method( 'increment' )
			->willReturnCallback( function ( $key ) use ( &$keyBuffer ) {
				$keyBuffer[] = $key;
			} );

		$tracker = new LuaFunctionCallTracker(
			$statsdFactory,
			'defancywiki',
			'fancy',
			$trackLuaFunctionCallsPerSiteGroup, $trackLuaFunctionCallsPerWiki
		);

		$tracker->incrementKey( 'wikibase.client.scribunto.doStuff.call' );

		$this->assertEquals( $expected, $keyBuffer );
	}

}
