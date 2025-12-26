@extends('layout')

@section('content')
    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Mijn Team</h5>
        <a href="/nieuwegein/admin" class="btn btn-primary btn-sm">Nieuwe Collega +</a>
    </div>

    <div class="erp-card">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Naam</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td class="fw-bold">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge bg-info text-dark">Employee</span></td>
                    <td><span class="badge bg-success">Actief</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
