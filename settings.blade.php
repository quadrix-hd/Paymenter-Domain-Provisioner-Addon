<x-filament-panels::page>

    {{-- Einstellungs-Formular --}}
    <x-filament::section>
        <x-slot name="heading">Einstellungen</x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex gap-3">
                <x-filament::button type="submit" color="primary">
                    💾 Einstellungen speichern
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Aktive Domains-Tabelle --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Aktive Domain-Provisions ({{ $this->getProvisions()->count() }})</x-slot>

        @php $provisions = $this->getProvisions() @endphp

        @if($provisions->isEmpty())
            <p class="text-gray-400 text-sm">Noch keine Domains provisioniert.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="py-2 pr-4">Bestellung</th>
                            <th class="py-2 pr-4">Domain</th>
                            <th class="py-2 pr-4">Server-IP</th>
                            <th class="py-2 pr-4">CF Record-ID</th>
                            <th class="py-2 pr-4">Pangolin-ID</th>
                            <th class="py-2">Erstellt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($provisions as $p)
                        <tr class="border-b border-gray-700">
                            <td class="py-2 pr-4 font-mono">{{ $p->order_id }}</td>
                            <td class="py-2 pr-4 font-semibold">{{ $p->full_domain }}</td>
                            <td class="py-2 pr-4 font-mono">{{ $p->server_ip }}</td>
                            <td class="py-2 pr-4 font-mono text-xs text-gray-400">{{ $p->cf_record_id ?? '–' }}</td>
                            <td class="py-2 pr-4 font-mono text-xs text-gray-400">{{ $p->pangolin_resource_id ?? '–' }}</td>
                            <td class="py-2 text-gray-400 text-xs">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
