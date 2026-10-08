<?php

namespace Tests\Unit;

use App\Support\BookMatcher;
use App\Support\Isbn;
use PHPUnit\Framework\TestCase;

class BookMatcherTest extends TestCase
{
    public function test_title_matching()
    {
        $this->assertTrue(BookMatcher::titleMatches('Špion, jemuž nevěřili', 'Špión, jemuž nevěřili'));
        $this->assertTrue(BookMatcher::titleMatches('Z očí do očí', 'Z očí do očí : rozhovory ze stejnojmenného pořadu ČT Brno'));
        $this->assertTrue(BookMatcher::titleMatches('VTEŘINY STRACHU', 'Vteřiny strachu'));
        $this->assertFalse(BookMatcher::titleMatches('Špion', 'Špión, jemuž nevěřili'));
        $this->assertFalse(BookMatcher::titleMatches(null, 'Cokoli'));
    }

    public function test_author_matching()
    {
        $this->assertSame('cave', BookMatcher::surname('Peter Cave, Růžena Loulová'));
        $this->assertSame('moravec', BookMatcher::surname('Moravec, František'));
        $this->assertTrue(BookMatcher::authorMatches('František Moravec', 'Moravec, František'));
        $this->assertTrue(BookMatcher::authorMatches(null, 'Peter Cave'));
        $this->assertTrue(BookMatcher::authorMatches('Kdokoli', null));
        $this->assertFalse(BookMatcher::authorMatches('Edgar Wallace', 'Peter Cave'));
    }

    public function test_isbn13_conversion()
    {
        $this->assertSame('9788085946918', Isbn::toIsbn13('80-85946-91-2'));
        $this->assertSame('9788085946918', Isbn::toIsbn13('978-80-85946-91-8'));
        $this->assertNull(Isbn::toIsbn13('13-030-68'));
    }
}
