<div class="space-y-4">
	@foreach($photos as $photo)
	<img src="{{ asset('storage/' . $photo) }}" class="w-full rounded-lg shadow-md">
	@endforeach
</div>