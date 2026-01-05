<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
	// Vypne ochranu a povolí ukládání všech sloupců, které máš v migraci
	protected $guarded = [];

	// Tady probíhá ta "magie" – Laravel automaticky převede pole na JSON a zpět
	protected $casts = [
		'photos' => 'array',
	];
}
