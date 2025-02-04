<?php

namespace Wikibase\Client\Tests\Unit\DataAccess\Scribunto;

use DataValues\DataValue;
use DataValues\Serializers\DataValueSerializer;
use DataValues\StringValue;
use Language;
use Wikibase\Client\DataAccess\Scribunto\SnakSerializationRenderer;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Serializers\SnakSerializer;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * @covers \Wikibase\Client\DataAccess\Scribunto\SnakSerializationRenderer
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseScribunto
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class SnakSerializationRendererTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @param DataValue $value
	 *
	 * @return array
	 */
	private function getSnakSerialization( DataValue $value ) {
		$snak = new PropertyValueSnak(
			new PropertyId( 'P42' ),
			$value
		);

		$snakSerializer = new SnakSerializer( new DataValueSerializer() );
		$serialized = $snakSerializer->serialize( $snak );

		return $serialized;
	}

	/**
	 * @return SnakSerializationRenderer
	 */
	private function getSnakRenderer() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		$snakFormatter = $this->createMock( SnakFormatter::class );
		$snakFormatter->method( 'formatSnak' )
			->willReturnCallback( function ( PropertyValueSnak $snak ) {
				$value = $snak->getDataValue();
				if ( $value instanceof EntityIdValue ) {
					return $value->getEntityId()->getSerialization();
				} else {
					return $value->getValue();
				}
			} );
		$snakFormatter->method( 'getFormat' )
			->willReturn( SnakFormatter::FORMAT_PLAIN );

		$snakDeserializer = $wikibaseClient->getBaseDataModelDeserializerFactory()->newSnakDeserializer();
		$snaksDeserializer = $wikibaseClient->getBaseDataModelDeserializerFactory()->newSnakListDeserializer();

		return new SnakSerializationRenderer(
			$snakFormatter,
			$snakDeserializer,
			Language::factory( 'en' ),
			$snaksDeserializer
		);
	}

	public function testRenderSnak() {
		$snakSerialization = $this->getSnakSerialization( new StringValue( 'foo bar foo' ) );
		$snakRenderer = $this->getSnakRenderer();

		$this->assertSame( 'foo bar foo', $snakRenderer->renderSnak( $snakSerialization ) );
	}

	public function provideRenderSnaks() {
		return [
			'Single Snak' => [
				'foo bar foo',
				[ 'P42' => [
					$this->getSnakSerialization( new StringValue( 'foo bar foo' ) ),
				] ]
			],
			'Multiple Snaks' => [
				'foo, bar, Berlin',
				[ 'P42' => [
					$this->getSnakSerialization( new StringValue( 'foo' ) ),
					$this->getSnakSerialization( new StringValue( 'bar' ) ),
					$this->getSnakSerialization( new StringValue( 'Berlin' ) ),
					$this->getSnakSerialization( new StringValue( '' ) ),
				] ]
			]
		];
	}

	/**
	 * @dataProvider provideRenderSnaks
	 */
	public function testRenderSnaks( $expected, array $snaksSerialization ) {
		$snakRenderer = $this->getSnakRenderer();

		$this->assertSame( $expected, $snakRenderer->renderSnaks( $snaksSerialization ) );
	}

}
