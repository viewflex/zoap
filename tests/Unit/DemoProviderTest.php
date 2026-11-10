<?php

namespace Viewflex\Zoap\Tests\Unit;

use Viewflex\Zoap\Demo\DemoProvider;
use Viewflex\Zoap\Demo\Types\Product;
use Viewflex\Zoap\Tests\Support\Soap;
use Viewflex\Zoap\Tests\TestCase;

class DemoProviderTest extends TestCase
{
    public function testAuthenticateWithToken()
    {
        $this->assertTrue(DemoProvider::authenticate(Soap::TOKEN));
        $this->assertFalse(DemoProvider::authenticate('wrong'));
    }

    public function testAuthenticateWithUserAndPassword()
    {
        $this->assertTrue(DemoProvider::authenticate('', Soap::USER, Soap::PASSWORD));
        $this->assertFalse(DemoProvider::authenticate('', Soap::USER, 'wrong'));
    }

    public function testAuthenticateWithoutCredentials()
    {
        $this->assertFalse(DemoProvider::authenticate());
        $this->assertFalse(DemoProvider::authenticate('', Soap::USER));
    }

    public function testGetTokenOnlyForKnownUser()
    {
        $this->assertSame(Soap::TOKEN, DemoProvider::getToken(Soap::USER));
        $this->assertSame('', DemoProvider::getToken('someone@else.com'));
    }

    public function testFindProducts()
    {
        $this->assertInstanceOf(Product::class, DemoProvider::findProduct(456));
        $this->assertCount(2, DemoProvider::findProductsBy(['category' => 'Outerwear']));
    }
}
