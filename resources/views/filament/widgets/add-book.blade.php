<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col items-center justify-center p-6 text-center">
            <h2 class="text-xl font-bold mb-4">Nová kniha ke zpracování?</h2>
            <p class="text-gray-500 mb-6">Přejděte na formulář pro rychlý sběr fotek a základních dat.</p>
            
            <x-filament::button 
                wire:click="goToCreate" 
                size="xl" 
                color="info"
                icon="heroicon-o-plus-circle"
            >
                Začít přidávat
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
