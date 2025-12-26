@extends('layout')

@section('content')
    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Mijn Team</h5>
        <a href="/nieuwegein/admin" class="btn btn-primary btn-sm">Nieuwe Collega +</a>
    </div>

    {{-- Succes melding weergeven --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="erp-card">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Naam</th>
                    <th>Email</th>
                    <th>Contract</th>
                    <th>Rol</th>
                    <th class="text-end">Acties</th> {{-- Nieuwe kolom --}}
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td class="fw-bold">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <small class="text-muted">
                            {{ $user->contract_days }} dagen / {{ $user->contract_hours }} uur
                        </small>
                    </td>
                    <td><span class="badge bg-info text-dark">Employee</span></td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            {{-- Edit Knop --}}
                            <a href="/nieuwegein/team/{{ $user->id }}/edit" class="btn btn-sm btn-outline-secondary">
                                ✏️ Bewerk
                            </a>

                            {{-- Delete Knop (moet in een formulier) --}}
                            <form action="/nieuwegein/team/{{ $user->id }}" method="POST" onsubmit="return confirm('Weet je zeker dat je {{ $user->name }} wilt verwijderen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection