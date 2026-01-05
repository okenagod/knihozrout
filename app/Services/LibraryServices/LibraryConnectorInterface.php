<?php

namespace App\Services\LibraryServices;

use App\DTO\BookData;

interface LibraryConnectorInterface
{
	public function fetch(string $isbn): ?BookData;
}
