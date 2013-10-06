<?php

namespace Wikibase\Lib\Test;

use Wikibase\EntityId;
use Wikibase\Lib\PropertyNotFoundException;

/**
 * @covers Wikibase\Lib\PropertyNotFoundException
 *
 * @since 0.1
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertyNotFoundExceptionTest extends \PHPUnit_Framework_TestCase {

	public function testConstructorWithJustATable() {
		$propertyId = new EntityId( 'item', 42 );

		$exception = new PropertyNotFoundException( $propertyId );

		$this->assertEquals( $propertyId, $exception->getPropertyId() );
	}

}
