<?php

use PHPUnit\Framework\TestCase;

class QuoteGeneratorTest extends TestCase
{
    public function testNextReturnsArray()
    {
        $quote = Utils_QuoteGenerator::next();
        $this->assertIsArray($quote);
    }

    public function testNextHasRequiredKeys()
    {
        $quote = Utils_QuoteGenerator::next();
        $this->assertArrayHasKey(0, $quote);
        $this->assertArrayHasKey(1, $quote);
    }

    public function testNextReturnsStringValues()
    {
        $quote = Utils_QuoteGenerator::next();
        $this->assertIsString($quote[0]);
        $this->assertIsString($quote[1]);
    }

    public function testNextReturnsNonEmptyStrings()
    {
        $quote = Utils_QuoteGenerator::next();
        $this->assertNotEmpty($quote[0]);
        $this->assertNotEmpty($quote[1]);
    }

    public function testNextReturnsMultipleTimes()
    {
        // Ensure multiple calls don't throw errors
        for ($i = 0; $i < 20; $i++) {
            $quote = Utils_QuoteGenerator::next();
            $this->assertIsArray($quote);
            $this->assertCount(2, $quote);
        }
    }
}
