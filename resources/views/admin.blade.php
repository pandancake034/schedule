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
        @csrf
        
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="m-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-3">
            <label>Volledige Naam</label>
            <input type="text" name="name" class="form-control" required placeholder="Bijv. Jan Jansen">
        </div>
        
        <div class="mb-3">
            <label>Email Adres</label>
            <input type="email" name="email" class="form-control" required placeholder="jan@bedrijf.nl">
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Werkdagen per week</label>
                <input type="number" name="contract_days" class="form-control" value="5" min="1" max="7" required>
            </div>
            <div class="col-md-6 mb-3">
                <label>Uren per week</label>
                <input type="number" name="contract_hours" class="form-control" value="40" min="1" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Beschikbaarheid Voorkeur</label>
            <select name="shift_preference" class="form-control">
                <option value="BOTH">Beide (Ochtend & Middag)</option>
                <option value="AM">Alleen Ochtend</option>
                <option value="PM">Alleen Middag</option>
            </select>
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
        {{-- Nieuwe Wis-knop --}}
        <form action="/nieuwegein/admin/clear" method="POST" onsubmit="return confirm('Weet je zeker dat je het HELE rooster wilt wissen? Dit kan niet ongedaan worden gemaakt!');">
            @csrf
            <button type="submit" class="btn btn-outline-danger w-100">🗑️ Wis volledig rooster</button>
        </form>
    </div>
</div>
@endsection
