<?php

namespace App\DTO;

class BookData
{
	public function __construct(
		public readonly ?string $title,
		public readonly ?string $author,
		public readonly ?string $publisher,
		public readonly ?int $year,
	) {}
}