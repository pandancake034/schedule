@extends('layout')

@section('content')
    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Collega Bewerken: {{ $user->name }}</h5>
        <a href="/nieuwegein/team" class="btn btn-light btn-sm border">Terug</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="erp-card">
                <form action="/nieuwegein/team/{{ $user->id }}" method="POST">
                    @csrf
                    @method('PUT') {{-- Belangrijk voor updaten --}}
                    
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
                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label>Email Adres</label>
                        <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Werkdagen per week</label>
                            <input type="number" name="contract_days" class="form-control" value="{{ $user->contract_days }}" min="1" max="7" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Uren per week</label>
                            <input type="number" name="contract_hours" class="form-control" value="{{ $user->contract_hours }}" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Beschikbaarheid Voorkeur</label>
                        <select name="shift_preference" class="form-control">
                            <option value="BOTH" {{ $currentPreference == 'BOTH' ? 'selected' : '' }}>Beide (Ochtend & Middag)</option>
                            <option value="AM" {{ $currentPreference == 'AM' ? 'selected' : '' }}>Alleen Ochtend</option>
                            <option value="PM" {{ $currentPreference == 'PM' ? 'selected' : '' }}>Alleen Middag</option>
                        </select>
                        <div class="form-text">Let op: Dit werkt de voorkeur bij voor alle dagen van de week.</div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Vaste Werkdagen</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach(['Monday'=>'Ma', 'Tuesday'=>'Di', 'Wednesday'=>'Wo', 'Thursday'=>'Do', 'Friday'=>'Vr', 'Saturday'=>'Za', 'Sunday'=>'Zo'] as $eng => $nl)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fixed_days[]" value="{{ $eng }}" id="fd_edit_{{ $eng }}"
                                        {{ in_array($eng, $user->fixed_days ?? []) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="fd_edit_{{ $eng }}">{{ $nl }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">💾 Wijzigingen Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection