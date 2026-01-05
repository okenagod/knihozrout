<?php

namespace Tests\Unit;

use App\DTO\BookData;
use App\Services\LibraryService;
use App\Services\LibraryServices\LibraryConnectorInterface;
use Tests\TestCase;

class LibraryServiceTest extends TestCase
{
    public function test_it_returns_the_first_successful_result_from_connectors()
    {
        $isbn = '1234567890';
        $expectedData = new BookData('First Title', 'Author', 'Publisher', 2023);

        $connector1 = $this->createMock(LibraryConnectorInterface::class);
        $connector1->method('fetch')->willReturn(null);

        $connector2 = $this->createMock(LibraryConnectorInterface::class);
        $connector2->method('fetch')->willReturn($expectedData);

        $connector3 = $this->createMock(LibraryConnectorInterface::class);
        $connector3->expects($this->never())->method('fetch');

        $service = new LibraryService();
        
        // Injecting mocks into the connectors array via reflection since they are hardcoded in constructor
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('connectors');
        $property->setAccessible(true);
        $property->setValue($service, [$connector1, $connector2, $connector3]);

        $result = $service->searchByIsbn($isbn);

        $this->assertSame($expectedData, $result);
    }

    public function test_it_returns_null_if_all_connectors_fail()
    {
        $isbn = '1234567890';

        $connector1 = $this->createMock(LibraryConnectorInterface::class);
        $connector1->method('fetch')->willReturn(null);

        $service = new LibraryService();
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('connectors');
        $property->setAccessible(true);
        $property->setValue($service, [$connector1]);

        $result = $service->searchByIsbn($isbn);

        $this->assertNull($result);
    }
}
