<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de turnos</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css">
</head>
<body class="bg-gray-50 text-gray-900">
    @php use Illuminate\Support\Facades\Storage; @endphp
    <div class="max-w-5xl mx-auto py-10 px-6">
        <div class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Panel del negocio</h1>
                @if($business)
                    <p class="text-gray-600 mt-1">{{$business->name}} · {{$business->phone}}</p>
                @else
                    <p class="text-red-500 mt-2">No hay negocio asociado al usuario actual.</p>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-700 hover:text-gray-900 border border-gray-300 rounded px-3 py-1 bg-white">Cerrar sesión</button>
            </form>
        </div>

        @if (session('status'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white shadow rounded p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Datos del negocio</h2>
                    @if($business)
                        <span class="text-xs text-gray-500">Zona horaria: {{ $business->timezone }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ $business ? route('business.update', $business) : route('business.store') }}">
                    @csrf
                    @if($business)
                        @method('PUT')
                    @endif
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input name="name" value="{{ old('name', $business->name ?? '') }}" class="mt-1 w-full border-gray-300 rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                            <input name="phone" value="{{ old('phone', $business->phone ?? '') }}" class="mt-1 w-full border-gray-300 rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dirección</label>
                            <input name="address" value="{{ old('address', $business->address ?? '') }}" class="mt-1 w-full border-gray-300 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Zona horaria</label>
                            <input name="timezone" value="{{ old('timezone', $business->timezone ?? 'America/Caracas') }}" class="mt-1 w-full border-gray-300 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Instrucciones de pago (se muestran al cliente)</label>
                            <textarea name="payment_instructions" class="mt-1 w-full border-gray-300 rounded" rows="3">{{ old('payment_instructions', $business->payment_instructions ?? '') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                                {{ $business ? 'Actualizar negocio' : 'Crear negocio' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow rounded p-5">
                <h2 class="text-lg font-semibold mb-4">Crear turno manual</h2>
                <form method="POST" action="{{ route('appointments.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre del cliente</label>
                        <input name="customer_name" value="{{ old('customer_name') }}" class="mt-1 w-full border-gray-300 rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                        <input name="customer_phone" value="{{ old('customer_phone') }}" class="mt-1 w-full border-gray-300 rounded" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha</label>
                            <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="mt-1 w-full border-gray-300 rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Hora</label>
                            <input type="time" name="time" value="{{ old('time', now()->format('H:i')) }}" class="mt-1 w-full border-gray-300 rounded" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado</label>
                        <select name="status" class="mt-1 w-full border-gray-300 rounded">
                            @foreach(\App\Models\Appointment::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status') === $status || (!old('status') && $status === 'confirmed'))>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Guardar turno</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white shadow rounded mb-3 p-4 flex items-end gap-4">
            <form method="GET" action="{{ route('dashboard') }}" class="flex items-end gap-3 flex-wrap">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Filtrar por fecha</label>
                    <input type="date" name="date" value="{{ optional($selectedDate)->format('Y-m-d') }}" class="mt-1 border-gray-300 rounded" required>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Ver turnos</button>
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-100 text-gray-800 rounded border border-gray-300">Hoy</a>
                </div>
            </form>
            @if($selectedDate)
                <div class="ml-auto text-sm text-gray-600">Mostrando turnos para {{ $selectedDate->format('Y-m-d') }}</div>
            @endif
        </div>

        <div class="overflow-x-auto bg-white shadow rounded">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Fecha</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Hora</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Cliente</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Teléfono</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Estado</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Comprobante</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $appointment->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->time->format('H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->customer_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $appointment->customer_phone }}</td>
                            <td class="px-4 py-3 text-sm">
                                <form method="POST" action="/appointments/{{ $appointment->id }}/status">
                                    @csrf
                                    <select name="status" class="border-gray-300 rounded" onchange="this.form.submit()">
                                        @foreach (\App\Models\Appointment::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($appointment->status === $status)>
                                                {{ ucfirst(__($status)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($appointment->payment_proof_path)
                                    <a class="text-indigo-600 underline" href="{{ Storage::url($appointment->payment_proof_path) }}" target="_blank">Ver comprobante</a>
                                @else
                                    <span class="text-gray-500">No adjunto</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm space-x-2">
                                @if($appointment->status === 'pending_review')
                                    <form class="inline" method="POST" action="/appointments/{{ $appointment->id }}/status">
                                        @csrf
                                        <input type="hidden" name="status" value="confirmed">
                                        <button class="px-3 py-1 bg-green-600 text-white rounded">Aprobar pago</button>
                                    </form>
                                    <form class="inline" method="POST" action="/appointments/{{ $appointment->id }}/status">
                                        @csrf
                                        <input type="hidden" name="status" value="expired">
                                        <button class="px-3 py-1 bg-red-600 text-white rounded">Rechazar</button>
                                    </form>
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-5 text-center text-gray-500">No hay turnos para la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
