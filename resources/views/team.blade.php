@extends('layout')

@section('content')
    {{-- TOP BAR --}}
    <div class="top-bar d-flex justify-content-between align-items-center mb-4 p-3 bg-white border rounded-1 shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">
                <i class="bi bi-people-fill me-2 text-primary"></i>PERSONEELSBEHEER
            </h5>
        </div>
        <a href="/nieuwegein/admin" class="btn btn-primary btn-sm px-3 rounded-1 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
            <i class="bi bi-person-plus-fill me-2"></i>Nieuwe Medewerker
        </a>
    </div>

    {{-- SUCCESS MELDING --}}
    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center py-2 px-3 rounded-1 border-0 border-start border-4 border-success shadow-sm mb-4 bg-white">
            <i class="bi bi-check-circle-fill me-3 text-success"></i>
            <span class="fw-medium text-dark" style="font-size: 0.85rem;">{{ session('success') }}</span>
        </div>
    @endif

    {{-- TEAM TABEL --}}
    <div class="bg-white p-0 rounded-1 shadow-sm border">
        <div class="p-3 border-bottom bg-light">
            <span class="fw-bold text-secondary text-uppercase small">Medewerkersoverzicht</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small py-3" style="font-weight: 600; font-size: 0.75rem;">ID</th>
                        <th class="text-secondary text-uppercase small" style="font-weight: 600; font-size: 0.75rem;">Naam & Email</th>
                        <th class="text-secondary text-uppercase small" style="font-weight: 600; font-size: 0.75rem;">Contract</th>
                        <th class="text-secondary text-uppercase small" style="font-weight: 600; font-size: 0.75rem;">Rol</th>
                        <th class="text-end pe-4 text-secondary text-uppercase small" style="font-weight: 600; font-size: 0.75rem;">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td class="ps-4 text-muted small">#{{ $user->id }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark">{{ $user->name }}</span>
                                <span class="text-muted small">{{ $user->email }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock me-2 text-muted"></i>
                                <span class="text-dark">{{ $user->contract_hours }}u</span>
                                <span class="text-muted mx-1">/</span>
                                <span class="text-muted">{{ $user->contract_days }}d</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border fw-normal text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                Employee
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group" role="group">
                                {{-- Edit Knop --}}
                                <a href="/nieuwegein/team/{{ $user->id }}/edit" class="btn btn-sm btn-outline-secondary rounded-0 border-end-0" title="Bewerken">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                {{-- Delete Formulier --}}
                                <form action="/nieuwegein/team/{{ $user->id }}" method="POST" onsubmit="return confirm('Bevestig verwijdering van medewerker: {{ $user->name }}');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-0" title="Verwijderen">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection