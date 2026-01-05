<?php

namespace App\Observers;

use App\Jobs\ProcessBookJob;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;

class BookObserver
{
	/**
	 * Handle the Book "created" event.
	 */
	public function created(Book $book): void
	{
		ProcessBookJob::dispatch($book);
	}

	/**
	 * Handle the Book "updated" event.
	 */
	public function updated(Book $book): void
	{
		//
	}

	/**
	 * Handle the Book "deleted" event.
	 */
	public function deleted(Book $book): void
	{
		// 1. Smazání hlavní fotky
		if ($book->main_photo)
		{
			Storage::disk('public')->delete($book->main_photo);
		}

		// 2. Smazání ostatních fotek z pole
		if ($book->photos && is_array($book->photos))
		{
			foreach ($book->photos as $photo)
			{
				Storage::disk('public')->delete($photo);
			}
		}
	}

	/**
	 * Handle the Book "restored" event.
	 */
	public function restored(Book $book): void
	{
		//
	}

	/**
	 * Handle the Book "force deleted" event.
	 */
	public function forceDeleted(Book $book): void
	{
		//
	}
}
