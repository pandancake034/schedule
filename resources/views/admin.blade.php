@extends('layout')

@section('content')
    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Admin & Configuratie</h5>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="erp-card">
                <h5 class="mb-3">Nieuwe Medewerker</h5>
                <form action="/nieuwegein/admin/create-user" method="POST">
                    @csrf <div class="mb-3">
                        <label>Volledige Naam</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email Adres</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success">Opslaan</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
    <div class="erp-card">
        <h5 class="mb-3">Automatische Planning</h5>
        <p>Regels: Za-Wo (2 pers), Do-Vr (1 pers). Max 5 dagen werken.</p>
        
        <form action="/nieuwegein/admin/generate" method="POST">
            @csrf
            <div class="mb-3">
                <label>Startdatum (Maandag)</label>
                <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d', strtotime('next monday')) }}" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">🚀 Genereer Rooster</button>
        </form>
    </div>
</div>
@endsection
