<?php

namespace Wikibase\Client\Tests\Unit\Hooks;

use File;
use Title;

use Wikibase\Client\Hooks\SkinAfterBottomScriptsHandler;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\Lib\EntityTypeDefinitions;

/**
 * @covers \Wikibase\Client\Hooks\SkinAfterBottomScriptsHandler
 *
 * @group WikibaseClient
 * @group WikibaseHooks
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class SkinAfterBottomScriptsHandlerTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider createSchemaProvider
	 */
	public function testCreateSchema( $revisionTimestamp, $image, $description, $expected ) {
		$client = WikibaseClient::getDefaultInstance();
		$repoLinker = new RepoLinker(
			new EntitySourceDefinitions( [], new EntityTypeDefinitions( [] ) ),
			'https://www.wikidata.org',
			'/wiki/$1',
			'/w'
		);
		$handler = new SkinAfterBottomScriptsHandler( 'en', $repoLinker, $client->getTermLookup() );

		$title = $this->mockTitle( 'https://de.wikipedia.org/wiki', 'Douglas Adams', '1022523983' );
		$actual = $handler->createSchema(
			$title, $revisionTimestamp, 'https://www.wikidata.org/entity/Q42', $image, $description
		);
		$this->assertSchemaSubset( $expected, $actual );
	}

	private function assertSchemaSubset( array $expected, array $actual ) {
		foreach ( $expected as $key => $val ) {
			$this->assertArrayHasKey( $key, $actual );
			if ( is_array( $val ) ) {
				$this->assertSchemaSubset( $expected[$key], $actual[$key] );
			} else {
				$this->assertSame( $val, $actual[$key] );
			}
		}
	}

	public function createSchemaProvider() {
		$nullExpected = [
			"@context" => "https://schema.org",
			"@type" => "Article",
			"name" => "Douglas Adams",
			"url" => "https://de.wikipedia.org/wiki/Douglas_Adams",
			"sameAs" => "https://www.wikidata.org/entity/Q42",
			"mainEntity" => "https://www.wikidata.org/entity/Q42",
			"author" => [ "@type" => "Organization" ],
			"publisher" => [
				"@type" => "Organization",
				"logo" => [
					"@type" => "ImageObject"
				]
			],
			"datePublished" => "2002-05-27T18:26:23Z"
		];

		$image = $this->mockFile(
			'https://upload.wikimedia.org/wikipedia/commons/c/c0/Douglas_adams_portrait_cropped.jpg'
		);
		$nonNullExpected = [
			"@context" => "https://schema.org",
			"@type" => "Article",
			"name" => "Douglas Adams",
			"url" => "https://de.wikipedia.org/wiki/Douglas_Adams",
			"sameAs" => "https://www.wikidata.org/entity/Q42",
			"mainEntity" => "https://www.wikidata.org/entity/Q42",
			"author" => [ "@type" => "Organization" ],
			"publisher" => [
				"@type" => "Organization",
				"logo" => [
					"@type" => "ImageObject"
				]
			],
			"datePublished" => "2002-05-27T18:26:23Z",
			"dateModified" => "2018-09-28T20:16:12Z",
			"image" => "https://upload.wikimedia.org/wikipedia/commons/c/c0/Douglas_adams_portrait_cropped.jpg",
			"headline" => "British author and humorist (1952–2001)"
		];

		return [
			[ null, null, null, $nullExpected ],
			[ '1538165772', $image, 'British author and humorist (1952–2001)', $nonNullExpected ]
		];
	}

	/**
	 * @param string|null $url
	 * @return File
	 */
	private function mockFile( $url = null ) {
		$mock = $this->getMockForAbstractClass(
			File::class, [ false, false ], '', true, true, true, [ 'getUrl' ]
		);
		$mock->method( 'getUrl' )
			->willReturn( $url );
		return $mock;
	}

	/**
	 * @param string $baseURL
	 * @param string $text
	 * @param string|null $earliestRevTimestamp
	 * @return Title
	 */
	private function mockTitle( $baseURL, $titleText, $earliestRevTimestamp = null ) {
		$mock = $this->createMock( Title::class );
		$mock->method( 'getFullURL' )
			->willReturn( $baseURL . '/' . str_replace( ' ', '_', $titleText ) );
		$mock->method( 'getText' )
			->willReturn( $titleText );
		$mock->method( 'getEarliestRevTime' )
			->willReturn( $earliestRevTimestamp );
		return $mock;
	}

}
