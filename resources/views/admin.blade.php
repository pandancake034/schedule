@extends('layout')

@section('content')
    {{-- TOP BAR --}}
    <div class="top-bar d-flex justify-content-between align-items-center mb-4 p-3 bg-white border rounded-1 shadow-sm">
        <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">
            <i class="bi bi-sliders me-2 text-primary"></i>ADMINISTRATIE & CONFIGURATIE
        </h5>
    </div>

    <div class="row g-4">
        {{-- KOLOM 1: NIEUWE MEDEWERKER --}}
        <div class="col-lg-7">
            <div class="bg-white p-0 rounded-1 shadow-sm border h-100">
                <div class="p-3 border-bottom bg-light">
                    <span class="fw-bold text-secondary text-uppercase small">Nieuwe Medewerker Registreren</span>
                </div>
                <div class="p-4">
                    <form action="/nieuwegein/admin/create-user" method="POST">
                        @csrf
                        
                        @if ($errors->any())
                            <div class="alert alert-danger rounded-0 border-0 border-start border-4 border-danger mb-4">
                                <ul class="m-0 ps-3 small">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Volledige Naam</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                                    <input type="text" name="name" class="form-control border-start-0" required placeholder="Bijv. J. Jansen">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">E-mailadres</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0" required placeholder="naam@bedrijf.nl">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Contractdagen / week</label>
                                <input type="number" name="contract_days" class="form-control form-control-sm" value="5" min="1" max="7" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Contracturen / week</label>
                                <input type="number" name="contract_hours" class="form-control form-control-sm" value="40" min="1" required>
                            </div>
                        </div>

                        <div class="mb-3 p-3 bg-light border rounded-1">
                            <label class="form-label fw-bold small text-secondary d-block mb-2">Vaste Werkdagen (Verplicht)</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(['Monday'=>'Ma', 'Tuesday'=>'Di', 'Wednesday'=>'Wo', 'Thursday'=>'Do', 'Friday'=>'Vr', 'Saturday'=>'Za', 'Sunday'=>'Zo'] as $eng => $nl)
                                    <div class="form-check form-check-inline bg-white px-2 py-1 border rounded-1 m-0">
                                        <input class="form-check-input" type="checkbox" name="fixed_days[]" value="{{ $eng }}" id="fd_{{ $eng }}">
                                        <label class="form-check-label small" for="fd_{{ $eng }}">{{ $nl }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text text-muted small mt-2 fst-italic">
                                <i class="bi bi-info-circle me-1"></i>Selecteer dagen waarop deze medewerker <strong>altijd</strong> ingepland moet worden.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Voorkeur Shift</label>
                            <select name="shift_preference" class="form-select form-select-sm">
                                <option value="BOTH">Flexibel (Ochtend & Middag)</option>
                                <option value="AM">Alleen Ochtend (AM)</option>
                                <option value="PM">Alleen Middag (PM)</option>
                            </select>
                        </div>

                        <div class="border-top pt-3 text-end">
                            <button type="submit" class="btn btn-success btn-sm px-4 fw-bold text-uppercase rounded-1">
                                <i class="bi bi-check-lg me-2"></i>Opslaan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KOLOM 2: SYSTEEM ACTIES --}}
        <div class="col-lg-5">
            <div class="bg-white p-0 rounded-1 shadow-sm border h-100">
                <div class="p-3 border-bottom bg-light">
                    <span class="fw-bold text-secondary text-uppercase small">Systeemtaken</span>
                </div>
                <div class="p-4">
                    <div class="alert alert-light border small text-muted mb-4">
                        <h6 class="fw-bold text-dark"><i class="bi bi-gear-wide-connected me-2"></i>Parameters</h6>
                        <ul class="mb-0 ps-3">
                            <li>Weekstart: <strong>Zaterdag</strong></li>
                            <li>Bezetting: 2 pers. (Za-Wo), 1 pers. (Do-Vr)</li>
                        </ul>
                    </div>
                    
                    <h6 class="fw-bold small text-secondary mb-2">Handmatige Uitrol</h6>
                    <form action="/nieuwegein/schedule/generate" method="POST" class="mb-4">
                        @csrf
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-light">Startdatum</span>
                            <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d', strtotime('next saturday')) }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold text-uppercase rounded-1">
                            <i class="bi bi-cpu-fill me-2"></i>Genereer Rooster
                        </button>
                    </form>

                    <hr class="text-muted opacity-25">

                    <h6 class="fw-bold small text-danger mb-2">Gevarenzone</h6>
                    <form action="/nieuwegein/admin/clear" method="POST" onsubmit="return confirm('LET OP: Hiermee wordt de volledige planning gewist uit de database. Dit kan niet ongedaan worden gemaakt. Doorgaan?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-bold text-uppercase rounded-1">
                            <i class="bi bi-trash3-fill me-2"></i>Wis Database
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection