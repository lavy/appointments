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
    <div class="max-w-5xl mx-auto py-10 px-6">
        <div class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Turnos de hoy</h1>
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

        <div class="overflow-x-auto bg-white shadow rounded">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Fecha</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Hora</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Cliente</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Teléfono</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Estado</th>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-5 text-center text-gray-500">No hay turnos para hoy.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
