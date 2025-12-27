@extends('layout')

@section('content')
    <div class="top-bar">
        <h5 class="m-0 fw-bold text-secondary">Dashboard / Rooster</h5>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <div class="row">
        {{-- Linker kolom: Kalender --}}
        <div class="col-md-8">
            <div class="erp-card">
                <div id='calendar' style="max-height: 700px; font-size: 0.85rem;"></div>
            </div>
        </div>

        {{-- Rechter kolom: Uren Overzicht --}}
        <div class="col-md-4">
            <div class="erp-card">
                <h6 class="fw-bold mb-3">Urenstand (Deze Maand)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>Contract</th>
                                <th>Gepland</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats as $user)
                                @php
                                    // Bereken maandtarget (contract per week * 4)
                                    $monthlyTarget = $user->contract_hours * 4;
                                @endphp
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td><small class="text-muted">{{ $user->contract_hours }}u/w</small></td>
                                    <td class="fw-bold {{ $user->worked_hours > $monthlyTarget ? 'text-danger' : 'text-success' }}">
                                        {{ $user->worked_hours }}u
                                    </td>
                                    <td>
                                        @if($user->worked_hours >= $monthlyTarget)
                                            <span class="badge bg-success">Compleet</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Open</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <small class="text-muted fst-italic">Gebaseerd op contracturen per dag.</small>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'nl',
                height: 600,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                // Zorg dat de tijden goed worden getoond
                eventTimeFormat: { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    meridiem: false 
                },
                displayEventEnd: true, // Laat ook de eindtijd zien
                events: '/nieuwegein/schedule/api' 
            });
            calendar.render();
        });
    </script>
@endsection