<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
	protected static string $resource = BookResource::class;

	/**
	 * Redirect to new empty form after submit
	 */
	protected function getRedirectUrl(): string
	{
		return $this->getResource()::getUrl('create');
	}
}
